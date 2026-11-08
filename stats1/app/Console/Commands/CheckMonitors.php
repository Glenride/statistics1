<?php

namespace App\Console\Commands;

use App\Services\PressReleaseMonitorService;
use App\Services\PDFHunterService;
use Illuminate\Console\Command;

class CheckMonitors extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'monitors:check {--type=all : Type to check (press, pdf, all)}';

    /**
     * The console command description.
     */
    protected $description = 'Check press release monitors and PDF sources for new content';

    public function __construct(
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
        $type = $this->option('type');

        $this->info('🔍 Starting monitor checks...');

        // Initialize monitors if needed
        $this->monitorService->initializeMonitors();

        $changes = [];

        // Check press release monitors
        if ($type === 'all' || $type === 'press') {
            $this->info('  → Checking press release monitors...');
            
            try {
                $pressChanges = $this->monitorService->checkForChanges();
                
                if (empty($pressChanges)) {
                    $this->line('    No changes detected');
                } else {
                    foreach ($pressChanges as $change) {
                        $this->warn("    🔔 CHANGE: {$change['monitor']}");
                        $changes[] = $change;
                    }
                }
            } catch (\Exception $e) {
                $this->error("    Error: {$e->getMessage()}");
            }
        }

        // Check PDF sources
        if ($type === 'all' || $type === 'pdf') {
            $this->info('  → Scanning for new PDFs...');
            
            try {
                $newPDFs = $this->pdfService->scanForNewPDFs();
                
                if (empty($newPDFs)) {
                    $this->line('    No new PDFs detected');
                } else {
                    foreach ($newPDFs as $pdf) {
                        $this->warn("    📄 NEW PDF: {$pdf['title']} ({$pdf['source']})");
                        $changes[] = $pdf;
                    }
                }
            } catch (\Exception $e) {
                $this->error("    Error: {$e->getMessage()}");
            }
        }

        // Summary
        $this->newLine();
        
        if (empty($changes)) {
            $this->info('✅ Monitor check completed - no new content detected.');
        } else {
            $this->info("✅ Monitor check completed - " . count($changes) . " change(s) detected!");
            $this->warn('   Check the alerts dashboard for details.');
        }

        return self::SUCCESS;
    }
}
