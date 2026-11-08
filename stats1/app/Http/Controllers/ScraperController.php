<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\DataSource;
use App\Services\AIDataProcessor;
use App\Services\Scrapers\RCPScraper;
use App\Services\Scrapers\PressReleaseScraper;
use App\Services\Scrapers\PDFHunterScraper;
use App\Services\Scrapers\APElectionsScraper;
use App\Services\Scrapers\BallotpediaScraper;
use App\Services\Scrapers\NationBuilderScraper;
use App\Services\Scrapers\CiceroScraper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScraperController extends Controller
{
    protected AIDataProcessor $aiProcessor;

    public function __construct(AIDataProcessor $aiProcessor)
    {
        $this->aiProcessor = $aiProcessor;
    }

    /**
     * Run a specific scraper
     */
    public function run(Request $request, string $type)
    {
        $scraper = $this->getScraper($type);

        if (!$scraper) {
            return response()->json([
                'success' => false,
                'error' => "Unknown scraper type: {$type}",
            ], 400);
        }

        try {
            Log::info("[ScraperController] Running {$type} scraper");

            // Get previous data for comparison
            $cacheKey = "scraper_last_data_{$type}";
            $previousData = Cache::get($cacheKey, []);

            // Run the scraper
            $rawData = $scraper->scrape();

            if (empty($rawData)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Scraper returned no data',
                ], 500);
            }

            // Process with AI
            $processedData = $this->aiProcessor->normalizeData($rawData, $type);

            // Generate alerts for changes
            $this->generateAlerts($type, $rawData, $previousData);

            // Cache the new data for next comparison
            Cache::put($cacheKey, $rawData, now()->addHours(24));

            // Update data source status
            $this->updateDataSource($type, count($rawData));

            return response()->json([
                'success' => true,
                'result' => [
                    'raw_items' => count($rawData),
                    'processed' => $processedData !== null,
                    'scraped_at' => now()->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("[ScraperController] {$type} scraper error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run all scrapers
     */
    public function runAll()
    {
        $results = [];
        $scraperTypes = [
            'rcp', 'press_monitor', 'pdf_hunter',
            'ap_elections', 'ballotpedia', 'nationbuilder', 'cicero'
        ];

        foreach ($scraperTypes as $type) {
            $result = $this->run(request(), $type);
            $results[$type] = json_decode($result->getContent(), true);
        }

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * Get status of all scrapers
     */
    public function status()
    {
        $scraperTypes = [
            'rcp', 'press_monitor', 'pdf_hunter',
            'ap_elections', 'ballotpedia', 'nationbuilder', 'cicero'
        ];
        $status = [];

        foreach ($scraperTypes as $type) {
            $cacheKey = "scraper_last_run_{$type}";
            $lastRun = Cache::get($cacheKey);

            $status[$type] = [
                'last_run' => $lastRun,
                'is_stale' => $lastRun ? now()->diffInHours($lastRun) > 6 : true,
            ];
        }

        return response()->json($status);
    }

    /**
     * Get the appropriate scraper instance
     */
    protected function getScraper(string $type)
    {
        return match($type) {
            'rcp' => new RCPScraper(),
            'press_monitor' => new PressReleaseScraper(),
            'pdf_hunter' => app(PDFHunterScraper::class),
            'ap_elections' => new APElectionsScraper(),
            'ballotpedia' => new BallotpediaScraper(),
            'nationbuilder' => new NationBuilderScraper(),
            'cicero' => new CiceroScraper(),
            default => null,
        };
    }

    /**
     * Generate alerts based on data changes
     */
    protected function generateAlerts(string $source, array $newData, array $previousData): void
    {
        // Use AI to detect significant changes
        $alertData = $this->aiProcessor->generateAlerts($newData, $previousData);

        if (!$alertData || empty($alertData['alerts'])) {
            return;
        }

        foreach ($alertData['alerts'] as $alert) {
            Alert::create([
                'type' => $alert['type'] ?? Alert::TYPE_NEW_POLL,
                'source' => $source,
                'severity' => $alert['severity'] ?? Alert::SEVERITY_INFO,
                'title' => $alert['title'] ?? 'New data detected',
                'description' => $alert['description'] ?? null,
                'data' => $alert['data'] ?? null,
            ]);

            Log::info("[ScraperController] Created alert: {$alert['title']}");
        }
    }

    /**
     * Update data source status after scrape
     */
    protected function updateDataSource(string $type, int $recordCount): void
    {
        DataSource::where('type', $type)->update([
            'status' => 'active',
            'last_sync_at' => now(),
            'record_count' => \DB::raw("record_count + {$recordCount}"),
        ]);

        // Cache last run time
        Cache::put("scraper_last_run_{$type}", now(), now()->addDays(7));
    }
}
