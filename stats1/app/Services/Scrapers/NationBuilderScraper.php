<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class NationBuilderScraper extends BaseScraper
{
    protected int $requestsPerMinute = 3;

    /**
     * Public NationBuilder-powered campaign sites to monitor
     * These are campaigns that use NationBuilder and have public pages
     */
    protected array $campaignSites = [
        // Add known NationBuilder campaign sites here
        // Format: 'identifier' => 'https://campaign.nationbuilder.com'
    ];

    public function getIdentifier(): string
    {
        return 'nationbuilder';
    }

    public function getBaseUrl(): string
    {
        return 'https://nationbuilder.com';
    }

    /**
     * Scrape public campaign data from NationBuilder-powered sites
     */
    public function scrape(): array
    {
        $results = [
            'campaigns' => $this->scrapeCampaignDirectory(),
            'events' => [],
            'scraped_at' => now()->toIso8601String(),
        ];

        // Scrape each known campaign site
        foreach ($this->campaignSites as $id => $url) {
            $events = $this->scrapeCampaignEvents($url);
            if (!empty($events)) {
                $results['events'][$id] = $events;
            }
        }

        Log::info("[NationBuilder] Scraped campaign data");
        
        return $results;
    }

    /**
     * Scrape the NationBuilder customer directory for political campaigns
     */
    public function scrapeCampaignDirectory(): array
    {
        // NationBuilder showcases customers on their website
        $url = 'https://nationbuilder.com/customers';
        $html = $this->fetch($url);

        if (!$html) {
            return [];
        }

        return $this->extractCampaigns($html);
    }

    /**
     * Add a campaign site to monitor
     */
    public function addCampaignSite(string $identifier, string $url): void
    {
        $this->campaignSites[$identifier] = $url;
    }

    /**
     * Scrape events from a specific campaign site
     */
    public function scrapeCampaignEvents(string $siteUrl): array
    {
        $eventsUrl = rtrim($siteUrl, '/') . '/events';
        $html = $this->fetch($eventsUrl);

        if (!$html) {
            return [];
        }

        return $this->extractEvents($html);
    }

    /**
     * Scrape volunteer/signup pages from a campaign
     */
    public function scrapeCampaignVolunteer(string $siteUrl): array
    {
        $volunteerUrl = rtrim($siteUrl, '/') . '/volunteer';
        $html = $this->fetch($volunteerUrl);

        if (!$html) {
            return [];
        }

        return $this->extractVolunteerInfo($html);
    }

    /**
     * Extract campaigns from NationBuilder directory
     */
    protected function extractCampaigns(string $html): array
    {
        $crawler = $this->getCrawler($html);
        $campaigns = [];

        // Look for customer stories/cards
        // Adjusted for typical NationBuilder customer showcase structure (e.g., cards, articles)
        $crawler->filter('article.customer, div.customer-card, div.post-content')->each(function (Crawler $node) use (&$campaigns) {
            try {
                $name = '';
                $type = '';
                $url = '';

                // Extract name
                $nameNode = $node->filter('h2, h3, h4')->first();
                if ($nameNode->count() > 0) {
                    $name = $this->cleanText($nameNode->text());
                }

                // Extract URL
                $linkNode = $node->filter('a[href^="http"]')->first();
                if ($linkNode->count() > 0) {
                    $url = $linkNode->attr('href');
                }

                // Determine if it's political
                if ($this->isPoliticalCampaign($node->text())) {
                    $type = 'political';
                }

                if ($name && $type === 'political') {
                    $campaigns[] = [
                        'name' => $name,
                        'type' => $type,
                        'url' => $url,
                    ];
                }
            } catch (\Exception $e) {
                // Skip
            }
        });

        return array_slice($campaigns, 0, 30);
    }

    /**
     * Extract events from a campaign events page
     */
    protected function extractEvents(string $html): array
    {
        $crawler = $this->getCrawler($html);
        $events = [];

        // NationBuilder event pages typically have event cards in a .event or .page-results class
        $crawler->filter('div.event, li.event, div.page-excerpt')->each(function (Crawler $node) use (&$events) {
            try {
                $title = '';
                $date = '';
                $location = '';
                $link = '';

                // Extract title
                $titleNode = $node->filter('h3, h4, a.event-name')->first();
                if ($titleNode->count() > 0) {
                    $title = $this->cleanText($titleNode->text());
                    
                    // Extract link from title if possible
                    $a = $titleNode->nodeName() === 'a' ? $titleNode : $titleNode->filter('a')->first();
                    if ($a->count() > 0) {
                        $link = $a->attr('href');
                    }
                }

                // Extract date
                // Look for time tags or date-like text
                $dateNode = $node->filter('time');
                if ($dateNode->count() > 0) {
                    $date = $dateNode->attr('datetime') ?? $dateNode->text();
                } else {
                    // Fallback to regex on text
                    if (preg_match('/(\d{1,2}\/\d{1,2}\/\d{4}|\w+ \d{1,2}, \d{4})/', $node->text(), $dateMatch)) {
                        $date = $dateMatch[1];
                    }
                }

                // Extract location
                $locNode = $node->filter('address, div.event-location, span.address');
                if ($locNode->count() > 0) {
                    $location = $this->cleanText($locNode->text());
                }

                if ($title) {
                    $events[] = [
                        'title' => $title,
                        'date' => $date,
                        'location' => $location,
                        'url' => $link,
                    ];
                }
            } catch (\Exception $e) {
                // Skip
            }
        });

        return array_slice($events, 0, 20);
    }

    /**
     * Extract volunteer information
     */
    protected function extractVolunteerInfo(string $html): array
    {
        $crawler = $this->getCrawler($html);
        $info = [
            'has_volunteer_form' => false,
            'opportunities' => [],
        ];

        // Check for signup form
        if ($crawler->filter('form')->count() > 0) {
             $info['has_volunteer_form'] = true;
        }

        // Look for volunteer opportunities (list items in content)
        $crawler->filter('div.content li, div.page-content li')->each(function (Crawler $li) use (&$info) {
            $text = $this->cleanText($li->text());
            if (strlen($text) > 10 && strlen($text) < 200) {
                $info['opportunities'][] = $text;
            }
        });

        return $info;
    }

    /**
     * Check if content indicates a political campaign
     */
    protected function isPoliticalCampaign(string $text): bool
    {
        $keywords = [
            'campaign', 'candidate', 'election', 'vote', 'senator', 'congress',
            'governor', 'mayor', 'council', 'representative', 'democrat',
            'republican', 'progressive', 'conservative', 'ballot'
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
