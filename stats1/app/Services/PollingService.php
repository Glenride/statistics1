<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollingService
{
    // FiveThirtyEight GitHub raw URLs for polling data
    // Note: Original projects.fivethirtyeight.com URLs now redirect to ABC News
    // Using GitHub raw URLs instead which contain archived and current polling data
    protected string $pollingUrl = 'https://raw.githubusercontent.com/fivethirtyeight/data/master/polls/2024-averages/presidential_general_averages_2024-09-12_uncorrected.csv';
    protected string $averagesUrl = 'https://raw.githubusercontent.com/fivethirtyeight/data/master/polls/2024-averages/presidential_primary_averages_2024.csv';
    
    protected int $cacheMinutes = 60; // Cache for 1 hour

    /**
     * Get national polling averages for presidential candidates.
     */
    public function getNationalPollingAverages(?string $startDate = null, ?string $endDate = null): array
    {
        $cacheKey = "polling_averages_{$startDate}_{$endDate}";
        
        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($startDate, $endDate) {
            try {
                $response = Http::timeout(30)->get($this->pollingUrl);
                
                if (!$response->successful()) {
                    Log::warning('Failed to fetch FiveThirtyEight polling data', [
                        'status' => $response->status()
                    ]);
                    return []; // Return empty - no simulated data
                }
                
                $parsed = $this->parseCsvData($response->body(), $startDate, $endDate);
                
                // If parsing returns empty, log it
                if (empty($parsed)) {
                    Log::info('FiveThirtyEight CSV parsed but no matching data found');
                }
                
                return $parsed;
            } catch (\Exception $e) {
                Log::error('Polling service error: ' . $e->getMessage());
                return []; // Return empty - no simulated data
            }
        });
    }

    /**
     * Parse CSV polling data from FiveThirtyEight.
     */
    protected function parseCsvData(string $csvContent, ?string $startDate, ?string $endDate): array
    {
        $lines = explode("\n", $csvContent);
        if (count($lines) < 2) {
            return $this->getSimulatedPollingData();
        }

        // Parse header - filter out any non-string values to avoid array_flip error
        $header = str_getcsv(array_shift($lines));
        $header = array_filter($header, fn($val) => is_string($val) && $val !== '');
        $headerMap = array_flip($header);

        $polls = [];
        $candidatePolls = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $row = str_getcsv($line);
            if (count($row) < count($header)) continue;

            // Extract relevant fields - handle multiple CSV formats
            // Old format: candidate_name, pct, end_date
            // New format: candidate, date, pct_estimate (or pct_trend_adjusted)
            $candidate = $row[$headerMap['candidate'] ?? $headerMap['candidate_name'] ?? $headerMap['answer'] ?? 0] ?? null;
            $pct = $row[$headerMap['pct_estimate'] ?? $headerMap['pct_trend_adjusted'] ?? $headerMap['pct'] ?? $headerMap['value'] ?? 1] ?? null;
            $date = $row[$headerMap['date'] ?? $headerMap['end_date'] ?? $headerMap['modeldate'] ?? 2] ?? null;
            $state = $row[$headerMap['state'] ?? 99] ?? '';
            $isNational = $state === '' || strtolower($state) === 'national';

            if (!$candidate || !$pct) continue;
            
            // Skip non-national polls unless that's all we have
            if (!$isNational && !empty($candidatePolls)) continue;

            // Filter by date range if specified
            if ($startDate && $date && $date < $startDate) continue;
            if ($endDate && $date && $date > $endDate) continue;

            // Aggregate polls by candidate
            $candidateKey = $this->normalizeCandidateName($candidate);
            if (!isset($candidatePolls[$candidateKey])) {
                $candidatePolls[$candidateKey] = [
                    'name' => $candidate,
                    'polls' => [],
                    'total' => 0,
                    'count' => 0,
                ];
            }
            
            $candidatePolls[$candidateKey]['polls'][] = (float) $pct;
            $candidatePolls[$candidateKey]['total'] += (float) $pct;
            $candidatePolls[$candidateKey]['count']++;
        }

        // Calculate averages
        $result = [];
        foreach ($candidatePolls as $key => $data) {
            if ($data['count'] === 0) continue;
            
            $polls = $data['polls'];
            sort($polls);
            
            $result[$key] = [
                'candidate' => $data['name'],
                'poll_avg' => round($data['total'] / $data['count'], 1),
                'poll_count' => $data['count'],
                'poll_high' => round(max($polls), 1),
                'poll_low' => round(min($polls), 1),
                'poll_trend' => $this->calculateTrend($polls),
                'poll_source' => '538',
            ];
        }

        // Sort by poll average descending
        uasort($result, fn($a, $b) => $b['poll_avg'] <=> $a['poll_avg']);

        return $result;
    }

    /**
     * Calculate polling trend based on recent polls.
     */
    protected function calculateTrend(array $polls): string
    {
        if (count($polls) < 3) return 'stable';
        
        $recent = array_slice($polls, -5);
        $older = array_slice($polls, 0, min(5, count($polls) - 5));
        
        if (empty($older)) return 'stable';
        
        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = array_sum($older) / count($older);
        
        $diff = $recentAvg - $olderAvg;
        
        if ($diff > 1.5) return 'up';
        if ($diff < -1.5) return 'down';
        return 'stable';
    }

    /**
     * Normalize candidate name for matching with FEC data.
     */
    public function normalizeCandidateName(string $name): string
    {
        // Convert to lowercase, remove titles, standardize format
        $name = strtolower(trim($name));
        $name = preg_replace('/^(mr\.|mrs\.|ms\.|dr\.|sen\.|rep\.)\s*/i', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        
        return $name;
    }

    /**
     * Match polling data to FEC candidates by name.
     */
    public function matchToFECCandidate(string $fecName, array $pollingData): ?array
    {
        $normalizedFec = $this->normalizeCandidateName($fecName);
        
        // Direct match
        if (isset($pollingData[$normalizedFec])) {
            return $pollingData[$normalizedFec];
        }

        // Try last name match
        $fecParts = explode(' ', $normalizedFec);
        $fecLastName = end($fecParts);

        foreach ($pollingData as $key => $data) {
            $pollParts = explode(' ', $key);
            $pollLastName = end($pollParts);
            
            if ($fecLastName === $pollLastName && strlen($fecLastName) > 3) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Get simulated polling data when live data is unavailable.
     * Based on realistic 2024 primary patterns and FEC fundraising correlations.
     */
    public function getSimulatedPollingData(): array
    {
        // Major 2024 presidential candidates with realistic polling ranges
        $candidates = [
            'donald trump' => ['base' => 52, 'variance' => 8],
            'joe biden' => ['base' => 45, 'variance' => 6],
            'nikki haley' => ['base' => 12, 'variance' => 5],
            'ron desantis' => ['base' => 15, 'variance' => 7],
            'vivek ramaswamy' => ['base' => 6, 'variance' => 3],
            'chris christie' => ['base' => 4, 'variance' => 2],
            'robert kennedy' => ['base' => 8, 'variance' => 4],
            'marianne williamson' => ['base' => 2, 'variance' => 2],
            'dean phillips' => ['base' => 3, 'variance' => 2],
        ];

        $result = [];
        foreach ($candidates as $name => $config) {
            $variance = rand(-$config['variance'], $config['variance']);
            $avg = $config['base'] + ($variance / 2);
            
            $result[$name] = [
                'candidate' => ucwords($name),
                'poll_avg' => round($avg, 1),
                'poll_count' => rand(15, 45),
                'poll_high' => round($avg + abs($variance), 1),
                'poll_low' => round($avg - abs($variance), 1),
                'poll_trend' => ['up', 'down', 'stable'][rand(0, 2)],
                'poll_source' => 'simulated',
            ];
        }

        uasort($result, fn($a, $b) => $b['poll_avg'] <=> $a['poll_avg']);
        
        return $result;
    }

    /**
     * Get polling data for a specific candidate.
     */
    public function getCandidatePolling(string $candidateName): ?array
    {
        $allPolls = $this->getNationalPollingAverages();
        return $this->matchToFECCandidate($candidateName, $allPolls);
    }

    /**
     * Clear the polling data cache.
     */
    public function clearCache(): void
    {
        Cache::forget('polling_averages__');
    }

    /**
     * Sync polls from FiveThirtyEight to local database.
     * This detects new and updated polls.
     */
    public function syncPolls(): array
    {
        $stats = [
            'new' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'errors' => 0,
        ];

        try {
            $response = Http::timeout(30)->get($this->pollingUrl);
            
            if (!$response->successful()) {
                Log::warning('Failed to fetch polling data for sync');
                return $stats;
            }

            $polls = $this->parseCsvForSync($response->body());
            
            foreach ($polls as $pollData) {
                try {
                    $result = $this->storePoll($pollData);
                    $stats[$result]++;
                } catch (\Exception $e) {
                    Log::error('Error storing poll', [
                        'poll_id' => $pollData['poll_id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $stats['errors']++;
                }
            }
        } catch (\Exception $e) {
            Log::error('Poll sync failed', ['error' => $e->getMessage()]);
        }

        Log::info('Poll sync completed', $stats);
        return $stats;
    }

    /**
     * Parse CSV specifically for poll sync (individual poll records).
     * Handles both old format (with poll_id) and new averages format (without poll_id).
     */
    protected function parseCsvForSync(string $csvContent): array
    {
        $lines = explode("\n", $csvContent);
        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_filter($header, fn($val) => is_string($val) && $val !== '');
        $headerMap = array_flip($header);

        $polls = [];
        $hasPolId = isset($headerMap['poll_id']);

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $row = str_getcsv($line);
            if (count($row) < count($header)) continue;

            // Get candidate and percentage - handle multiple column name formats
            $candidate = $row[$headerMap['candidate'] ?? $headerMap['candidate_name'] ?? $headerMap['answer'] ?? 0] ?? null;
            $pct = (float) ($row[$headerMap['pct_estimate'] ?? $headerMap['pct_trend_adjusted'] ?? $headerMap['pct'] ?? 1] ?? 0);
            $date = $row[$headerMap['date'] ?? $headerMap['end_date'] ?? 2] ?? null;
            $state = $row[$headerMap['state'] ?? 99] ?? null;
            $party = $row[$headerMap['party'] ?? 99] ?? null;

            if (!$candidate || $pct === 0.0) continue;

            // Generate poll ID - use existing or create synthetic one
            if ($hasPolId) {
                $pollId = $row[$headerMap['poll_id']] ?? null;
                if (!$pollId) continue;
            } else {
                // For averages format, create synthetic poll ID based on date + state
                $pollId = '538_avg_' . md5(($date ?? 'unknown') . '_' . ($state ?? 'national'));
            }

            // Build candidate data array
            $candidateData = [
                'name' => $candidate,
                'percentage' => $pct,
                'party' => $party,
            ];

            // Group by poll_id
            if (!isset($polls[$pollId])) {
                $polls[$pollId] = [
                    'poll_id' => $pollId,
                    'source' => '538',
                    'pollster' => $hasPolId 
                        ? ($row[$headerMap['pollster'] ?? 99] ?? 'FiveThirtyEight')
                        : 'FiveThirtyEight Average',
                    'race_type' => $this->determineRaceType($row, $headerMap),
                    'state' => $this->normalizeState($state),
                    'start_date' => $hasPolId ? ($row[$headerMap['start_date'] ?? 99] ?? null) : $date,
                    'end_date' => $date,
                    'sample_size' => $hasPolId ? ((int) ($row[$headerMap['sample_size'] ?? 99] ?? 0) ?: null) : null,
                    'fte_grade' => $hasPolId ? ($row[$headerMap['fte_grade'] ?? $headerMap['grade'] ?? 99] ?? null) : 'A+',
                    'candidates_data' => [],
                    'content_hash' => null,
                ];
            }

            $polls[$pollId]['candidates_data'][] = $candidateData;
        }

        // Generate content hashes for change detection
        foreach ($polls as $pollId => &$poll) {
            $poll['content_hash'] = md5(json_encode($poll['candidates_data']));
        }

        Log::info('Parsed ' . count($polls) . ' polls from CSV');

        return array_values($polls);
    }

    /**
     * Store or update a poll in the database.
     */
    protected function storePoll(array $pollData): string
    {
        $existing = \App\Models\Poll::where('poll_id', $pollData['poll_id'])->first();

        if (!$existing) {
            // New poll - assign trust score based on pollster
            $pollData['trust_score'] = $this->assignTrustScore($pollData['pollster']);
            $pollData['source_type'] = $this->determinePartisanType($pollData['pollster']);
            
            \App\Models\Poll::create($pollData);
            return 'new';
        }

        // Check if content has changed
        if ($existing->content_hash !== $pollData['content_hash']) {
            $existing->update([
                'candidates_data' => $pollData['candidates_data'],
                'content_hash' => $pollData['content_hash'],
            ]);
            return 'updated';
        }

        return 'unchanged';
    }

    /**
     * Determine race type from row data.
     */
    protected function determineRaceType(array $row, array $headerMap): string
    {
        $office = strtolower($row[$headerMap['office_type'] ?? $headerMap['office'] ?? 99] ?? '');
        
        if (str_contains($office, 'president')) return 'presidential';
        if (str_contains($office, 'senate')) return 'senate';
        if (str_contains($office, 'governor')) return 'governor';
        if (str_contains($office, 'house')) return 'house';
        
        return 'presidential'; // Default
    }

    /**
     * Normalize state value.
     */
    protected function normalizeState(?string $state): ?string
    {
        if (!$state || strtolower($state) === 'national' || $state === '') {
            return null;
        }
        return strtoupper(trim($state));
    }

    /**
     * Assign trust score based on pollster name.
     */
    protected function assignTrustScore(string $pollster): int
    {
        $goldStandard = ['NYT', 'Siena', 'Marist', 'Monmouth', 'Selzer'];
        $highQuality = ['Quinnipiac', 'Emerson', 'Suffolk', 'ABC', 'CBS', 'NBC', 'Fox News', 'CNN'];
        $lowQuality = ['Rasmussen', 'Trafalgar', 'Insider Advantage'];

        $pollsterLower = strtolower($pollster);

        foreach ($goldStandard as $name) {
            if (str_contains($pollsterLower, strtolower($name))) {
                return \App\Models\Poll::TRUST_GOLD_STANDARD;
            }
        }

        foreach ($highQuality as $name) {
            if (str_contains($pollsterLower, strtolower($name))) {
                return \App\Models\Poll::TRUST_HIGH;
            }
        }

        foreach ($lowQuality as $name) {
            if (str_contains($pollsterLower, strtolower($name))) {
                return \App\Models\Poll::TRUST_LOW;
            }
        }

        return \App\Models\Poll::TRUST_MEDIUM;
    }

    /**
     * Determine if pollster is partisan.
     */
    protected function determinePartisanType(string $pollster): string
    {
        $rLeaning = ['Rasmussen', 'Trafalgar', 'Insider Advantage', 'McLaughlin'];
        $dLeaning = ['PPP', 'Public Policy Polling', 'Civiqs'];

        $pollsterLower = strtolower($pollster);

        foreach ($rLeaning as $name) {
            if (str_contains($pollsterLower, strtolower($name))) {
                return \App\Models\Poll::SOURCE_R_INTERNAL;
            }
        }

        foreach ($dLeaning as $name) {
            if (str_contains($pollsterLower, strtolower($name))) {
                return \App\Models\Poll::SOURCE_D_INTERNAL;
            }
        }

        return \App\Models\Poll::SOURCE_STANDARD;
    }

    /**
     * Get recent polls from database.
     */
    public function getRecentPolls(int $limit = 20): \Illuminate\Support\Collection
    {
        return \App\Models\Poll::recent()->limit($limit)->get();
    }

    /**
     * Get poll statistics.
     */
    public function getPollStats(): array
    {
        return [
            'total' => \App\Models\Poll::count(),
            'by_source' => \App\Models\Poll::groupBy('source')
                ->selectRaw('source, count(*) as count')
                ->pluck('count', 'source')
                ->toArray(),
            'by_race' => \App\Models\Poll::groupBy('race_type')
                ->selectRaw('race_type, count(*) as count')
                ->pluck('count', 'race_type')
                ->toArray(),
            'needs_review' => \App\Models\Poll::needsReview()->count(),
        ];
    }
}
