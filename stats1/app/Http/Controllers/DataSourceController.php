<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use App\Services\PollingService;
use App\Services\RCPService;
use App\Services\PressReleaseMonitorService;
use App\Services\PDFHunterService;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class DataSourceController extends Controller
{
    public function index()
    {
        $dataSources = DataSource::all();

        // Ensure API keys are synced from env
        foreach ($dataSources as $source) {
            $this->syncEnvApiKey($source);
        }

        // Group sources by category
        $groupedSources = [
            'core' => DataSource::byCategory('core')->get(),
            'polling' => DataSource::byCategory('polling')->get(),
            'custom' => DataSource::custom()->get(),
        ];

        return Inertia::render('data-sources', [
            'dataSources' => DataSource::all(),
            'groupedSources' => $groupedSources,
        ]);
    }

    /**
     * Get status of polling-specific sources
     */
    protected function getPollingSourcesStatus(): array
    {
        return [
            [
                'id' => 'rcp',
                'name' => 'RealClearPolitics',
                'type' => 'rcp',
                'description' => 'Latest polls from RealClearPolitics with partisan detection',
                'status' => 'active',
                'last_sync_at' => \Illuminate\Support\Facades\Cache::get('rcp_last_sync'),
                'record_count' => \App\Models\Poll::fromSource('rcp')->count(),
            ],
            [
                'id' => 'press_monitor',
                'name' => 'Press Release Monitor',
                'type' => 'press_monitor',
                'description' => 'NYT/Siena, Quinnipiac, Marist, Monmouth page monitoring',
                'status' => 'active',
                'last_sync_at' => \Illuminate\Support\Facades\Cache::get('press_monitor_last_check'),
                'record_count' => \App\Models\PressReleaseMonitor::active()->count(),
            ],
            [
                'id' => 'pdf_hunter',
                'name' => 'PDF Hunter',
                'type' => 'pdf_hunter',
                'description' => 'Suffolk, Emerson academic poll PDF detection',
                'status' => 'active',
                'last_sync_at' => \Illuminate\Support\Facades\Cache::get('pdf_hunter_last_scan'),
                'record_count' => count(glob(storage_path('app/pdfs/*.pdf'))),
            ],
        ];
    }

    public function update(Request $request, DataSource $dataSource)
    {
        $validated = $request->validate([
            'config' => 'nullable|array',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if (isset($validated['config'])) {
            $dataSource->config = array_merge($dataSource->config ?? [], $validated['config']);
        }

        if (isset($validated['status'])) {
            $dataSource->status = $validated['status'];
        }

        $dataSource->save();

        return back()->with('success', 'Data source updated successfully.');
    }

    /**
     * Store a new custom data source
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50|unique:data_sources,type',
            'description' => 'nullable|string',
            'config' => 'nullable|array',
            'config.url' => 'nullable|url',
            'config.api_key' => 'nullable|string',
            'sync_handler' => 'nullable|string',
        ]);

        $dataSource = DataSource::create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => 'custom',
            'is_custom' => true,
            'pipeline_enabled' => true,
            'status' => 'inactive',
            'config' => $validated['config'] ?? [],
            'sync_handler' => $validated['sync_handler'] ?? null,
        ]);

        return back()->with('success', "Data source '{$dataSource->name}' created successfully.");
    }

    /**
     * Delete a custom data source
     */
    public function destroy(DataSource $dataSource)
    {
        if (!$dataSource->is_custom) {
            return back()->with('error', 'Cannot delete system data sources.');
        }

        $name = $dataSource->name;
        $dataSource->delete();

        return back()->with('success', "Data source '{$name}' deleted successfully.");
    }

    /**
     * Toggle pipeline_enabled for a data source
     */
    public function togglePipelineEnabled(DataSource $dataSource)
    {
        $dataSource->pipeline_enabled = !$dataSource->pipeline_enabled;
        $dataSource->save();

        $status = $dataSource->pipeline_enabled ? 'enabled' : 'disabled';
        return response()->json([
            'success' => true,
            'pipeline_enabled' => $dataSource->pipeline_enabled,
            'message' => "Pipeline {$status} for {$dataSource->name}",
        ]);
    }

    public function testConnection(DataSource $dataSource)
    {
        // First sync from env
        $this->syncEnvApiKey($dataSource);
        
        $apiKey = $dataSource->config['api_key'] ?? null;
        
        // For polling, no API key needed
        if ($dataSource->type === 'polling') {
            $dataSource->update([
                'status' => 'active',
                'last_error' => null,
                'data_quality_score' => 85.0,
            ]);
            return back()->with('success', 'Polling source ready - no API key required!');
        }

        if (empty($apiKey)) {
            $envVar = strtoupper($dataSource->type) . '_API_KEY';
            $dataSource->update([
                'status' => 'error',
                'last_error' => "No API key found. Set {$envVar} in your .env file.",
            ]);
            return back()->with('error', "No API key found. Set {$envVar} in your .env file.");
        }

        // Test actual API connection
        try {
            $success = false;
            $message = '';

            switch ($dataSource->type) {
                case 'fec':
                    $response = Http::timeout(10)->get('https://api.open.fec.gov/v1/candidates/', [
                        'api_key' => $apiKey,
                        'per_page' => 1,
                    ]);
                    $success = $response->successful();
                    $message = $success ? 'FEC API connection successful!' : 'FEC API error: ' . ($response->json()['message'] ?? 'Unknown error');
                    break;

                case 'fred':
                    $response = Http::timeout(10)->get('https://api.stlouisfed.org/fred/series', [
                        'api_key' => $apiKey,
                        'series_id' => 'GNPCA',
                        'file_type' => 'json',
                    ]);
                    $success = $response->successful();
                    $message = $success ? 'FRED API connection successful!' : 'FRED API error: ' . ($response->json()['error_message'] ?? 'Unknown error');
                    break;
            }

            if ($success) {
                $dataSource->update([
                    'status' => 'active',
                    'last_error' => null,
                    'data_quality_score' => 85.0,
                ]);
                return back()->with('success', $message);
            }

            $dataSource->update([
                'status' => 'error',
                'last_error' => $message,
            ]);
            return back()->with('error', $message);

        } catch (\Exception $e) {
            $dataSource->update([
                'status' => 'error',
                'last_error' => 'Connection failed: ' . $e->getMessage(),
            ]);
            return back()->with('error', 'Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Sync a specific polling source
     */
    public function syncPollingSource(Request $request, string $sourceType): JsonResponse
    {
        try {
            $result = [];

            switch ($sourceType) {
                case '538':
                case 'polling':
                    $pollingService = app(PollingService::class);
                    $result = $pollingService->syncPolls();
                    \Illuminate\Support\Facades\Cache::put('538_last_sync', now()->toIso8601String());
                    break;

                case 'rcp':
                    $scraper = new \App\Services\Scrapers\RCPScraper();
                    $data = $scraper->scrape();
                    $result = [
                        'national_polls' => count($data['national'] ?? []),
                        'battleground_states' => count($data['battleground'] ?? []),
                    ];
                    \Illuminate\Support\Facades\Cache::put('rcp_last_sync', now()->toIso8601String());
                    break;

                case 'press_monitor':
                    $scraper = new \App\Services\Scrapers\PressReleaseScraper();
                    $data = $scraper->scrape();
                    $totalReleases = 0;
                    foreach ($data as $key => $value) {
                        if (isset($value['count'])) {
                            $totalReleases += $value['count'];
                        }
                    }
                    $result = ['releases_found' => $totalReleases];
                    \Illuminate\Support\Facades\Cache::put('press_monitor_last_check', now()->toIso8601String());
                    break;

                case 'pdf_hunter':
                    $scraper = app(\App\Services\Scrapers\PDFHunterScraper::class);
                    $data = $scraper->scrape();
                    $totalPdfs = 0;
                    foreach ($data as $key => $value) {
                        if (isset($value['count'])) {
                            $totalPdfs += $value['count'];
                        }
                    }
                    $result = ['pdfs_found' => $totalPdfs];
                    \Illuminate\Support\Facades\Cache::put('pdf_hunter_last_scan', now()->toIso8601String());
                    break;

                case 'ap_elections':
                    $scraper = new \App\Services\Scrapers\APElectionsScraper();
                    $data = $scraper->scrape();
                    $result = [
                        'races' => count($data['races'] ?? []),
                        'results' => count($data['results'] ?? []),
                    ];
                    \Illuminate\Support\Facades\Cache::put('ap_elections_last_sync', now()->toIso8601String());
                    break;

                case 'ballotpedia':
                    $scraper = new \App\Services\Scrapers\BallotpediaScraper();
                    $data = $scraper->scrape();
                    $result = [
                        'presidential' => count($data['presidential'] ?? []),
                        'senate' => count($data['senate'] ?? []),
                        'governors' => count($data['governors'] ?? []),
                        'elections' => count($data['elections'] ?? []),
                    ];
                    \Illuminate\Support\Facades\Cache::put('ballotpedia_last_sync', now()->toIso8601String());
                    break;

                case 'nationbuilder':
                    $scraper = new \App\Services\Scrapers\NationBuilderScraper();
                    $data = $scraper->scrape();
                    $result = [
                        'campaigns' => count($data['campaigns'] ?? []),
                        'events' => count($data['events'] ?? []),
                    ];
                    \Illuminate\Support\Facades\Cache::put('nationbuilder_last_sync', now()->toIso8601String());
                    break;

                case 'cicero':
                    $scraper = new \App\Services\Scrapers\CiceroScraper();
                    $data = $scraper->scrape();
                    $result = [
                        'house_reps' => count($data['house'] ?? []),
                        'senators' => count($data['senate'] ?? []),
                    ];
                    \Illuminate\Support\Facades\Cache::put('cicero_last_sync', now()->toIso8601String());
                    break;

                default:
                    return response()->json(['error' => 'Unknown source type'], 400);
            }

            // Update data source status
            DataSource::where('type', $sourceType)->update([
                'status' => 'active',
                'last_sync_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'source' => $sourceType,
                'result' => $result,
                'synced_at' => now()->toIso8601String(),
            ]);

        } catch (\Exception $e) {
            // Update data source with error
            DataSource::where('type', $sourceType)->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync API key from environment variable
     */
    private function syncEnvApiKey(DataSource $dataSource): void
    {
        $envKey = null;

        switch ($dataSource->type) {
            case 'fec':
                $envKey = env('FEC_API_KEY');
                break;
            case 'fred':
                $envKey = env('FRED_API_KEY');
                break;
        }

        if ($envKey && !empty($envKey)) {
            $config = $dataSource->config ?? [];
            if (($config['api_key'] ?? '') !== $envKey) {
                $config['api_key'] = $envKey;
                $dataSource->config = $config;
                $dataSource->save();
            }
        }
    }
}

