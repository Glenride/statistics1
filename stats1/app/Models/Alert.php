<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'source',
        'severity',
        'title',
        'description',
        'data',
        'entity_type',
        'entity_id',
        'read_at',
        'dismissed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    // Alert types
    public const TYPE_NEW_POLL = 'new_poll';
    public const TYPE_MAJOR_SHIFT = 'major_shift';
    public const TYPE_LEAD_CHANGE = 'lead_change';
    public const TYPE_UNUSUAL_RESULT = 'unusual_result';
    public const TYPE_FUNDRAISING = 'fundraising_milestone';
    public const TYPE_PAGE_CHANGE = 'page_change';
    public const TYPE_NEW_PDF = 'new_pdf';

    // Severity levels
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Scope: Unread alerts
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: Not dismissed
     */
    public function scopeActive($query)
    {
        return $query->whereNull('dismissed_at');
    }

    /**
     * Scope: By severity
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope: By source
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope: Recent (last N days)
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: By type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Critical alerts
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    /**
     * Mark as read
     */
    public function markAsRead(): self
    {
        $this->update(['read_at' => now()]);
        return $this;
    }

    /**
     * Dismiss the alert
     */
    public function dismiss(): self
    {
        $this->update(['dismissed_at' => now()]);
        return $this;
    }

    /**
     * Check if alert is read
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Check if alert is critical
     */
    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * Get severity color for UI
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            self::SEVERITY_CRITICAL => 'red',
            self::SEVERITY_WARNING => 'orange',
            default => 'blue',
        };
    }

    /**
     * Get type icon for UI
     */
    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            self::TYPE_NEW_POLL => 'chart-bar',
            self::TYPE_MAJOR_SHIFT => 'trending-up',
            self::TYPE_LEAD_CHANGE => 'swap-horizontal',
            self::TYPE_UNUSUAL_RESULT => 'alert-triangle',
            self::TYPE_FUNDRAISING => 'dollar-sign',
            self::TYPE_NEW_PDF => 'file-text',
            default => 'bell',
        };
    }

    /**
     * Create a new poll alert
     */
    public static function newPoll(string $source, string $pollster, array $data = []): self
    {
        return self::create([
            'type' => self::TYPE_NEW_POLL,
            'source' => $source,
            'severity' => self::SEVERITY_INFO,
            'title' => "New poll from {$pollster}",
            'description' => "A new poll has been released by {$pollster}",
            'data' => $data,
        ]);
    }

    /**
     * Create a major shift alert
     */
    public static function majorShift(string $source, string $candidate, float $shift, array $data = []): self
    {
        $direction = $shift > 0 ? 'gained' : 'lost';
        $severity = abs($shift) >= 5 ? self::SEVERITY_CRITICAL : self::SEVERITY_WARNING;

        return self::create([
            'type' => self::TYPE_MAJOR_SHIFT,
            'source' => $source,
            'severity' => $severity,
            'title' => "{$candidate} {$direction} " . abs($shift) . " points",
            'description' => "Significant polling movement detected for {$candidate}",
            'data' => array_merge($data, ['shift' => $shift]),
        ]);
    }

    /**
     * Create a lead change alert
     */
    public static function leadChange(string $source, string $newLeader, string $previousLeader, array $data = []): self
    {
        return self::create([
            'type' => self::TYPE_LEAD_CHANGE,
            'source' => $source,
            'severity' => self::SEVERITY_CRITICAL,
            'title' => "{$newLeader} takes the lead",
            'description' => "{$newLeader} has overtaken {$previousLeader} in the polling average",
            'data' => $data,
        ]);
    }
}
