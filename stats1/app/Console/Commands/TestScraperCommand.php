<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestScraperCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:test {scraper : The name of the scraper to test (e.g., rcp, ballotpedia)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test a specific scraper and view its output';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $scraperName = $this->argument('scraper');
        
        $this->info("Testing scraper: {$scraperName}");

        $scraperClass = $this->getScraperClass($scraperName);

        if (!$scraperClass) {
            $this->error("Scraper not found: {$scraperName}");
            return Command::FAILURE;
        }

        try {
            $this->line("Instantiating {$scraperClass}...");
            
            // Handle dependency injection for PDFHunter if needed, otherwise generic instantiation
            if ($scraperName === 'pdf_hunter') {
                $scraper = app($scraperClass);
            } else {
                $scraper = new $scraperClass();
            }

            $this->line("Starting scrape (this may take a moment)...");
            
            $startTime = microtime(true);
            $data = $scraper->scrape();
            $duration = round(microtime(true) - $startTime, 2);

            $this->info("Scrape completed in {$duration}s");
            
            $this->outputDataSummary($data);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Scraper failed: " . $e->getMessage());
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function getScraperClass(string $name): ?string
    {
        $map = [
            'rcp' => \App\Services\Scrapers\RCPScraper::class,
            'ballotpedia' => \App\Services\Scrapers\BallotpediaScraper::class,
            'cicero' => \App\Services\Scrapers\CiceroScraper::class,
            'nationbuilder' => \App\Services\Scrapers\NationBuilderScraper::class,
            'ap_elections' => \App\Services\Scrapers\APElectionsScraper::class,
            'pdf_hunter' => \App\Services\Scrapers\PDFHunterScraper::class,
            'press_monitor' => \App\Services\Scrapers\PressReleaseScraper::class,
        ];

        return $map[$name] ?? null;
    }

    private function outputDataSummary(array $data)
    {
        $this->info("Total keys in result: " . count($data));

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $count = count($value);
                $this->line("- [{$key}]: {$count} items");
                
                // Show first item as sample
                if ($count > 0) {
                    $firstKey = array_key_first($value);
                    $firstItem = $value[$firstKey];
                    $preview = is_array($firstItem) ? json_encode($firstItem, JSON_PRETTY_PRINT) : $firstItem;
                    // Truncate if too long
                    if (strlen($preview) > 500) {
                        $preview = substr($preview, 0, 500) . '...';
                    }
                    $this->comment("  Sample ({$firstKey}): {$preview}");
                }
            } else {
                $this->line("- [{$key}]: " . (is_string($value) ? $value : json_encode($value)));
            }
        }
    }
}
