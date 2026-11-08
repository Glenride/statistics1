<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class RCPScraper extends BaseScraper
{
    protected int $requestsPerMinute = 5;

    public function getIdentifier(): string
    {
        return 'rcp';
    }

    public function getBaseUrl(): string
    {
        return 'https://www.realclearpolitics.com';
    }

    /**
     * Scrape RealClearPolitics polling averages
     */
    public function scrape(): array
    {
        $results = [
            'national' => $this->scrapeNationalPolls(),
            'battleground' => $this->scrapeBattlegroundPolls(),
            'scraped_at' => now()->toIso8601String(),
        ];

        Log::info("[RCP] Scraped " . count($results['national']) . " national polls");
        
        return $results;
    }

    /**
     * Scrape national polling averages
     */
    public function scrapeNationalPolls(): array
    {
        $url = $this->getBaseUrl() . '/epolls/2024/president/us/general-election-trump-vs-biden-7947.html';
        $html = $this->fetchHeadless($url);

        if (!$html) {
            return [];
        }

        return $this->extractPollingData($html);
    }

    /**
     * Scrape battleground state polls
     */
    public function scrapeBattlegroundPolls(): array
    {
        $states = [
            'arizona' => '/epolls/2024/president/az/arizona-trump-vs-biden-7967.html',
            'georgia' => '/epolls/2024/president/ga/georgia-trump-vs-biden-7968.html',
            'michigan' => '/epolls/2024/president/mi/michigan-trump-vs-biden-7969.html',
            'nevada' => '/epolls/2024/president/nv/nevada-trump-vs-biden-7970.html',
            'pennsylvania' => '/epolls/2024/president/pa/pennsylvania-trump-vs-biden-7971.html',
            'wisconsin' => '/epolls/2024/president/wi/wisconsin-trump-vs-biden-7972.html',
        ];

        $results = [];
        foreach ($states as $state => $path) {
            $html = $this->fetchHeadless($this->getBaseUrl() . $path);
            if ($html) {
                $results[$state] = $this->extractPollingData($html);
            }
        }

        return $results;
    }

    /**
     * Extract polling data from RCP page HTML using DomCrawler
     */
    protected function extractPollingData(string $html): array
    {
        $crawler = $this->getCrawler($html);
        $polls = [];

        try {
            // Find the main polling table
            $table = $crawler->filter('table.data, table#polling-data-full')->first();
            
            if ($table->count() > 0) {
                $table->filter('tr')->each(function (Crawler $row, $i) use (&$polls) {
                    // Skip header row(s) or empty rows
                    if ($i < 1) return;
                    
                    $cells = $row->filter('td');
                    if ($cells->count() >= 5) {
                        try {
                            $pollster = $this->cleanText($cells->eq(0)->text());
                            // Skip RCP Average row in the list, we handle it separately
                            if (str_contains($pollster, 'RCP Average')) return;

                            $polls[] = [
                                'pollster' => $pollster,
                                'date' => $this->cleanText($cells->eq(1)->text()),
                                'sample' => $this->cleanText($cells->eq(2)->text()),
                                'margin_of_error' => $this->extractMoE($cells->eq(2)->text()),
                                'candidate_1' => $this->parsePercentage($cells->eq(3)->text()),
                                'candidate_2' => $this->parsePercentage($cells->eq(4)->text()),
                                'spread' => $this->cleanText($cells->eq(5)->text('')),
                            ];
                        } catch (\Exception $e) {
                            // Skip malformed rows
                        }
                    }
                });
            }

            // Extract RCP Average specifically
            $avgRow = $crawler->filter('tr.rcpAvg, tr.final')->first();
            if ($avgRow->count() > 0) {
                $cells = $avgRow->filter('td');
                if ($cells->count() >= 5) {
                    $polls['rcp_average'] = [
                        'candidate_1' => $this->parsePercentage($cells->eq(3)->text('0')),
                        'candidate_2' => $this->parsePercentage($cells->eq(4)->text('0')),
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error("[RCP] Parsing error: " . $e->getMessage());
        }

        return $polls;
    }

    /**
     * Parse percentage from cell text
     */
    protected function parsePercentage(string $text): ?float
    {
        if (preg_match('/([0-9.]+)/', $text, $match)) {
            return (float) $match[1];
        }
        return null;
    }

    /**
     * Extract margin of error from sample text
     */
    protected function extractMoE(string $text): ?float
    {
        if (preg_match('/±\s*([0-9.]+)/', $text, $match)) {
            return (float) $match[1];
        }
        return null;
    }

    /**
     * Get the RCP average spread value
     */
    public function getAverageSpread(): ?array
    {
        // For getAverageSpread, we can just fetch the national polls
        // The scraping method handles caching so this is efficient
        $national = $this->scrapeNationalPolls();
        
        if (isset($national['rcp_average'])) {
            return [
                'spread' => $national['rcp_average']['candidate_1'] - $national['rcp_average']['candidate_2'],
                'source' => 'RealClearPolitics',
                'scraped_at' => now()->toIso8601String(),
            ];
        }

        return null;
    }
}
