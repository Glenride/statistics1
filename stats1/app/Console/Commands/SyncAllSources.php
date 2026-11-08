<?php

namespace App\Console\Commands;

use App\Services\PollingService;
use App\Services\RCPService;
use App\Services\PressReleaseMonitorService;
use App\Services\PDFHunterService;
use App\Services\Scrapers\APElectionsScraper;
use App\Services\Scrapers\BallotpediaScraper;
use App\Services\Scrapers\NationBuilderScraper;
use App\Services\Scrapers\CiceroScraper;
use App\Services\Scrapers\RCPScraper;
use App\Services\Scrapers\PressReleaseScraper;
use App\Services\Scrapers\PDFHunterScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncAllSources extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sources:sync-all 
                            {--dry-run : Show what would be synced without actually syncing}
                            {--skip-scrapers : Skip web scrapers, only sync API sources}';

    /**
     * The console command description.
     */
    protected $description = 'Sync all data sources that do not require API keys (scrapers)';

    public function __construct(
        protected PollingService $pollingService,
        protected RCPService $rcpService,
        protected PressReleaseMonitorService $monitorService,
        protected PDFHunterService $pdfService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $skipScrapers = $this->option('skip-scrapers');

        $this->info('🚀 Starting sync of all API-free data sources...');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No data will be synced');
            $this->newLine();
        }

        $results = [];

        // 1. FiveThirtyEight (538)
        $this->info('📊 [1/8] FiveThirtyEight Polling Data');
        if (!$isDryRun) {
            try {
                $stats = $this->pollingService->syncPolls();
                $results['538'] = [
                    'status' => 'success',
                    'new' => $stats['new'] ?? 0,
                    'updated' => $stats['updated'] ?? 0,
                ];
                $this->line("   ✓ New: {$stats['new']}, Updated: {$stats['updated']}");
                Cache::put('538_last_sync', now()->toIso8601String());
            } catch (\Exception $e) {
                $results['538'] = ['status' => 'error', 'error' => $e->getMessage()];
                $this->error("   ✗ Error: {$e->getMessage()}");
            }
        } else {
            $this->line('   → Would sync from GitHub: presidential_general_averages_2024.csv');
        }

        // 2. RealClearPolitics
        $this->info('📰 [2/8] RealClearPolitics');
        if (!$isDryRun && !$skipScrapers) {
            try {
                $scraper = new RCPScraper();
                $data = $scraper->scrape();
                $national = count($data['national'] ?? []);
                $battleground = 0;
                foreach ($data['battleground'] ?? [] as $state => $polls) {
                    $battleground += count($polls);
                }
                $results['rcp'] = [
                    'status' => 'success',
                    'national' => $national,
                    'battleground' => $battleground,
                ];
                $this->line("   ✓ National: {$national} polls, Battleground: {$battleground} state polls");
                Cache::put('rcp_last_sync', now()->toIso8601String());
            } catch (\Exception $e) {
                $results['rcp'] = ['status' => 'error', 'error' => $e->getMessage()];
                $this->error("   ✗ Error: {$e->getMessage()}");
            }
        } else {
            $this->line('   → Would scrape national and battleground state polls');
        }

        // 3. Press Release Monitor
        $this->info('📢 [3/8] Press Release Monitor');
        if (!$isDryRun && !$skipScrapers) {
            try {
                $scraper = new PressReleaseScraper();
                $data = $scraper->scrape();
                $totalReleases = 0;
                foreach ($data as $key => $value) {
                    if (isset($value['count'])) {
                        $totalReleases += $value['count'];
                    }
                }
                $results['press_monitor'] = [
                    'status' => 'success',
                    'releases' => $totalReleases,
                ];
                $this->line("   ✓ Found {$totalReleases} press releases across monitored pollsters");
                Cache::put('press_monitor_last_check', now()->toIso8601String());
            } catch (\Exception $e) {
                $results['press_monitor'] = ['status' => 'error', 'error' => $e->getMessage()];
                $this->error("   ✗ Error: {$e->getMessage()}");
            }
        } else {
            $this->line('   → Would check NYT/Siena, Quinnipiac, Marist, Monmouth');
        }

        // 4. PDF Hunter
        $this->info('📄 [4/8] PDF Hunter');
        if (!$isDryRun && !$skipScrapers) {
            try {
                $scraper = app(PDFHunterScraper::class);
                $data = $scraper->scrape();
                $totalPdfs = 0;
                foreach ($data as $key => $value) {
                    if (isset($value['count'])) {
                        $totalPdfs += $value['count'];
                    }
                }
                $results['pdf_hunter'] = [
                    'status' => 'success',
                    'pdfs' => $totalPdfs,
                ];
                $this->line("   ✓ Found {$totalPdfs} PDFs from academic pollsters");
                Cache::put('pdf_hunter_last_scan', now()->toIso8601String());
            } catch (\Exception $e) {
                $results['pdf_hunter'] = ['status' => 'error', 'error' => $e->getMessage()];
                $this->error("   ✗ Error: {$e->getMessage()}");
            }
        } else {
            $this->line('   → Would scan Suffolk, Emerson for new poll PDFs');
        }

        // 5. AP Elections
        $this->info('🗳️  [5/8] AP Elections');
        if (!$isDryRun && !$skipScrapers) {
            try {
                $scraper = new APElectionsScraper();
                $data = $scraper->scrape();
                $races = count($data['races'] ?? []);
                $results['ap_elections'] = [
                    'status' => 'success',
                    'races' => $races,
                ];
                $this->line("   ✓ Found {$races} election races/news items");
                Cache::put('ap_elections_last_sync', now()->toIso8601String());
            } catch (\Exception $e) {
                $results['ap_elections'] = ['status' => 'error', 'error' => $e->getMessage()];
                $this->error("   ✗ Error: {$e->getMessage()}");
            }
        } else {
            $this->line('   → Would scrape AP News election hub');
        }

        // 6. Ballotpedia
        $this->info('📚 [6/8] Ballotpedia');
        if (!$isDryRun && !$skipScrapers) {
            try {
                $scraper = new BallotpediaScraper();
                $data = $scraper->scrape();
                $total = count($data['presidential'] ?? []) + 
                        count($data['senate'] ?? []) + 
                        count($data['governors'] ?? []);
                $results['ballotpedia'] = [
                    'status' => 'success',
                    'items' => $total,
                ];
                $this->line("   ✓ Found {$total} candidates/races");
                Cache::put('ballotpedia_last_sync', now()->toIso8601String());
            } catch (\Exception $e) {
                $results['ballotpedia'] = ['status' => 'error', 'error' => $e->getMessage()];
                $this->error("   ✗ Error: {$e->getMessage()}");
            }
        } else {
            $this->line('   → Would scrape Ballotpedia candidate/race data');
        }

        // 7. NationBuilder
        $this->info('🏛️  [7/8] NationBuilder');
        if (!$isDryRun && !$skipScrapers) {
            try {
                $scraper = new NationBuilderScraper();
                $data = $scraper->scrape();
                $campaigns = count($data['campaigns'] ?? []);
                $results['nationbuilder'] = [
                    'status' => 'success',
                    'campaigns' => $campaigns,
                ];
                $this->line("   ✓ Found {$campaigns} campaign items");
                Cache::put('nationbuilder_last_sync', now()->toIso8601String());
            } catch (\Exception $e) {
                $results['nationbuilder'] = ['status' => 'error', 'error' => $e->getMessage()];
                $this->error("   ✗ Error: {$e->getMessage()}");
            }
        } else {
            $this->line('   → Would scrape NationBuilder public campaign data');
        }

        // 8. Cicero
        $this->info('🏢 [8/8] Cicero');
        if (!$isDryRun && !$skipScrapers) {
            try {
                $scraper = new CiceroScraper();
                $data = $scraper->scrape();
                $house = count($data['house'] ?? []);
                $senate = count($data['senate'] ?? []);
                $results['cicero'] = [
                    'status' => 'success',
                    'house' => $house,
                    'senate' => $senate,
                ];
                $this->line("   ✓ House: {$house}, Senate: {$senate}");
                Cache::put('cicero_last_sync', now()->toIso8601String());
            } catch (\Exception $e) {
                $results['cicero'] = ['status' => 'error', 'error' => $e->getMessage()];
                $this->error("   ✗ Error: {$e->getMessage()}");
            }
        } else {
            $this->line('   → Would scrape elected official data');
        }

        // Summary
        $this->newLine();
        $this->info('📋 Sync Summary');
        $this->table(
            ['Source', 'Status', 'Details'],
            collect($results)->map(function ($result, $source) {
                $status = $result['status'] === 'success' ? '✓ Success' : '✗ Error';
                $details = $result['status'] === 'success'
                    ? collect($result)->except('status')->map(fn($v, $k) => "{$k}: {$v}")->implode(', ')
                    : ($result['error'] ?? 'Unknown error');
                return [$source, $status, $details];
            })->toArray()
        );

        $successCount = collect($results)->where('status', 'success')->count();
        $errorCount = collect($results)->where('status', 'error')->count();

        $this->newLine();
        if ($errorCount === 0) {
            $this->info("✅ All {$successCount} sources synced successfully!");
        } else {
            $this->warn("⚠️  {$successCount} sources synced, {$errorCount} had errors.");
        }

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
