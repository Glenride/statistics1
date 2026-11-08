<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PressReleaseMonitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'content_hash',
        'last_checked_at',
        'last_changed_at',
        'is_active',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'last_changed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Scope: Get active monitors
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if the page content has changed
     */
    public function hasChanged(string $newHash): bool
    {
        return $this->content_hash !== $newHash;
    }

    /**
     * Update the hash after checking
     */
    public function updateHash(string $newHash, bool $changed = false): bool
    {
        $data = [
            'content_hash' => $newHash,
            'last_checked_at' => now(),
        ];

        if ($changed) {
            $data['last_changed_at'] = now();
        }

        return $this->update($data);
    }
}
