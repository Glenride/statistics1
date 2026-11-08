<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PDFHunterService
{
    /**
     * Academic pollster sources that publish PDF reports.
     */
    public const SOURCES = [
        'suffolk' => [
            'name' => 'Suffolk University',
            'url' => 'https://www.suffolk.edu/academics/research-at-suffolk/political-research-center',
            'keywords' => ['poll', 'survey', 'election'],
        ],
        'emerson' => [
            'name' => 'Emerson College',
            'url' => 'https://emersoncollegepolling.com/',
            'keywords' => ['poll', 'national', 'primary'],
        ],
    ];

    protected AlertService $alertService;
    protected string $storagePath = 'poll_pdfs';

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    /**
     * Scan all sources for new PDFs.
     */
    public function scanForNewPDFs(): array
    {
        $newPDFs = [];

        foreach (self::SOURCES as $key => $source) {
            try {
                $pdfs = $this->scanSource($source);
                foreach ($pdfs as $pdf) {
                    if ($this->isNewPDF($pdf['url'])) {
                        $newPDFs[] = array_merge($pdf, ['source' => $source['name']]);
                        
                        // Create alert for new PDF
                        $this->alertService->alertNewPDF($source['name'], $pdf['url']);
                    }
                }
            } catch (\Exception $e) {
                Log::error('PDF scanner error', [
                    'source' => $source['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $newPDFs;
    }

    /**
     * Scan a single source for PDFs.
     */
    protected function scanSource(array $source): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            ])
            ->get($source['url']);

        if (!$response->successful()) {
            Log::warning('PDF source fetch failed', [
                'source' => $source['name'],
                'status' => $response->status(),
            ]);
            return [];
        }

        return $this->extractPDFLinks($response->body(), $source['url']);
    }

    /**
     * Extract PDF links from HTML content.
     */
    public function extractPDFLinks(string $html, string $baseUrl): array
    {
        $pdfs = [];

        // Find all href attributes ending in .pdf
        preg_match_all('/href=["\']([^"\']*\.pdf[^"\']*)["\']/', $html, $matches);

        foreach ($matches[1] ?? [] as $href) {
            $fullUrl = $this->resolveUrl($href, $baseUrl);
            
            // Extract title from link text or filename
            $title = $this->extractPDFTitle($href, $html);
            
            $pdfs[] = [
                'url' => $fullUrl,
                'title' => $title,
                'filename' => basename(parse_url($fullUrl, PHP_URL_PATH)),
            ];
        }

        return $pdfs;
    }

    /**
     * Resolve relative URL to absolute.
     */
    protected function resolveUrl(string $href, string $baseUrl): string
    {
        // Already absolute
        if (str_starts_with($href, 'http')) {
            return $href;
        }

        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';

        // Absolute path
        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$href}";
        }

        // Relative path
        $basePath = dirname($parsedBase['path'] ?? '/');
        return "{$scheme}://{$host}{$basePath}/{$href}";
    }

    /**
     * Extract PDF title from surrounding context.
     */
    protected function extractPDFTitle(string $href, string $html): string
    {
        // Try to find link text
        $pattern = '/<a[^>]*href=["\']' . preg_quote($href, '/') . '["\'][^>]*>([^<]+)</';
        if (preg_match($pattern, $html, $match)) {
            return trim(html_entity_decode($match[1]));
        }

        // Fall back to filename
        $filename = basename(parse_url($href, PHP_URL_PATH));
        return pathinfo($filename, PATHINFO_FILENAME);
    }

    /**
     * Check if we've already seen this PDF.
     */
    protected function isNewPDF(string $url): bool
    {
        $filename = $this->getStorageFilename($url);
        return !Storage::disk('local')->exists("{$this->storagePath}/logs/{$filename}.log");
    }

    /**
     * Download a PDF and save it locally.
     */
    public function downloadPDF(string $url): ?string
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::error('PDF download failed', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            $filename = $this->getStorageFilename($url);
            $path = "{$this->storagePath}/{$filename}";

            Storage::disk('local')->put($path, $response->body());

            // Log that we've downloaded this PDF
            Storage::disk('local')->put(
                "{$this->storagePath}/logs/{$filename}.log",
                json_encode(['url' => $url, 'downloaded_at' => now()->toIso8601String()])
            );

            Log::info('PDF downloaded', ['url' => $url, 'path' => $path]);

            return storage_path("app/{$path}");
        } catch (\Exception $e) {
            Log::error('PDF download error', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extract text from a PDF file using spatie/pdf-to-text (if available).
     */
    public function extractTextFromPDF(string $path): ?string
    {
        // Check if pdftotext is available (from poppler-utils)
        $pdfToTextPath = trim(shell_exec('which pdftotext') ?? '');
        
        if (empty($pdfToTextPath)) {
            Log::info('pdftotext not available, skipping text extraction');
            return null;
        }

        try {
            $outputFile = tempnam(sys_get_temp_dir(), 'pdf_text_');
            $command = escapeshellcmd($pdfToTextPath) . ' ' . 
                       escapeshellarg($path) . ' ' . 
                       escapeshellarg($outputFile);
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($outputFile)) {
                $text = file_get_contents($outputFile);
                unlink($outputFile);
                return $text;
            }
        } catch (\Exception $e) {
            Log::error('PDF text extraction failed', ['path' => $path, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Scan a PDF's text for political poll data.
     */
    public function scanPDFForPollData(string $text): array
    {
        $results = [];

        // Look for common poll patterns
        $patterns = [
            'leading' => '/([A-Za-z]+)\s+(?:is\s+)?lead(?:s|ing)\s+(?:by\s+)?(\d+)/i',
            'ahead' => '/([A-Za-z]+)\s+ahead\s+(?:by\s+)?(\d+)/i',
            'percentage' => '/([A-Za-z]+)\s*[:)]\s*(\d+(?:\.\d+)?)\s*%/i',
            'vs' => '/([A-Za-z]+)\s+(\d+)\s*(?:%|percent)?\s*(?:vs\.?|versus)\s*([A-Za-z]+)\s+(\d+)/i',
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $results[] = [
                        'pattern' => $name,
                        'match' => $match[0],
                        'data' => array_slice($match, 1),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Generate storage filename from URL.
     */
    protected function getStorageFilename(string $url): string
    {
        $parsed = parse_url($url);
        $filename = basename($parsed['path'] ?? 'unknown.pdf');
        $hash = substr(md5($url), 0, 8);
        return "{$hash}_{$filename}";
    }

    /**
     * Get list of downloaded PDFs.
     */
    public function getDownloadedPDFs(): array
    {
        $files = Storage::disk('local')->files($this->storagePath);
        
        return array_filter($files, function ($file) {
            return str_ends_with($file, '.pdf');
        });
    }
}
