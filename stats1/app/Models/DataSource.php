<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    protected $fillable = [
        'type',
        'category',
        'is_custom',
        'pipeline_enabled',
        'name',
        'description',
        'status',
        'config',
        'sync_handler',
        'record_count',
        'data_quality_score',
        'last_sync_at',
        'last_error',
    ];

    protected $casts = [
        'config' => 'array',
        'is_custom' => 'boolean',
        'pipeline_enabled' => 'boolean',
        'last_sync_at' => 'datetime',
        'data_quality_score' => 'decimal:2',
    ];

    public function datasets(): HasMany
    {
        return $this->hasMany(UnifiedDataset::class, 'source', 'type');
    }

    public function isHealthy(): bool
    {
        return $this->status === 'active' && $this->last_error === null;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'green',
            'inactive' => 'gray',
            'error' => 'red',
            default => 'gray',
        };
    }

    public function getCategoryColorAttribute(): string
    {
        return match ($this->category) {
            'core' => 'blue',
            'polling' => 'orange',
            'custom' => 'purple',
            default => 'gray',
        };
    }

    // Scopes for filtering
    public function scopePipelineEnabled($query)
    {
        return $query->where('pipeline_enabled', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_custom', true);
    }
}
