<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapedData extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'data_type',
        'title',
        'url',
        'content',
        'scraped_at',
        'pipeline_run_id',
    ];

    protected $casts = [
        'content' => 'array',
        'scraped_at' => 'datetime',
    ];

    public function pipelineRun(): BelongsTo
    {
        return $this->belongsTo(PipelineRun::class);
    }
}
