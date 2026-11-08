<?php

namespace App\Services;

use App\Models\PipelineRun;
use App\Models\UnifiedDataset;
use OpenAI\Laravel\Facades\OpenAI;

class AIAnalysisService
{
    // Using GPT 5.2 - the newest OpenAI model (requires max_completion_tokens)
    protected string $model = 'gpt-5.2';

    /**
     * Analyze data based on user query with full dataset context.
     */
    public function analyzeData(string $query, ?array $filters = null): array
    {
        $context = $this->buildDataContext($filters);
        $systemPrompt = $this->buildSystemPrompt();

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "Here is the current dataset:\n\n{$context}\n\nUser question: {$query}"],
                ],
                'temperature' => 0.7,
                'max_completion_tokens' => 2000,
            ]);

            return [
                'success' => true,
                'response' => $response->choices[0]->message->content,
                'tokens_used' => $response->usage->totalTokens ?? null,
            ];
        } catch (\Exception $e) {
            \Log::error('AI Analysis Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate AI summary for a completed pipeline run.
     */
    /**
     * Generate AI summary for a completed pipeline run.
     */
    public function generatePipelineSummary(PipelineRun $run): array
    {
        // Get unified dataset records
        $unifiedData = UnifiedDataset::where('pipeline_run_id', $run->id)
            ->orderBy('date')
            ->get();

        // Get scraped data records
        $scrapedData = \App\Models\ScrapedData::where('pipeline_run_id', $run->id)
            ->orderBy('scraped_at', 'desc')
            ->limit(50) // Limit to avoid hitting token limits
            ->get();

        if ($unifiedData->isEmpty() && $scrapedData->isEmpty()) {
            return [
                'success' => false,
                'error' => 'No data found for this pipeline run.',
            ];
        }

        $promptParts = [];
        $promptParts[] = "Analyze this pipeline run data and provide insights:";
        $promptParts[] = "Records processed: {$run->records_processed}";
        $promptParts[] = "Records created: {$run->records_created}";
        $promptParts[] = "Connectors used: " . $this->formatConnectors($run->connectors_run);

        if ($unifiedData->isNotEmpty()) {
            $dataJson = $unifiedData->map(fn($row) => [
                'date' => $row->date->format('Y-m-d'),
                'candidate' => $row->candidate,
                'party' => $row->party,
                'poll_avg' => $row->poll_avg,
                'cash_on_hand' => $row->cash_on_hand,
            ])->toJson();
            $promptParts[] = "\nUnified Dataset Sample:\n{$dataJson}";
        }

        if ($scrapedData->isNotEmpty()) {
            $scrapedJson = $scrapedData->map(fn($row) => [
                'source' => $row->source,
                'type' => $row->data_type,
                'title' => $row->title,
                'date' => $row->scraped_at->format('Y-m-d H:i'),
            ])->toJson();
            $promptParts[] = "\nScraped Content Sample:\n{$scrapedJson}";
        }

        $promptParts[] = "\nPlease provide:";
        $promptParts[] = "1. A brief executive summary";
        $promptParts[] = "2. Key trends observed";
        $promptParts[] = "3. Notable correlations or new information found";
        $promptParts[] = "4. Data quality observations";
        
        $promptParts[] = "\nIMPORTANT: Use plain text only. Do NOT use markdown syntax like ### headers, ** bold **, or bullet symbols. Use simple numbered lists and line breaks for structure.";

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a political data analyst. Analyze the provided campaign finance, polling, and scraped election data.'],
                    ['role' => 'user', 'content' => implode("\n", $promptParts)],
                ],
                'temperature' => 0.5,
                'max_completion_tokens' => 1500,
            ]);

            return [
                'success' => true,
                'insights' => $response->choices[0]->message->content,
                'tokens_used' => $response->usage->totalTokens ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get suggested analysis questions based on available data.
     */
    public function getSuggestedQuestions(): array
    {
        $hasData = UnifiedDataset::exists();

        if (!$hasData) {
            return [
                'Run a pipeline first to generate data for analysis.',
            ];
        }

        $candidates = UnifiedDataset::distinct('candidate')->pluck('candidate')->filter()->values();

        $suggestions = [
            'What are the key trends in polling averages over time?',
            'Is there a correlation between inflation rate and candidate fundraising?',
            'Which candidate has the strongest financial position?',
            'How does consumer confidence relate to incumbent polling numbers?',
            'Compare the cash-on-hand differences between parties.',
            'What economic indicators best predict polling changes?',
        ];

        if ($candidates->isNotEmpty()) {
            $suggestions[] = "Analyze {$candidates->first()}'s campaign trajectory.";
        }

        return $suggestions;
    }

    /**
     * Build context from the unified dataset.
     * Optimized for large candidate datasets with smart aggregation.
     */
    protected function buildDataContext(?array $filters = null): string
    {
        $query = UnifiedDataset::orderBy('total_receipts', 'desc');

        if ($filters) {
            if (!empty($filters['start_date'])) {
                $query->where('date', '>=', $filters['start_date']);
            }
            if (!empty($filters['end_date'])) {
                $query->where('date', '<=', $filters['end_date']);
            }
            if (!empty($filters['candidate'])) {
                $query->where('candidate', 'like', '%' . $filters['candidate'] . '%');
            }
            if (!empty($filters['party'])) {
                $query->where('party', $filters['party']);
            }
        }

        // Get comprehensive statistics
        $stats = $this->getDataStatistics();

        // Get top candidates by fundraising (most relevant for analysis)
        $topCandidates = $query->clone()->limit(30)->get();

        // Get polling leaders (candidates with polling data)
        $pollingLeaders = UnifiedDataset::whereNotNull('poll_avg')
            ->where('poll_avg', '>', 0)
            ->orderBy('poll_avg', 'desc')
            ->limit(15)
            ->get();

        // Party breakdown
        $partyStats = $this->getPartyBreakdown();

        // Build top candidates table with polling data
        $topCandidatesTable = $topCandidates->map(fn($row) => sprintf(
            "%s | %s | \$%s raised | \$%s cash | Poll: %s%% | %s",
            $row->candidate ?? 'Unknown',
            $row->party_full ?? $row->party ?? 'N/A',
            number_format($row->total_receipts ?? 0, 0),
            number_format($row->cash_on_hand ?? 0, 0),
            $row->poll_avg ? number_format($row->poll_avg, 1) : 'N/A',
            $row->poll_trend ?? 'N/A'
        ))->join("\n");

        // Build polling leaders section
        $pollingSection = $pollingLeaders->isEmpty() 
            ? "No polling data available yet." 
            : $pollingLeaders->map(fn($row) => sprintf(
                "%s (%s): %.1f%% avg (range: %.1f-%.1f%%) | Trend: %s | %d polls | Source: %s",
                $row->candidate ?? 'Unknown',
                $row->party ?? 'N/A',
                $row->poll_avg ?? 0,
                $row->poll_low ?? 0,
                $row->poll_high ?? 0,
                $row->poll_trend ?? 'unknown',
                $row->poll_count ?? 0,
                $row->poll_source ?? 'unknown'
            ))->join("\n");

        return <<<CONTEXT
=== COMPLETE 2024 PRESIDENTIAL CANDIDATE DATASET ===
Total Active Candidates: {$stats['total_records']}
Total Money Raised: \${$stats['total_raised']}
Candidates with Polling Data: {$stats['candidates_with_polls']}
Date Range: {$stats['date_range']}

=== ECONOMIC INDICATORS ===
Inflation Rate: {$stats['avg_inflation']}%
Unemployment Rate: {$stats['avg_unemployment']}%
Consumer Confidence: {$stats['avg_confidence']}

=== POLLING LEADERS ===
{$pollingSection}

=== PARTY BREAKDOWN ===
{$partyStats}

=== TOP 30 CANDIDATES BY FUNDRAISING ===
Name | Party | Total Raised | Cash on Hand | Poll Avg | Trend
{$topCandidatesTable}

=== KEY ANALYSIS OPPORTUNITIES ===
1. Compare polling leaders vs fundraising leaders - are they the same?
2. Analyze correlation between cash on hand and polling averages
3. Examine how economic indicators might affect incumbent vs challenger performance
4. Look for candidates with strong fundraising but weak polls (or vice versa)

=== DATA SOURCES ===
- Financial data: FEC (Federal Election Commission)
- Economic data: FRED (St. Louis Federal Reserve)
- Polling data: FiveThirtyEight or simulated averages
CONTEXT;
    }

    /**
     * Calculate comprehensive dataset statistics.
     */
    protected function getDataStatistics(): array
    {
        $total = UnifiedDataset::count();

        if ($total === 0) {
            return [
                'total_records' => 0,
                'date_range' => 'No data',
                'candidates' => 'None',
                'parties' => 'None',
                'total_raised' => '0',
                'candidates_with_polls' => 0,
                'avg_poll' => 0,
                'avg_cash' => 0,
                'avg_inflation' => 0,
                'avg_unemployment' => 0,
                'avg_confidence' => 0,
            ];
        }

        $minDate = UnifiedDataset::min('date');
        $maxDate = UnifiedDataset::max('date');
        $uniqueCandidates = UnifiedDataset::distinct('candidate_id')->count('candidate_id');
        $totalRaised = UnifiedDataset::sum('total_receipts');
        $candidatesWithPolls = UnifiedDataset::whereNotNull('poll_avg')->where('poll_avg', '>', 0)->count();

        return [
            'total_records' => $total,
            'unique_candidates' => $uniqueCandidates,
            'date_range' => "{$minDate} to {$maxDate}",
            'total_raised' => number_format($totalRaised, 0),
            'candidates_with_polls' => $candidatesWithPolls,
            'avg_poll' => number_format(UnifiedDataset::avg('poll_avg') ?? 0, 1),
            'avg_cash' => number_format(UnifiedDataset::avg('cash_on_hand') ?? 0, 0),
            'avg_inflation' => number_format(UnifiedDataset::avg('inflation_rate') ?? 0, 1),
            'avg_unemployment' => number_format(UnifiedDataset::avg('unemployment_rate') ?? 0, 1),
            'avg_confidence' => number_format(UnifiedDataset::avg('consumer_confidence') ?? 0, 0),
        ];
    }

    /**
     * Get party breakdown for context.
     */
    protected function getPartyBreakdown(): string
    {
        $parties = UnifiedDataset::selectRaw('
                party_full,
                party,
                COUNT(*) as candidate_count,
                SUM(total_receipts) as total_raised,
                SUM(cash_on_hand) as total_cash
            ')
            ->groupBy('party', 'party_full')
            ->orderByDesc('total_raised')
            ->get();

        return $parties->map(fn($p) => sprintf(
            "%s (%s): %d candidates, \$%s raised, \$%s cash",
            $p->party_full ?? 'Unknown',
            $p->party ?? '?',
            $p->candidate_count,
            number_format($p->total_raised ?? 0, 0),
            number_format($p->total_cash ?? 0, 0)
        ))->join("\n");
    }

    /**
     * Build the system prompt for GPT.
     */
    protected function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are a senior political data analyst for PolitiMetrix, a platform that tracks political campaign finances, polling data, and economic indicators.

Your role:
- Analyze correlations between economic conditions and political outcomes
- Identify trends in campaign finance and polling data
- Provide actionable insights for understanding political dynamics
- Answer questions about the unified dataset with specific data points

Guidelines:
- Be concise and data-driven
- Reference specific numbers from the dataset when possible
- Highlight interesting correlations or anomalies
- Explain statistical concepts in accessible terms
- When uncertain, acknowledge limitations in the data

Your analysis should help users understand:
1. How economic factors (inflation, unemployment, consumer confidence) relate to political outcomes
2. Campaign financial health and spending patterns
3. Polling trends and momentum
4. Cross-party comparisons

FORMATTING: Use plain text only. Do NOT use markdown syntax like ### headers, ** bold **, or - bullet points. Use simple numbered lists (1. 2. 3.) and line breaks for structure. Write in clear paragraphs.
PROMPT;
    }

    /**
     * Format connectors array for display.
     */
    protected function formatConnectors(?array $connectors): string
    {
        if (empty($connectors)) {
            return 'None specified';
        }

        $labels = [
            'fec' => 'FEC (Campaign Finance)',
            'fred' => 'FRED (Economic Data)',
            'polling' => 'Polling Data',
        ];

        return collect($connectors)
            ->map(fn($c) => $labels[$c] ?? $c)
            ->join(', ');
    }
}
