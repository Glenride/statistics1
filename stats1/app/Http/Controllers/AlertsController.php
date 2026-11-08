<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\AlertService;
use App\Services\PressReleaseMonitorService;
use App\Services\PDFHunterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertsController extends Controller
{
    protected AlertService $alertService;
    protected PressReleaseMonitorService $monitorService;
    protected PDFHunterService $pdfService;

    public function __construct(
        AlertService $alertService,
        PressReleaseMonitorService $monitorService,
        PDFHunterService $pdfService
    ) {
        $this->alertService = $alertService;
        $this->monitorService = $monitorService;
        $this->pdfService = $pdfService;
    }

    /**
     * Get all alerts.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 50);
        $unreadOnly = $request->boolean('unread_only', false);

        if ($unreadOnly) {
            $alerts = $this->alertService->getUnreadAlerts();
        } else {
            $alerts = $this->alertService->getRecentAlerts($limit);
        }

        return response()->json([
            'alerts' => $alerts,
            'unread_count' => $this->alertService->getUnreadCount(),
        ]);
    }

    /**
     * Mark an alert as read.
     */
    public function markRead(Alert $alert): JsonResponse
    {
        $success = $this->alertService->markAsRead($alert->id);

        return response()->json([
            'success' => $success,
            'unread_count' => $this->alertService->getUnreadCount(),
        ]);
    }

    /**
     * Mark all alerts as read.
     */
    public function markAllRead(): JsonResponse
    {
        $count = $this->alertService->markAllAsRead();

        return response()->json([
            'success' => true,
            'marked_count' => $count,
        ]);
    }

    /**
     * Manually trigger a check for new content.
     */
    public function checkNow(): JsonResponse
    {
        $results = [
            'press_releases' => [],
            'pdfs' => [],
        ];

        // Check press release monitors
        try {
            $results['press_releases'] = $this->monitorService->checkForChanges();
        } catch (\Exception $e) {
            $results['press_releases_error'] = $e->getMessage();
        }

        // Scan for new PDFs
        try {
            $results['pdfs'] = $this->pdfService->scanForNewPDFs();
        } catch (\Exception $e) {
            $results['pdfs_error'] = $e->getMessage();
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get monitor status.
     */
    public function monitorStatus(): JsonResponse
    {
        return response()->json([
            'monitors' => $this->monitorService->getMonitorStatus(),
        ]);
    }
}
