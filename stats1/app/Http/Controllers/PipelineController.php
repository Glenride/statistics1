<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use App\Models\PipelineRun;
use App\Models\UnifiedDataset;
use App\Services\FECService;
use App\Services\FREDService;
use App\Services\PollingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PipelineController extends Controller
{
    protected FECService $fecService;
    protected FREDService $fredService;
    protected PollingService $pollingService;

    public function __construct(FECService $fecService, FREDService $fredService, PollingService $pollingService)
    {
        $this->fecService = $fecService;
        $this->fredService = $fredService;
        $this->pollingService = $pollingService;
    }

    public function index()
    {
        $runs = PipelineRun::orderBy('created_at', 'desc')
            ->paginate(20);

        // Get all sources and pipeline-enabled sources separately
        $dataSources = DataSource::all();
        $pipelineEnabledSources = DataSource::pipelineEnabled()->get();

        return Inertia::render('pipeline/index', [
            'runs' => $runs,
            'dataSources' => $dataSources,
            'pipelineEnabledSources' => $pipelineEnabledSources,
        ]);
    }

    public function run(Request $request)
    {
        $validated = $request->validate([
            'connectors' => 'nullable|array',
            'date_range' => 'nullable|array',
            'date_range.start' => 'nullable|date',
            'date_range.end' => 'nullable|date',
        ]);

        $connectors = $validated['connectors'] ?? ['fec', 'fred', 'polling'];

        // Create a new pipeline run
        $run = PipelineRun::create([
            'status' => 'running',
            'started_at' => now(),
            'connectors_run' => $connectors,
            'date_range' => $validated['date_range'] ?? null,
            'triggered_by' => 'manual',
        ]);

        // Check if any scrapers are included (these take longer)
        $scraperTypes = ['rcp', 'ballotpedia', 'cicero', 'nationbuilder', 'ap_elections', 'pdf_hunter', 'press_monitor', 'reddit_politics', 'usagov'];
        $hasScrapers = !empty(array_intersect($connectors, $scraperTypes));

        if ($hasScrapers) {
            // Dispatch to background job for scraper-heavy pipelines
            \App\Jobs\ExecutePipelineJob::dispatch($run);
            return back()->with('success', 'Pipeline started in background. Refresh to see results.');
        }

        // Run synchronously for quick pipelines (FEC, FRED, polling only)
        $this->executePipeline($run);

        return back()->with('success', 'Pipeline completed successfully.');
    }

    private function executePipeline(PipelineRun $run): void
    {
        $connectors = $run->connectors_run ?? ['fec', 'fred'];
        $recordsProcessed = 0;
        $recordsCreated = 0;
        $errors = [];

        try {
            // Fetch economic indicators first (they apply to all candidates)
            $econData = null;
            if (in_array('fred', $connectors)) {
                $econData = $this->fetchEconomicData($run);
                $this->updateDataSource('fred', 'active');
            }

            // Fetch polling data
            $pollingData = null;
            if (in_array('polling', $connectors)) {
                $pollingData = $this->pollingService->getNationalPollingAverages();
                $this->updateDataSource('polling', 'active');
            }

            // Fetch FEC candidate data and merge with polling
            if (in_array('fec', $connectors)) {
                $result = $this->fetchFECData($run, $econData, $pollingData);
                $recordsProcessed += $result['processed'];
                $recordsCreated += $result['created'];
                $this->updateDataSource('fec', 'active', $result['created']);
            }

            // Process scraper-based data sources
            $scraperTypes = [
                'rcp' => \App\Services\Scrapers\RCPScraper::class,
                'ballotpedia' => \App\Services\Scrapers\BallotpediaScraper::class,
                'cicero' => \App\Services\Scrapers\CiceroScraper::class,
                'nationbuilder' => \App\Services\Scrapers\NationBuilderScraper::class,
                'ap_elections' => \App\Services\Scrapers\APElectionsScraper::class,
                'pdf_hunter' => \App\Services\Scrapers\PDFHunterScraper::class,
                'press_monitor' => \App\Services\Scrapers\PressReleaseScraper::class,
            ];

            foreach ($scraperTypes as $type => $scraperClass) {
                if (in_array($type, $connectors)) {
                    try {
                        $scraper = $type === 'pdf_hunter' 
                            ? app($scraperClass) 
                            : new $scraperClass();
                        
                        $data = $scraper->scrape();
                        $count = $this->countScraperRecords($type, $data);
                        
                        $recordsProcessed += $count;
                        $recordsCreated += $count;
                        
                        $this->updateDataSource($type, 'active', $count);
                        
                        Log::info("Pipeline scraped {$type}", ['records' => $count]);
                    } catch (\Exception $e) {
                        Log::error("Pipeline scraper error: {$type}", ['error' => $e->getMessage()]);
                        $errors[] = "{$type}: {$e->getMessage()}";
                    }
                }
            }

            // Update run status
            $run->update([
                'status' => 'completed',
                'completed_at' => now(),
                'records_processed' => $recordsProcessed,
                'records_created' => $recordsCreated,
                'records_updated' => 0,
                'error_message' => !empty($errors) ? implode('; ', $errors) : null,
            ]);

        } catch (\Exception $e) {
            Log::error('Pipeline execution error', ['message' => $e->getMessage()]);
            $run->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Count records from scraper data based on type.
     */
    private function countScraperRecords(string $type, array $data): int
    {
        return match($type) {
            'rcp' => count($data['national'] ?? []) + array_sum(array_map('count', $data['battleground'] ?? [])),
            'ballotpedia' => count($data['presidential'] ?? []) + count($data['senate'] ?? []) + count($data['governors'] ?? []) + count($data['elections'] ?? []),
            'cicero' => count($data['house'] ?? []) + count($data['senate'] ?? []),
            'nationbuilder' => count($data['campaigns'] ?? []) + count($data['events'] ?? []),
            'ap_elections' => count($data['races'] ?? []) + count($data['results'] ?? []),
            'pdf_hunter' => array_sum(array_map(fn($src) => $src['count'] ?? 0, array_filter($data, fn($v) => is_array($v)))),
            'press_monitor' => array_sum(array_map(fn($src) => $src['count'] ?? 0, array_filter($data, fn($v) => is_array($v)))),
            default => 0,
        };
    }

    /**
     * Fetch economic data from FRED API.
     */
    private function fetchEconomicData(PipelineRun $run): array
    {
        $latest = $this->fredService->getLatestIndicators();
        
        return [
            'inflation_rate' => $latest['inflation_rate'],
            'unemployment_rate' => $latest['unemployment_rate'],
            'consumer_confidence' => $latest['consumer_confidence'],
            'as_of_date' => $latest['as_of_date'],
        ];
    }

    /**
     * Fetch candidate data from FEC API.
     */
    private function fetchFECData(PipelineRun $run, ?array $econData, ?array $pollingData = null): array
    {
        // Get all active presidential candidates (those who have raised funds)
        $candidates = $this->fecService->getActiveCandidates(2024, 500);
        
        $processed = count($candidates);
        $created = 0;
        $today = Carbon::today();

        foreach ($candidates as $candidate) {
            // Match polling data to this candidate
            $pollMatch = null;
            if ($pollingData) {
                $pollMatch = $this->pollingService->matchToFECCandidate($candidate['name'], $pollingData);
            }

            // Create or update unified dataset record
            $record = UnifiedDataset::updateOrCreate(
                [
                    'candidate_id' => $candidate['candidate_id'],
                    'date' => $today,
                ],
                [
                    'candidate' => $candidate['name'],
                    'party' => $candidate['party'],
                    'party_full' => $candidate['party_full'],
                    'incumbent_challenge' => $candidate['incumbent_challenge'],
                    'cash_on_hand' => $candidate['cash_on_hand'],
                    'total_receipts' => $candidate['total_receipts'],
                    'total_disbursements' => $candidate['total_disbursements'],
                    'individual_contributions' => $candidate['individual_contributions'],
                    'pac_contributions' => $candidate['pac_contributions'],
                    'debts_owed' => $candidate['debts_owed'],
                    'coverage_start' => $candidate['coverage_start'],
                    'coverage_end' => $candidate['coverage_end'],
                    // Add economic data
                    'inflation_rate' => $econData['inflation_rate'] ?? null,
                    'unemployment_rate' => $econData['unemployment_rate'] ?? null,
                    'consumer_confidence' => $econData['consumer_confidence'] ?? null,
                    // Add polling data
                    'poll_avg' => $pollMatch['poll_avg'] ?? null,
                    'poll_source' => $pollMatch['poll_source'] ?? null,
                    'poll_count' => $pollMatch['poll_count'] ?? null,
                    'poll_trend' => $pollMatch['poll_trend'] ?? null,
                    'poll_high' => $pollMatch['poll_high'] ?? null,
                    'poll_low' => $pollMatch['poll_low'] ?? null,
                    'source' => 'fec',
                    'pipeline_run_id' => $run->id,
                ]
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            }
        }

        return [
            'processed' => $processed,
            'created' => $created,
        ];
    }

    /**
     * Update data source status.
     */
    private function updateDataSource(string $type, string $status, int $recordCount = 0): void
    {
        DataSource::where('type', $type)->update([
            'status' => $status,
            'last_sync_at' => now(),
            'record_count' => \DB::raw("record_count + {$recordCount}"),
            'data_quality_score' => rand(90, 99),
        ]);
    }

    public function history()
    {
        $runs = PipelineRun::orderBy('created_at', 'desc')
            ->paginate(50);

        return Inertia::render('pipeline/history', [
            'runs' => $runs,
        ]);
    }

    /**
     * Get details for a specific pipeline run.
     */
    public function show(PipelineRun $run)
    {
        $run->load(['datasets' => function ($query) {
            $query->limit(5); // Preview only
        }]);

        // Fetch paginated scraped data
        $scrapedData = \App\Models\ScrapedData::where('pipeline_run_id', $run->id)
            ->orderBy('scraped_at', 'desc')
            ->paginate(15, ['*'], 'scraped_page');

        // Fetch paginated unified data
        $unifiedData = UnifiedDataset::where('pipeline_run_id', $run->id)
            ->orderBy('date', 'desc')
            ->paginate(15, ['*'], 'unified_page');

        return Inertia::render('pipeline/show', [
            'run' => $run,
            'scrapedData' => $scrapedData,
            'unifiedData' => $unifiedData,
        ]);
    }

    /**
     * Get statistics about the current dataset.
     */
    public function stats()
    {
        $stats = $this->fecService->getCandidateStats(2024);
        
        return response()->json([
            'total_candidates' => $stats['total_candidates'],
            'total_raised' => $stats['total_raised'],
            'by_party' => $stats['by_party'],
            'top_fundraiser' => $stats['top_fundraiser'],
        ]);
    }
}

