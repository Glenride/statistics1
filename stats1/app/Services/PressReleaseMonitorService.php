<?php

namespace App\Services;

use App\Models\PressReleaseMonitor as PressReleaseMonitorModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PressReleaseMonitorService
{
    /**
     * Gold standard pollster sources to monitor.
     */
    public const SOURCES = [
        'nyt_siena' => [
            'name' => 'NYT/Siena',
            'url' => 'https://www.nytimes.com/section/upshot',
            'keywords' => ['poll', 'survey', 'election', 'voter'],
        ],
        'quinnipiac' => [
            'name' => 'Quinnipiac',
            'url' => 'https://poll.qu.edu/',
            'keywords' => ['poll', 'election', 'president', 'senate', 'governor'],
        ],
        'marist' => [
            'name' => 'Marist',
            'url' => 'https://maristpoll.marist.edu/',
            'keywords' => ['poll', 'national', 'election'],
        ],
        'monmouth' => [
            'name' => 'Monmouth',
            'url' => 'https://www.monmouth.edu/polling-institute/',
            'keywords' => ['poll', 'survey', 'election'],
        ],
    ];

    protected AlertService $alertService;

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    /**
     * Initialize monitors in the database.
     */
    public function initializeMonitors(): void
    {
        foreach (self::SOURCES as $key => $source) {
            PressReleaseMonitorModel::firstOrCreate(
                ['url' => $source['url']],
                [
                    'name' => $source['name'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Check all active monitors for changes.
     */
    public function checkForChanges(): array
    {
        $changes = [];
        $monitors = PressReleaseMonitorModel::active()->get();

        foreach ($monitors as $monitor) {
            try {
                $result = $this->checkMonitor($monitor);
                if ($result['changed']) {
                    $changes[] = $result;
                    
                    // Create an alert
                    $this->alertService->alertPageChange($monitor->name, $monitor->url);
                }
            } catch (\Exception $e) {
                Log::error('Monitor check failed', [
                    'monitor' => $monitor->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $changes;
    }

    /**
     * Check a single monitor for changes.
     */
    public function checkMonitor(PressReleaseMonitorModel $monitor): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            ])
            ->get($monitor->url);

        if (!$response->successful()) {
            Log::warning('Monitor fetch failed', [
                'monitor' => $monitor->name,
                'status' => $response->status(),
            ]);
            
            return [
                'monitor' => $monitor->name,
                'changed' => false,
                'error' => 'Fetch failed: ' . $response->status(),
            ];
        }

        $content = $response->body();
        $newHash = $this->hashPageContent($content);
        $changed = $monitor->hasChanged($newHash);

        // Check for political content if changed
        $hasPoliticalContent = false;
        if ($changed) {
            $sourceConfig = $this->getSourceConfig($monitor->url);
            $hasPoliticalContent = $this->filterPoliticalKeywords(
                $content,
                $sourceConfig['keywords'] ?? []
            );
        }

        // Update the monitor
        $monitor->updateHash($newHash, $changed);

        return [
            'monitor' => $monitor->name,
            'url' => $monitor->url,
            'changed' => $changed && $hasPoliticalContent,
            'has_political_content' => $hasPoliticalContent,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Hash page content (excluding dynamic timestamps, etc.).
     */
    public function hashPageContent(string $content): string
    {
        // Remove common dynamic elements before hashing
        $cleanContent = preg_replace([
            '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',  // ISO timestamps
            '/\d{1,2}:\d{2}\s*(AM|PM)/i',              // Time strings
            '/[a-f0-9]{32}/i',                          // MD5 hashes
            '/<script[^>]*>.*?<\/script>/si',          // Scripts
            '/<!--.*?-->/s',                            // Comments
        ], '', $content);

        return md5($cleanContent);
    }

    /**
     * Check if content contains political keywords.
     */
    public function filterPoliticalKeywords(string $content, array $keywords = []): bool
    {
        if (empty($keywords)) {
            $keywords = ['poll', 'election', 'president', 'senate', 'governor', 'campaign'];
        }

        $lowerContent = strtolower($content);

        foreach ($keywords as $keyword) {
            if (str_contains($lowerContent, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get source config by URL.
     */
    protected function getSourceConfig(string $url): array
    {
        foreach (self::SOURCES as $config) {
            if (str_contains($url, parse_url($config['url'], PHP_URL_HOST) ?? '')) {
                return $config;
            }
        }
        return [];
    }

    /**
     * Get monitor status for all sources.
     */
    public function getMonitorStatus(): array
    {
        $monitors = PressReleaseMonitorModel::all();

        return $monitors->map(function ($monitor) {
            return [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'url' => $monitor->url,
                'is_active' => $monitor->is_active,
                'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
                'last_changed_at' => $monitor->last_changed_at?->toIso8601String(),
                'has_hash' => !is_null($monitor->content_hash),
            ];
        })->toArray();
    }
}
