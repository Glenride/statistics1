<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class RedditPoliticsScraper extends BaseScraper
{
    protected int $requestsPerMinute = 5;

    public function getIdentifier(): string
    {
        return 'reddit_politics';
    }

    public function getBaseUrl(): string
    {
        return 'https://www.reddit.com';
    }

    public function scrape(): array
    {
        $url = $this->getBaseUrl() . '/r/politics/top/?t=day';
        
        // Reddit absolutely requires headless browser and careful headers/fingerprinting
        // Browsershot handles most of this
        $html = $this->fetchHeadless($url);

        if (!$html) {
            return [];
        }

        $results = [];
        $crawler = $this->getCrawler($html);

        // Select Reddit posts - class names are obfuscated usually, so we target semantic elements or unique attributes
        // shreddit-post is a web component used in new Reddit
        $crawler->filter('shreddit-post')->each(function (Crawler $node) use (&$results) {
            try {
                $title = $node->attr('post-title');
                $permalink = $node->attr('permalink');
                $author = $node->attr('author');
                $score = $node->attr('score');
                $commentCount = $node->attr('comment-count');
                
                if ($title && $permalink) {
                    $results[] = [
                        'title' => $title,
                        'url' => $this->getBaseUrl() . $permalink,
                        'author' => $author,
                        'score' => (int) $score,
                        'comments' => (int) $commentCount,
                        'type' => 'Reddit Post',
                        'scraped_at' => now()->toIso8601String(),
                    ];
                }
            } catch (\Exception $e) {
                // Skip
            }
        });

        // Fallback for old reddit or different layout if shreddit-post not found
        if (empty($results)) {
             $crawler->filter('div.Post')->each(function (Crawler $node) use (&$results) {
                 // Try parsing classic/shredded layout
             });
        }

        Log::info("[Reddit] Scraped " . count($results) . " top politics posts");

        return $results;
    }
}
