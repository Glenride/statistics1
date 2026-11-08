<?php

namespace App\Console\Commands;

use App\Services\PollingService;
use App\Services\RCPService;
use App\Services\AlertService;
use Illuminate\Console\Command;

class SyncPolls extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'polls:sync {--source=all : Source to sync (538, rcp, all)}';

    /**
     * The console command description.
     */
    protected $description = 'Sync polling data from external sources to local database';

    public function __construct(
        protected PollingService $pollingService,
        protected RCPService $rcpService,
        protected AlertService $alertService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = $this->option('source');

        $this->info('🗳️  Starting poll sync...');

        $totalStats = [
            'new' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'errors' => 0,
        ];

        // Sync 538 polls
        if ($source === 'all' || $source === '538') {
            $this->info('  → Syncing FiveThirtyEight polls...');
            $stats = $this->pollingService->syncPolls();
            
            $this->line("    New: {$stats['new']}, Updated: {$stats['updated']}, Unchanged: {$stats['unchanged']}");
            
            foreach ($stats as $key => $value) {
                $totalStats[$key] += $value;
            }

            // Create alerts for new polls
            if ($stats['new'] > 0) {
                $this->alertService->createAlert(
                    'new_poll',
                    '538',
                    "{$stats['new']} new poll(s) synced from FiveThirtyEight",
                    null,
                    ['count' => $stats['new']]
                );
            }
        }

        // Sync RCP polls
        if ($source === 'all' || $source === 'rcp') {
            $this->info('  → Scraping RealClearPolitics polls...');
            
            try {
                $rcpPolls = $this->rcpService->scrapeLatestPolls();
                $rcpCount = count($rcpPolls);
                
                $this->line("    Found {$rcpCount} polls from RCP");
                
                // Cross-reference with 538 data
                if (!empty($rcpPolls)) {
                    $existingPolls = \App\Models\Poll::fromSource('538')->get()->toArray();
                    $verifiedPolls = $this->rcpService->crossReferenceWith538($rcpPolls, $existingPolls);
                    
                    $unverified = collect($verifiedPolls)->where('verified', false)->count();
                    if ($unverified > 0) {
                        $this->warn("    ⚠️  {$unverified} poll(s) not verified by 538");
                    }
                }
            } catch (\Exception $e) {
                $this->error("    Error: {$e->getMessage()}");
                $totalStats['errors']++;
            }
        }

        $this->newLine();
        $this->info('✅ Poll sync completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['New Polls', $totalStats['new']],
                ['Updated Polls', $totalStats['updated']],
                ['Unchanged', $totalStats['unchanged']],
                ['Errors', $totalStats['errors']],
            ]
        );

        return self::SUCCESS;
    }
}
