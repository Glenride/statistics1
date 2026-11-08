<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    use HasFactory;

    protected $fillable = [
        'poll_id',
        'source',
        'pollster',
        'race_type',
        'state',
        'start_date',
        'end_date',
        'sample_size',
        'fte_grade',
        'source_type',
        'trust_score',
        'candidates_data',
        'content_hash',
        'needs_review',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'candidates_data' => 'array',
        'needs_review' => 'boolean',
    ];

    // Source types for bias tagging
    public const SOURCE_STANDARD = 'standard';
    public const SOURCE_R_INTERNAL = 'r_internal';
    public const SOURCE_D_INTERNAL = 'd_internal';
    public const SOURCE_PARTISAN = 'partisan';

    // Trust score ranges
    public const TRUST_GOLD_STANDARD = 100;  // NYT/Siena, Marist, Selzer
    public const TRUST_HIGH = 80;             // Quinnipiac, Emerson, Monmouth
    public const TRUST_MEDIUM = 60;           // most pollsters
    public const TRUST_LOW = 40;              // online panels
    public const TRUST_UNVERIFIED = 20;       // unknown/partisan

    /**
     * Scope: Filter by source (538, rcp, etc.)
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope: Filter by race type (presidential, senate, etc.)
     */
    public function scopeForRace($query, string $raceType)
    {
        return $query->where('race_type', $raceType);
    }

    /**
     * Scope: Filter by state
     */
    public function scopeForState($query, ?string $state)
    {
        if ($state) {
            return $query->where('state', $state);
        }
        return $query->whereNull('state'); // National polls
    }

    /**
     * Scope: Get polls needing manual review
     */
    public function scopeNeedsReview($query)
    {
        return $query->where('needs_review', true);
    }

    /**
     * Scope: Get high-trust polls only
     */
    public function scopeHighTrust($query, int $minScore = 70)
    {
        return $query->where('trust_score', '>=', $minScore);
    }

    /**
     * Scope: Get polls within date range
     */
    public function scopeInDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('end_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Order by recency
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('end_date', 'desc');
    }

    /**
     * Get the leading candidate from this poll
     */
    public function getLeadingCandidateAttribute(): ?array
    {
        $candidates = $this->candidates_data ?? [];
        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn($a, $b) => ($b['percentage'] ?? 0) <=> ($a['percentage'] ?? 0));
        return $candidates[0] ?? null;
    }

    /**
     * Get the margin/spread between top two candidates
     */
    public function getSpreadAttribute(): ?float
    {
        $candidates = $this->candidates_data ?? [];
        if (count($candidates) < 2) {
            return null;
        }

        usort($candidates, fn($a, $b) => ($b['percentage'] ?? 0) <=> ($a['percentage'] ?? 0));
        return ($candidates[0]['percentage'] ?? 0) - ($candidates[1]['percentage'] ?? 0);
    }
}
