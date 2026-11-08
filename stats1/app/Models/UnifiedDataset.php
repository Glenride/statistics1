<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnifiedDataset extends Model
{
    protected $fillable = [
        'date',
        'candidate',
        'candidate_id',
        'party',
        'party_full',
        'incumbent_challenge',
        'poll_avg',
        'poll_source',
        'poll_count',
        'poll_trend',
        'poll_high',
        'poll_low',
        'cash_on_hand',
        'total_receipts',
        'total_disbursements',
        'individual_contributions',
        'pac_contributions',
        'debts_owed',
        'inflation_rate',
        'unemployment_rate',
        'gdp_growth',
        'consumer_confidence',
        'source',
        'coverage_start',
        'coverage_end',
        'pipeline_run_id',
    ];

    protected $casts = [
        'date' => 'date',
        'coverage_start' => 'date',
        'coverage_end' => 'date',
        'poll_avg' => 'decimal:2',
        'poll_high' => 'decimal:2',
        'poll_low' => 'decimal:2',
        'poll_count' => 'integer',
        'cash_on_hand' => 'decimal:2',
        'total_receipts' => 'decimal:2',
        'total_disbursements' => 'decimal:2',
        'individual_contributions' => 'decimal:2',
        'pac_contributions' => 'decimal:2',
        'debts_owed' => 'decimal:2',
        'inflation_rate' => 'decimal:2',
        'unemployment_rate' => 'decimal:2',
        'gdp_growth' => 'decimal:2',
        'consumer_confidence' => 'decimal:2',
    ];

    public function pipelineRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByCandidate($query, $candidate)
    {
        return $query->where('candidate', $candidate);
    }

    public function scopeByParty($query, $party)
    {
        return $query->where('party', $party);
    }
}
