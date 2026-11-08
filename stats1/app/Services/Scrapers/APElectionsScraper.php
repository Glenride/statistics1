<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class APElectionsScraper extends BaseScraper
{
    protected int $requestsPerMinute = 3;

    public function getIdentifier(): string
    {
        return 'ap_elections';
    }

    public function getBaseUrl(): string
    {
        return 'https://apnews.com';
    }

    /**
     * Scrape AP News election results and race calls
     */
    public function scrape(): array
    {
        $results = [
            'races' => $this->scrapeRaces(),
            'results' => $this->scrapeResults(),
            'scraped_at' => now()->toIso8601String(),
        ];

        Log::info("[AP Elections] Scraped " . count($results['races']) . " races");
        
        return $results;
    }

    /**
     * Scrape current election races
     */
    public function scrapeRaces(): array
    {
        $url = $this->getBaseUrl() . '/hub/2024-election';
        $html = $this->fetch($url);

        if (!$html) {
            return [];
        }

        return $this->extractRaceData($html);
    }

    /**
     * Scrape election results pages
     */
    public function scrapeResults(): array
    {
        $resultPages = [
            'presidential' => '/hub/2024-presidential-election-results',
            'senate' => '/hub/2024-senate-election-results',
            'house' => '/hub/2024-house-election-results',
            'governors' => '/hub/2024-governor-election-results',
        ];

        $results = [];
        foreach ($resultPages as $type => $path) {
            $html = $this->fetch($this->getBaseUrl() . $path);
            if ($html) {
                $results[$type] = $this->extractResultsData($html, $type);
            }
        }

        return $results;
    }

    /**
     * Extract race data from AP election hub using DomCrawler
     */
    protected function extractRaceData(string $html): array
    {
        $crawler = $this->getCrawler($html);
        $races = [];

        // Select specific article structure
        $crawler->filter('div.PageList-items-item')->each(function (Crawler $item) use (&$races) {
            try {
                // Headline
                $headlineNode = $item->filter('h3.PagePromo-title span.PagePromoContentIcons-text');
                $headline = $headlineNode->count() > 0 ? $this->cleanText($headlineNode->text()) : '';

                if (!$headline) {
                    $headlineNode = $item->filter('h3.PagePromo-title');
                    $headline = $headlineNode->count() > 0 ? $this->cleanText($headlineNode->text()) : '';
                }

                // Check if it's election-related
                if ($headline && $this->isElectionRelated($headline)) {
                    // Extract link
                    $linkNode = $item->filter('a.Link');
                    $link = $linkNode->count() > 0 ? $linkNode->attr('href') : '';

                    if ($link) {
                        $races[] = [
                            'headline' => $headline,
                            'url' => str_starts_with($link, 'http') ? $link : $this->getBaseUrl() . $link,
                            'scraped_at' => now()->toIso8601String(),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Skip
            }
        });

        return array_slice($races, 0, 20);
    }

    /**
     * Extract results data from results pages
     */
    protected function extractResultsData(string $html, string $type): array
    {
        $crawler = $this->getCrawler($html);
        $results = [];

        // Generic result card selector (adjust based on actual AP structure)
        $crawler->filter('div.RaceResults-row, div.ResultCard')->each(function (Crawler $card) use (&$results, $type) {
            try {
                $candidate = '';
                $party = '';
                $votes = 0;
                $percentage = 0.0;
                $called = false;

                // Candidate Name
                $nameNode = $card->filter('div.CandidateName, span.name');
                if ($nameNode->count() > 0) {
                    $candidate = $this->cleanText($nameNode->text());
                }

                // Party
                $partyNode = $card->filter('div.Party, span.party');
                if ($partyNode->count() > 0) {
                    $partyRaw = $this->cleanText($partyNode->text());
                     if (preg_match('/\b(D|R|I|L|G)\b/', $partyRaw, $partyMatch)) {
                        $party = $partyMatch[1];
                    }
                }

                // Votes
                $votesNode = $card->filter('div.Votes, span.votes');
                if ($votesNode->count() > 0) {
                    $voteText = $votesNode->text();
                    if (preg_match('/([0-9,]+)/', $voteText, $voteMatch)) {
                        $votes = (int) str_replace(',', '', $voteMatch[1]);
                    }
                }

                // Percentage
                $pctNode = $card->filter('div.Percentage, span.pct');
                if ($pctNode->count() > 0) {
                    $pctText = $pctNode->text();
                    if (preg_match('/([0-9.]+)%?/', $pctText, $pctMatch)) {
                        $percentage = (float) $pctMatch[1];
                    }
                }

                // Called
                if ($card->filter('svg.CheckIcon, div.WinnerIcon').count() > 0) {
                    $called = true;
                }

                if ($candidate) {
                    $results[] = [
                        'candidate' => $candidate,
                        'party' => $party,
                        'votes' => $votes,
                        'percentage' => $percentage,
                        'called' => $called,
                        'race_type' => $type,
                    ];
                }

            } catch (\Exception $e) {
                // Skip
            }
        });

        return $results;
    }

    /**
     * Check if headline is election-related
     */
    protected function isElectionRelated(string $text): bool
    {
        $keywords = [
            'election', 'vote', 'ballot', 'candidate', 'race', 'poll',
            'trump', 'biden', 'senate', 'house', 'governor', 'primary',
            'caucus', 'delegate', 'electoral', 'swing state', 'battleground'
        ];

        $text = strtolower($text);
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
