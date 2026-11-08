<?php

namespace App\Services;

use App\Models\Alert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AlertService
{
    /**
     * Create a new alert.
     */
    public function createAlert(
        string $type,
        string $source,
        string $title,
        ?string $description = null,
        ?array $metadata = null
    ): Alert {
        $alert = Alert::create([
            'type' => $type,
            'source' => $source,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        Log::info('Alert created', [
            'id' => $alert->id,
            'type' => $type,
            'source' => $source,
            'title' => $title,
        ]);

        return $alert;
    }

    /**
     * Create a "new poll" alert.
     */
    public function alertNewPoll(string $pollster, string $state = 'National', ?array $metadata = null): Alert
    {
        return $this->createAlert(
            Alert::TYPE_NEW_POLL,
            $pollster,
            "New poll from {$pollster}" . ($state !== 'National' ? " ({$state})" : ''),
            null,
            $metadata
        );
    }

    /**
     * Create a "page changed" alert for press releases.
     */
    public function alertPageChange(string $sourceName, string $url): Alert
    {
        return $this->createAlert(
            Alert::TYPE_PAGE_CHANGE,
            $sourceName,
            "BREAKING: {$sourceName} just updated their page",
            "A new press release may be available.",
            ['url' => $url]
        );
    }

    /**
     * Create a "new PDF" alert.
     */
    public function alertNewPDF(string $sourceName, string $pdfUrl): Alert
    {
        return $this->createAlert(
            Alert::TYPE_NEW_PDF,
            $sourceName,
            "New PDF detected from {$sourceName}",
            "A new poll report PDF may be available for download.",
            ['pdf_url' => $pdfUrl]
        );
    }

    /**
     * Get all unread alerts.
     */
    public function getUnreadAlerts(): Collection
    {
        return Alert::unread()->recent()->get();
    }

    /**
     * Get recent alerts (read and unread).
     */
    public function getRecentAlerts(int $limit = 20): Collection
    {
        return Alert::recent()->limit($limit)->get();
    }

    /**
     * Mark an alert as read.
     */
    public function markAsRead(int $alertId): bool
    {
        $alert = Alert::find($alertId);
        if (!$alert) {
            return false;
        }

        return $alert->markAsRead();
    }

    /**
     * Mark all alerts as read.
     */
    public function markAllAsRead(): int
    {
        return Alert::unread()->update(['is_read' => true]);
    }

    /**
     * Get count of unread alerts.
     */
    public function getUnreadCount(): int
    {
        return Alert::unread()->count();
    }

    /**
     * Send notification to external services (Slack, Discord, etc.)
     * This is a stub for future implementation.
     */
    public function sendNotification(Alert $alert): void
    {
        // TODO: Implement Slack/Discord/SMS integration
        // For now, just log the notification
        Log::info('Notification would be sent', [
            'alert_id' => $alert->id,
            'type' => $alert->type,
            'title' => $alert->title,
        ]);
    }
}
