<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;

abstract class BaseScraper
{
    /**
     * Rate limiting: requests per minute
     */
    protected int $requestsPerMinute = 10;

    /**
     * Timeout in seconds for HTTP requests
     */
    protected int $timeout = 30;

    /**
     * Maximum wait time for rate limiting (in seconds)
     */
    protected int $maxRateLimitWait = 10;

    /**
     * User agent for requests
     */
    protected string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Cache duration in seconds
     */
    protected int $cacheDuration = 300; // 5 minutes

    /**
     * Get the scraper identifier
     */
    abstract public function getIdentifier(): string;

    /**
     * Get the base URL for this scraper
     */
    abstract public function getBaseUrl(): string;

    /**
     * Scrape and return structured data
     */
    abstract public function scrape(): array;

    /**
     * Make an HTTP request with rate limiting
     */
    protected function fetch(string $url): ?string
    {
        // Check rate limit
        $this->enforceRateLimit();

        // Check cache first
        $cacheKey = 'scraper_' . md5($url);
        if ($cached = Cache::get($cacheKey)) {
            Log::debug("[{$this->getIdentifier()}] Cache hit for {$url}");
            return $cached;
        }

        try {
            Log::info("[{$this->getIdentifier()}] Fetching {$url}");

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => $this->userAgent,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                    'Upgrade-Insecure-Requests' => '1',
                ])
                ->get($url);

            if ($response->successful()) {
                $content = $response->body();
                Cache::put($cacheKey, $content, $this->cacheDuration);
                return $content;
            }

            Log::warning("[{$this->getIdentifier()}] HTTP {$response->status()} for {$url}");
            return null;

        } catch (\Exception $e) {
            Log::error("[{$this->getIdentifier()}] Fetch error: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Make a headless browser request using Browsershot (Puppeteer)
     */
    protected function fetchHeadless(string $url): ?string
    {
        // Check rate limit
        $this->enforceRateLimit();

        // Check cache first
        $cacheKey = 'scraper_headless_' . md5($url);
        if ($cached = Cache::get($cacheKey)) {
            Log::debug("[{$this->getIdentifier()}] Headless cache hit for {$url}");
            return $cached;
        }

        try {
            Log::info("[{$this->getIdentifier()}] Fetching headless {$url}");

            $content = Browsershot::url($url)
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
                ->windowSize(1920, 1080)
                ->timeout($this->timeout)
                ->bodyHtml();

            if ($content) {
                Cache::put($cacheKey, $content, $this->cacheDuration);
                return $content;
            }

            Log::warning("[{$this->getIdentifier()}] Headless empty content for {$url}");
            return null;

        } catch (\Exception $e) {
            Log::error("[{$this->getIdentifier()}] Headless fetch error: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Get a DomCrawler instance for the given HTML or URL content
     */
    protected function getCrawler(string $html): Crawler
    {
        return new Crawler($html);
    }

    /**
     * Enforce rate limiting
     */
    protected function enforceRateLimit(): void
    {
        $key = 'rate_limit_' . $this->getIdentifier();
        $requests = Cache::get($key, []);
        $now = microtime(true);

        // Remove requests older than 1 minute
        $requests = array_filter($requests, fn($time) => $now - $time < 60);

        if (count($requests) >= $this->requestsPerMinute) {
            $waitTime = 60 - ($now - min($requests));
            
            // Cap wait time to prevent exceeding PHP timeout
            if ($waitTime > $this->maxRateLimitWait) {
                Log::warning("[{$this->getIdentifier()}] Rate limit wait too long ({$waitTime}s), capping to {$this->maxRateLimitWait}s");
                $waitTime = $this->maxRateLimitWait;
            }
            
            Log::info("[{$this->getIdentifier()}] Rate limit reached, waiting {$waitTime}s");
            usleep((int)($waitTime * 1000000));
        }

        $requests[] = $now;
        Cache::put($key, $requests, 120);
    }

    /**
     * Clean HTML text
     */
    protected function cleanText(?string $text): string
    {
        if ($text === null) return '';
        
        // Remove scripts and styles if passed raw HTML
        $text = strip_tags($text);

        // Normalize whitespace (replace multiple spaces/newlines with single space)
        $text = preg_replace('/\s+/', ' ', $text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        return trim($text);
    }
}
