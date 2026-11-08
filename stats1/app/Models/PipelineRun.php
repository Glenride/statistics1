<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineRun extends Model
{
    protected $fillable = [
        'status',
        'started_at',
        'completed_at',
        'records_processed',
        'records_created',
        'records_updated',
        'connectors_run',
        'date_range',
        'error_log',
        'triggered_by',
        'ai_insights',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'connectors_run' => 'array',
        'date_range' => 'array',
    ];

    public function datasets(): HasMany
    {
        return $this->hasMany(UnifiedDataset::class);
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        $diff = $this->completed_at->diff($this->started_at);
        
        if ($diff->h > 0) {
            return $diff->format('%hh %im %ss');
        } elseif ($diff->i > 0) {
            return $diff->format('%im %ss');
        }
        
        return $diff->format('%ss');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'green',
            'running' => 'blue',
            'failed' => 'red',
            'pending' => 'yellow',
            default => 'gray',
        };
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}
