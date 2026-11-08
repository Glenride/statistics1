<?php

namespace App\Jobs;

use App\Models\DataSource;
use App\Models\PipelineRun;
use App\Models\UnifiedDataset;
use App\Services\FECService;
use App\Services\FREDService;
use App\Services\PollingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecutePipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800; // 30 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct(
        public PipelineRun $run
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        FECService $fecService,
        FREDService $fredService,
        PollingService $pollingService
    ): void {
        $connectors = $this->run->connectors_run ?? ['fec', 'fred'];
        $recordsProcessed = 0;
        $recordsCreated = 0;
        $errors = [];

        try {
            // Fetch economic indicators first
            $econData = null;
            if (in_array('fred', $connectors)) {
                $econData = $this->fetchEconomicData($fredService);
                $this->updateDataSource('fred', 'active');
            }

            // Fetch polling data
            $pollingData = null;
            if (in_array('polling', $connectors)) {
                $pollingData = $pollingService->getNationalPollingAverages();
                $this->updateDataSource('polling', 'active');
            }

            // Fetch FEC candidate data
            if (in_array('fec', $connectors)) {
                $result = $this->fetchFECData($fecService, $pollingService, $econData, $pollingData);
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
                'usagov' => \App\Services\Scrapers\USAGovScraper::class,
                'reddit_politics' => \App\Services\Scrapers\RedditPoliticsScraper::class,
            ];

            foreach ($scraperTypes as $type => $scraperClass) {
                if (in_array($type, $connectors)) {
                    try {
                        $scraper = $type === 'pdf_hunter' 
                            ? app($scraperClass) 
                            : new $scraperClass();
                        
                        $data = $scraper->scrape();
                        
                        // Save to database
                        $count = $this->saveScrapedData($type, $data);
                        
                        $recordsProcessed += $count;
                        $recordsCreated += $count;
                        
                        $this->updateDataSource($type, 'active', $count);
                        
                        Log::info("Pipeline scraped {$type}", ['records' => $count]);
                    } catch (\Exception $e) {
                        Log::error("Pipeline scraper error: {$type}", ['error' => $e->getMessage()]);
                        $errors[] = "{$type}: " . substr($e->getMessage(), 0, 100);
                    }
                }
            }

            // Update run status
            $this->run->update([
                'status' => 'completed',
                'completed_at' => now(),
                'records_processed' => $recordsProcessed,
                'records_created' => $recordsCreated,
                'records_updated' => 0,
                'error_message' => !empty($errors) ? implode('; ', $errors) : null,
            ]);

            Log::info("Pipeline completed", [
                'run_id' => $this->run->id,
                'records' => $recordsCreated,
            ]);

        } catch (\Exception $e) {
            Log::error('Pipeline execution error', ['message' => $e->getMessage()]);
            $this->run->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Pipeline job failed', [
            'run_id' => $this->run->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->run->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $exception ? $exception->getMessage() : 'Job timed out or failed unexpectedly',
        ]);
    }

    private function fetchEconomicData(FREDService $fredService): array
    {
        $latest = $fredService->getLatestIndicators();
        
        return [
            'inflation_rate' => $latest['inflation_rate'],
            'unemployment_rate' => $latest['unemployment_rate'],
            'consumer_confidence' => $latest['consumer_confidence'],
            'as_of_date' => $latest['as_of_date'],
        ];
    }

    private function fetchFECData(
        FECService $fecService,
        PollingService $pollingService,
        ?array $econData,
        ?array $pollingData
    ): array {
        $candidates = $fecService->getActiveCandidates(2024, 500);
        
        $processed = count($candidates);
        $created = 0;
        $today = Carbon::today();

        foreach ($candidates as $candidate) {
            $pollMatch = null;
            if ($pollingData) {
                $pollMatch = $pollingService->matchToFECCandidate($candidate['name'], $pollingData);
            }

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
                    'inflation_rate' => $econData['inflation_rate'] ?? null,
                    'unemployment_rate' => $econData['unemployment_rate'] ?? null,
                    'consumer_confidence' => $econData['consumer_confidence'] ?? null,
                    'poll_avg' => $pollMatch['poll_avg'] ?? null,
                    'poll_source' => $pollMatch['poll_source'] ?? null,
                    'poll_count' => $pollMatch['poll_count'] ?? null,
                    'poll_trend' => $pollMatch['poll_trend'] ?? null,
                    'poll_high' => $pollMatch['poll_high'] ?? null,
                    'poll_low' => $pollMatch['poll_low'] ?? null,
                    'source' => 'fec',
                    'pipeline_run_id' => $this->run->id,
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

    private function saveScrapedData(string $source, array $data): int
    {
        $count = 0;
        $scrapedAt = $data['scraped_at'] ?? now();

        $saveItem = function($dataType, $item) use ($source, $scrapedAt, &$count) {
            $title = $item['title'] ?? $item['headline'] ?? $item['name'] ?? $item['details'] ?? null;
            
            $url = $item['url'] ?? $item['website'] ?? null;
            
            // Generate a simpler title if missing
            if (!$title) {
                if ($dataType === 'result' && isset($item['candidate'])) {
                    $title = "{$item['candidate']} ({$item['party']}) - " . ($item['race_type'] ?? 'Unknown');
                } elseif (isset($item['state'], $item['type'])) {
                    $title = "{$item['state']} {$item['type']}";
                }
            }

            // Create a unique fingerprint for deduplication
            $uniqueKey = [
                'source' => $source,
                'url' => $url ?: 'hash_' . md5($source . $dataType . $title . $data['scraped_at']), // Fallback if no URL
            ];

            // If URL is empty, we might want a better key, but for now this prevents EXACT duplicates if we run twice
            // Actually, best to use title + date if URL is missing
            if (!$url) {
                $uniqueKey = [
                    'source' => $source,
                    'title' => substr($title, 0, 255),
                    'data_type' => $dataType,
                ];
            }

            \App\Models\ScrapedData::updateOrCreate(
                $uniqueKey,
                [
                    'data_type' => $dataType,
                    'title' => $title ? substr($title, 0, 255) : 'Untitled',
                    'url' => $url,
                    'content' => $item,
                    'scraped_at' => $scrapedAt,
                    'pipeline_run_id' => $this->run->id,
                ]
            );
            $count++;
        };

        // Handle standardized structure
        switch ($source) {
            case 'rcp':
                foreach ($data['national'] ?? [] as $item) $saveItem('poll', $item);
                foreach ($data['battleground'] ?? [] as $state => $polls) {
                    foreach ($polls as $item) $saveItem('poll', array_merge($item, ['state' => $state]));
                }
                break;
            
            case 'ballotpedia':
                foreach ($data['elections'] ?? [] as $item) $saveItem('election', $item);
                foreach ($data['governors'] ?? [] as $item) $saveItem('governor', $item);
                // Simplify: just store these two primary types for now to avoid noise
                break;

            case 'cicero':
                foreach ($data['house'] ?? [] as $item) $saveItem('rep', $item);
                foreach ($data['senate'] ?? [] as $item) $saveItem('senator', $item);
                break;

            case 'nationbuilder':
                foreach ($data['campaigns'] ?? [] as $item) $saveItem('campaign', $item);
                foreach ($data['events'] ?? [] as $site => $events) {
                    foreach ($events as $item) $saveItem('event', array_merge($item, ['site_id' => $site]));
                }
                break;

            case 'ap_elections':
                foreach ($data['races'] ?? [] as $item) $saveItem('race', $item);
                foreach ($data['results'] ?? [] as $type => $results) {
                    foreach ($results as $item) $saveItem('result', $item);
                }
                break;

            case 'pdf_hunter':
                foreach ($data as $key => $sourceData) {
                    if (is_array($sourceData) && isset($sourceData['pdfs'])) {
                        foreach ($sourceData['pdfs'] as $item) $saveItem('pdf', $item);
                    }
                }
                break;

            case 'press_monitor':
                foreach ($data as $key => $sourceData) {
                    if (is_array($sourceData) && isset($sourceData['releases'])) {
                        foreach ($sourceData['releases'] as $item) $saveItem('press_release', $item);
                    }
                }
                break;

            case 'usagov':
                foreach ($data as $item) $saveItem('agency', $item);
                break;

            case 'reddit_politics':
                foreach ($data as $item) $saveItem('reddit_post', $item);
                break;
        }

        return $count;
    }

    private function updateDataSource(string $type, string $status, int $recordCount = 0): void
    {
        DataSource::where('type', $type)->update([
            'status' => $status,
            'last_sync_at' => now(),
            'record_count' => \DB::raw("record_count + {$recordCount}"),
        ]);
    }
}
