<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use App\Models\PipelineRun;
use App\Models\UnifiedDataset;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Get data source statuses
        $dataSources = DataSource::all();
        
        // If no data sources exist yet, create defaults
        if ($dataSources->isEmpty()) {
            $this->seedDefaultDataSources();
            $dataSources = DataSource::all();
        }

        // Sync API keys from env if they're set
        $this->syncApiKeysFromEnv($dataSources);

        // Reload after sync
        $dataSources = DataSource::all();

        // Get recent pipeline runs
        $recentRuns = PipelineRun::orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Get stats
        $totalRecords = UnifiedDataset::count();
        $lastSync = PipelineRun::where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->first();
        
        // Calculate data quality score (average across sources)
        $dataQualityScore = $dataSources->avg('data_quality_score') ?? 0;

        // Get sample data preview
        $dataPreview = UnifiedDataset::orderBy('date', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('dashboard', [
            'dataSources' => $dataSources,
            'recentRuns' => $recentRuns,
            'stats' => [
                'totalRecords' => $totalRecords,
                'lastSyncAt' => $lastSync?->completed_at?->toISOString(),
                'dataQualityScore' => round($dataQualityScore, 1),
                'activeConnectors' => $dataSources->where('status', 'active')->count(),
            ],
            'dataPreview' => $dataPreview,
        ]);
    }

    /**
     * Sync API keys from environment variables to data sources
     */
    private function syncApiKeysFromEnv($dataSources): void
    {
        foreach ($dataSources as $source) {
            $envKey = null;
            $hasApiKey = false;

            switch ($source->type) {
                case 'fec':
                    $envKey = env('FEC_API_KEY');
                    break;
                case 'fred':
                    $envKey = env('FRED_API_KEY');
                    break;
                case 'polling':
                    // Polling doesn't need an API key
                    $hasApiKey = true;
                    break;
            }

            if ($envKey && !empty($envKey)) {
                $config = $source->config ?? [];
                $config['api_key'] = $envKey;
                $source->config = $config;
                $source->status = 'active';
                $source->data_quality_score = 85.0;
                $source->save();
            } elseif ($hasApiKey) {
                // Polling source - mark as active
                $source->status = 'active';
                $source->data_quality_score = 85.0;
                $source->save();
            }
        }
    }

    private function seedDefaultDataSources(): void
    {
        $sources = [
            [
                'type' => 'fec',
                'name' => 'FEC Political Finance',
                'status' => env('FEC_API_KEY') ? 'active' : 'inactive',
                'config' => [
                    'api_key' => env('FEC_API_KEY', ''),
                    'candidates' => [],
                    'date_range' => ['start' => '2023-01-01', 'end' => '2024-12-31'],
                ],
                'record_count' => 0,
                'data_quality_score' => env('FEC_API_KEY') ? 85.0 : 0,
            ],
            [
                'type' => 'fred',
                'name' => 'FRED Economic Data',
                'status' => env('FRED_API_KEY') ? 'active' : 'inactive',
                'config' => [
                    'api_key' => env('FRED_API_KEY', ''),
                    'indicators' => ['CPIAUCSL', 'UNRATE', 'GDP', 'UMCSENT'],
                ],
                'record_count' => 0,
                'data_quality_score' => env('FRED_API_KEY') ? 85.0 : 0,
            ],
            [
                'type' => 'polling',
                'name' => '538/RCP Polling',
                'status' => 'active', // Polling doesn't need API key
                'config' => [
                    'sources' => ['538', 'rcp'],
                    'poll_types' => ['presidential', 'senate'],
                ],
                'record_count' => 0,
                'data_quality_score' => 85.0,
            ],
        ];

        foreach ($sources as $source) {
            DataSource::create($source);
        }
    }
}
