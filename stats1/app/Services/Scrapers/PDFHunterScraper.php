<?php

namespace App\Services\Scrapers;

use App\Services\AIDataProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;

class PDFHunterScraper extends BaseScraper
{
    protected int $requestsPerMinute = 3;

    /**
     * Academic poll sources to monitor for PDFs
     */
    protected array $sources = [
        'suffolk' => [
            'name' => 'Suffolk University',
            'url' => 'https://www.suffolk.edu/academics/research-at-suffolk/political-research-center/polls',
            'pattern' => '/\.pdf$/i',
        ],
        'emerson' => [
            'name' => 'Emerson College',
            'url' => 'https://emersoncollegepolling.com/category/polls/',
            'pattern' => '/\.pdf$/i',
        ],
    ];

    protected AIDataProcessor $aiProcessor;

    public function __construct(AIDataProcessor $aiProcessor)
    {
        $this->aiProcessor = $aiProcessor;
    }

    public function getIdentifier(): string
    {
        return 'pdf_hunter';
    }

    public function getBaseUrl(): string
    {
        return 'https://www.suffolk.edu';
    }

    /**
     * Scrape sources for PDF links
     */
    public function scrape(): array
    {
        $results = [];

        foreach ($this->sources as $key => $source) {
            Log::info("[PDFHunter] Scanning {$source['name']} for PDFs");
            
            $html = $this->fetch($source['url']);
            if ($html) {
                $pdfs = $this->extractPDFLinks($html, $source);
                $results[$key] = [
                    'source' => $source['name'],
                    'pdfs' => $pdfs,
                    'count' => count($pdfs),
                ];
            }
        }

        $results['scraped_at'] = now()->toIso8601String();
        
        return $results;
    }

    /**
     * Extract PDF links from page HTML
     */
    protected function extractPDFLinks(string $html, array $source): array
    {
        $crawler = $this->getCrawler($html);
        $pdfs = [];

        $crawler->filter('a')->each(function (Crawler $node) use (&$pdfs, $source) {
            try {
                $href = $node->attr('href');
                if ($href && preg_match($source['pattern'], $href)) {
                    $url = $this->resolveUrl($href, $source['url']);
                    $title = $this->cleanText($node->text());

                    // Check if it looks like a poll PDF
                    if ($this->isPollPDF($url, $title)) {
                        $pdfs[] = [
                            'title' => $title ?: $this->extractTitleFromUrl($url),
                            'url' => $url,
                            'detected_at' => now()->toIso8601String(),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Skip
            }
        });

        // Unique filter
        $pdfs = array_map("unserialize", array_unique(array_map("serialize", $pdfs)));
        
        return array_slice(array_values($pdfs), 0, 10);
    }

    /**
     * Check if a PDF looks like it contains poll data
     */
    protected function isPollPDF(string $url, string $title): bool
    {
        $pollKeywords = ['poll', 'survey', 'election', 'voter', 'approval', 'candidate', 'president', 'topline'];
        $text = strtolower($url . ' ' . $title);

        foreach ($pollKeywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract a title from the PDF URL
     */
    protected function extractTitleFromUrl(string $url): string
    {
        $filename = basename(parse_url($url, PHP_URL_PATH));
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $title = str_replace(['-', '_'], ' ', $title);
        return ucwords($title);
    }

    /**
     * Download and process a PDF using AI
     */
    public function processPDF(string $url): ?array
    {
        Log::info("[PDFHunter] Downloading and processing PDF: {$url}");

        try {
            // Download PDF
            $pdfContent = $this->fetch($url);
            if (!$pdfContent) {
                return null;
            }

            // Save temporarily
            $tempPath = 'temp/pdf_' . md5($url) . '.pdf';
            Storage::put($tempPath, $pdfContent);

            // Use AI to extract text and structure
            $extractedData = $this->aiProcessor->extractFromPDF(
                Storage::path($tempPath)
            );

            // Clean up
            Storage::delete($tempPath);

            return $extractedData;

        } catch (\Exception $e) {
            Log::error("[PDFHunter] PDF processing error: {$e->getMessage()}");
            return null;
        }
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
}
