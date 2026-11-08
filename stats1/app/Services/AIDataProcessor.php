<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AIDataProcessor
{
    protected string $model = 'gpt-5-mini';
    protected int $maxTokens = 4096;
    protected float $temperature = 0.1; // Low temperature for structured data

    /**
     * Extract structured polling data from raw HTML content
     */
    public function extractPollingData(string $html, string $source): ?array
    {
        $prompt = <<<PROMPT
You are a data extraction specialist. Extract polling data from this HTML content from $source.

Return a JSON object with this exact structure:
{
  "polls": [
    {
      "pollster": "Name of polling organization",
      "date": "YYYY-MM-DD",
      "sample_size": 1000,
      "margin_of_error": 3.5,
      "candidates": [
        {"name": "Candidate Name", "party": "D/R/I", "percentage": 45.5}
      ],
      "methodology": "Phone/Online/Mixed",
      "population": "LV/RV/A"
    }
  ],
  "averages": {
    "leading_candidate": "Name",
    "lead_margin": 2.5
  },
  "extraction_confidence": 0.95
}

If you cannot find valid polling data, return {"polls": [], "extraction_confidence": 0}.

HTML Content:
$html
PROMPT;

        return $this->query($prompt);
    }

    /**
     * Extract data from PDF text content
     */
    public function extractFromPDF(string $pdfPath): ?array
    {
        // Extract text from PDF using pdftotext or similar
        $text = $this->extractPDFText($pdfPath);
        
        if (!$text) {
            return null;
        }

        $prompt = <<<PROMPT
You are a data extraction specialist. Extract polling data from this academic poll PDF content.

Return a JSON object with this exact structure:
{
  "poll_info": {
    "organization": "Polling organization name",
    "sponsor": "News org or sponsor if any",
    "field_dates": "Date range when poll was conducted",
    "release_date": "YYYY-MM-DD",
    "methodology": "How the poll was conducted"
  },
  "sample": {
    "size": 1000,
    "population": "Likely Voters/Registered Voters/Adults",
    "margin_of_error": 3.5,
    "weighting": "Description of weighting if mentioned"
  },
  "results": {
    "presidential": [
      {"candidate": "Name", "party": "D/R/I", "percentage": 45.5}
    ],
    "approval_ratings": [
      {"figure": "President Name", "approve": 42, "disapprove": 55}
    ],
    "issue_questions": [
      {"question": "Question text", "responses": {"option1": 50, "option2": 40}}
    ]
  },
  "topline_summary": "Brief summary of key findings",
  "extraction_confidence": 0.9
}

PDF Content:
$text
PROMPT;

        return $this->query($prompt);
    }

    /**
     * Normalize and structure scraped data
     */
    public function normalizeData(array $rawData, string $sourceType): ?array
    {
        $dataJson = json_encode($rawData, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
You are a data normalization specialist for political polling data.

Normalize this raw scraped data from source type "$sourceType" into our unified format.

Tasks:
1. Standardize candidate names (e.g., "Donald Trump" not "Trump, Donald")
2. Standardize party codes (DEM → D, REP → R, IND → I)
3. Convert dates to YYYY-MM-DD format
4. Ensure percentages are numbers, not strings
5. Flag any suspicious or inconsistent data

Return a JSON object:
{
  "normalized_data": [...],
  "transformations_applied": ["list of changes made"],
  "data_quality_score": 0.95,
  "warnings": ["any data quality issues found"]
}

Raw Data:
$dataJson
PROMPT;

        return $this->query($prompt);
    }

    /**
     * Detect entities (candidates, elections) in text
     */
    public function extractEntities(string $text): ?array
    {
        $prompt = <<<PROMPT
Extract political entities from this text related to US elections.

Return a JSON object:
{
  "candidates": [
    {"name": "Full Name", "party": "D/R/I", "office": "President/Senate/House", "state": "XX or National"}
  ],
  "elections": [
    {"type": "Presidential/Senate/House", "year": 2024, "state": "XX or National"}
  ],
  "organizations": ["polling orgs, news outlets, PACs mentioned"],
  "dates_mentioned": ["YYYY-MM-DD format"],
  "key_topics": ["economy", "immigration", "healthcare", etc]
}

Text:
$text
PROMPT;

        return $this->query($prompt);
    }

    /**
     * Generate alerts from new data
     */
    public function generateAlerts(array $newData, array $previousData): ?array
    {
        $newJson = json_encode($newData, JSON_PRETTY_PRINT);
        $prevJson = json_encode($previousData, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Compare this new polling data with previous data and identify significant changes that warrant alerts.

Generate alerts for:
1. New polls released (always alert)
2. Major polling shifts (>3 point movement)
3. Lead changes (candidate A was ahead, now B is ahead)
4. New pollsters entering
5. Unusual results (far outside consensus)

Return a JSON array of alerts:
{
  "alerts": [
    {
      "type": "new_poll|major_shift|lead_change|unusual_result",
      "severity": "info|warning|critical",
      "title": "Brief headline",
      "description": "1-2 sentence explanation",
      "data": {"relevant data points"}
    }
  ]
}

Previous Data:
$prevJson

New Data:
$newJson
PROMPT;

        return $this->query($prompt);
    }

    /**
     * Query the OpenAI API
     */
    protected function query(string $prompt): ?array
    {
        $apiKey = config('services.openai.api_key');

        if (!$apiKey) {
            Log::error('[AIDataProcessor] OpenAI API key not configured');
            return null;
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a data extraction and processing specialist. Always respond with valid JSON only, no markdown formatting or code blocks.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => $this->maxTokens,
                    'temperature' => $this->temperature,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                $parsed = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    Log::info('[AIDataProcessor] Successfully processed data');
                    return $parsed;
                }

                Log::warning('[AIDataProcessor] Failed to parse JSON response');
                return null;
            }

            Log::error('[AIDataProcessor] API error: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('[AIDataProcessor] Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract text from PDF file
     */
    protected function extractPDFText(string $pdfPath): ?string
    {
        // Try pdftotext first (requires poppler-utils)
        $output = [];
        $returnCode = 0;
        exec("pdftotext -layout '{$pdfPath}' -", $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            return implode("\n", $output);
        }

        // Fallback: Try to read with PHP if pdftotext not available
        Log::warning('[AIDataProcessor] pdftotext not available, PDF text extraction limited');
        
        // Basic PDF text extraction (very limited)
        $content = file_get_contents($pdfPath);
        if (preg_match_all('/\((.*?)\)/', $content, $matches)) {
            return implode(' ', $matches[1]);
        }

        return null;
    }

    /**
     * Aggregate multiple poll sources into a single view
     */
    public function aggregatePolls(array $sources): ?array
    {
        $sourcesJson = json_encode($sources, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Aggregate polling data from multiple sources into a unified analysis.

For each candidate:
1. Calculate weighted poll average (weight by recency and pollster rating if available)
2. Identify trend direction (improving/declining/stable)
3. Note pollster house effects if apparent

Return a JSON object:
{
  "candidates": [
    {
      "name": "Candidate Name",
      "party": "D/R/I",
      "average": 45.5,
      "trend": "improving|declining|stable",
      "trend_magnitude": 1.5,
      "poll_count": 10,
      "range": {"low": 42, "high": 48}
    }
  ],
  "race_status": "Toss-up|Lean D|Lean R|Likely D|Likely R|Safe D|Safe R",
  "margin": 2.5,
  "confidence": "High|Medium|Low",
  "last_updated": "ISO timestamp",
  "notes": "Any important context"
}

Source Data:
$sourcesJson
PROMPT;

        return $this->query($prompt);
    }
}
