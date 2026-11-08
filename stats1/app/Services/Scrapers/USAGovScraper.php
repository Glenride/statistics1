<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class USAGovScraper extends BaseScraper
{
    protected int $requestsPerMinute = 10;

    public function getIdentifier(): string
    {
        return 'usagov';
    }

    public function getBaseUrl(): string
    {
        return 'https://www.usa.gov';
    }

    public function scrape(): array
    {
        $url = $this->getBaseUrl() . '/agency-index';
        // Use headless fetch since government sites often have bot protection or complex rendering
        $html = $this->fetchHeadless($url);

        if (!$html) {
            return [];
        }

        $results = [];
        $crawler = $this->getCrawler($html);

        // This selector might need adjustment based on actual page structure
        // Assuming a list or table of agencies
        $crawler->filter('.usa-collection__item')->each(function (Crawler $node) use (&$results) {
            try {
                $link = $node->filter('a')->first();
                if ($link->count() > 0) {
                    $name = $this->cleanText($link->text());
                    $href = $link->attr('href');
                    $fullUrl = str_starts_with($href, 'http') ? $href : $this->getBaseUrl() . $href;

                    $results[] = [
                        'title' => $name,
                        'url' => $fullUrl,
                        'description' => $this->cleanText($node->text()), // Capture full text as description
                        'type' => 'Agency',
                        'scraped_at' => now()->toIso8601String(),
                    ];
                }
            } catch (\Exception $e) {
                // Skip malformed items
            }
        });

        Log::info("[USAGov] Scraped " . count($results) . " agencies");

        return $results;
    }
}
