<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class PressReleaseScraper extends BaseScraper
{
    protected int $requestsPerMinute = 3;

    /**
     * Pollster press release pages to monitor
     */
    protected array $pollsters = [
        'nyt_siena' => [
            'name' => 'NYT/Siena',
            'url' => 'https://www.siena.edu/news-events/news/',
            'pattern' => '/poll|survey|election/i',
        ],
        'quinnipiac' => [
            'name' => 'Quinnipiac University',
            'url' => 'https://poll.qu.edu/',
            'pattern' => '/national|swing state|president/i',
        ],
        'marist' => [
            'name' => 'Marist College',
            'url' => 'https://maristpoll.marist.edu/latest-polls/',
            'pattern' => '/poll|survey/i',
        ],
        'monmouth' => [
            'name' => 'Monmouth University',
            'url' => 'https://www.monmouth.edu/polling-institute/reports/',
            'pattern' => '/national|president|election/i',
        ],
    ];

    public function getIdentifier(): string
    {
        return 'press_monitor';
    }

    public function getBaseUrl(): string
    {
        return 'https://poll.qu.edu'; // Primary source
    }

    /**
     * Scrape all pollster press release pages
     */
    public function scrape(): array
    {
        $results = [];

        foreach ($this->pollsters as $key => $pollster) {
            Log::info("[PressMonitor] Checking {$pollster['name']}");
            
            $html = $this->fetch($pollster['url']);
            if ($html) {
                $releases = $this->extractPressReleases($html, $pollster);
                $results[$key] = [
                    'pollster' => $pollster['name'],
                    'releases' => $releases,
                    'count' => count($releases),
                ];
            }
        }

        $results['scraped_at'] = now()->toIso8601String();
        
        return $results;
    }

    /**
     * Extract press releases from a pollster page
     */
    protected function extractPressReleases(string $html, array $pollster): array
    {
        $crawler = $this->getCrawler($html);
        $releases = [];

        $crawler->filter('a')->each(function (Crawler $node) use (&$releases, $pollster) {
            try {
                $title = $this->cleanText($node->text());
                $url = $node->attr('href');
                
                // Also check article parent context if link text is vague
                if (strlen($title) < 10) {
                     $parent = $node->closest('article, div.post');
                     if ($parent) {
                         $titleNode = $parent->filter('h2, h3, h4')->first();
                         if ($titleNode->count() > 0) {
                             $title = $this->cleanText($titleNode->text());
                         }
                     }
                }

                if ($url && $title && preg_match($pollster['pattern'], $title)) {
                    $releases[] = [
                        'title' => $title,
                        'url' => $this->resolveUrl($url, $pollster['url']),
                        'detected_at' => now()->toIso8601String(),
                    ];
                }
            } catch (\Exception $e) {
                // Skip
            }
        });

        // Deduplicate
        $unique = [];
        $seen = [];
        foreach ($releases as $r) {
            if (!in_array($r['url'], $seen)) {
                $seen[] = $r['url'];
                $unique[] = $r;
            }
        }

        return array_slice($unique, 0, 10);
    }

    /**
     * Resolve relative URLs to absolute
     */
    protected function resolveUrl(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        $parsed = parse_url($baseUrl);
        $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        if (str_starts_with($url, '/')) {
            return $base . $url;
        }

        return rtrim($baseUrl, '/') . '/' . $url;
    }

    /**
     * Check for new releases since last check
     */
    public function checkForNewReleases(array $lastKnownUrls = []): array
    {
        $allReleases = $this->scrape();
        $newReleases = [];

        foreach ($allReleases as $key => $data) {
            if ($key === 'scraped_at' || !isset($data['releases'])) continue;

            foreach ($data['releases'] as $release) {
                if (!in_array($release['url'], $lastKnownUrls)) {
                    $newReleases[] = array_merge($release, [
                        'pollster' => $data['pollster'],
                    ]);
                }
            }
        }

        return $newReleases;
    }
}
