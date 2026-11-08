<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FECService
{
    protected string $baseUrl = 'https://api.open.fec.gov/v1';
    protected string $apiKey;
    protected int $perPage = 100;
    protected int $cacheMinutes = 60; // Cache for 1 hour to respect rate limits

    public function __construct()
    {
        $this->apiKey = config('services.fec.api_key', env('FEC_API_KEY'));
    }

    /**
     * Get all presidential candidates for a given election year.
     * 
     * @param int $electionYear
     * @param array $filters Optional filters (has_raised_funds, party, etc.)
     * @param int|null $limit Max candidates to return (null = all)
     * @return array
     */
    public function getPresidentialCandidates(int $electionYear = 2024, array $filters = [], ?int $limit = null): array
    {
        $cacheKey = "fec_candidates_{$electionYear}_" . md5(json_encode($filters));
        
        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($electionYear, $filters, $limit) {
            $candidates = [];
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $params = array_merge([
                    'api_key' => $this->apiKey,
                    'election_year' => $electionYear,
                    'office' => 'P', // Presidential
                    'per_page' => $this->perPage,
                    'page' => $page,
                    'sort' => '-receipts', // Sort by most money raised
                ], $filters);

                try {
                    $response = Http::timeout(30)->get("{$this->baseUrl}/candidates/totals/", $params);

                    if (!$response->successful()) {
                        Log::error('FEC API Error', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);
                        break;
                    }

                    $data = $response->json();
                    $results = $data['results'] ?? [];

                    foreach ($results as $candidate) {
                        $candidates[] = $this->transformCandidate($candidate);

                        if ($limit && count($candidates) >= $limit) {
                            return $candidates;
                        }
                    }

                    $pagination = $data['pagination'] ?? [];
                    $hasMore = $page < ($pagination['pages'] ?? 0);
                    $page++;

                    // Rate limiting: sleep between requests
                    if ($hasMore) {
                        usleep(100000); // 100ms delay
                    }
                } catch (\Exception $e) {
                    Log::error('FEC API Exception', ['message' => $e->getMessage()]);
                    break;
                }
            }

            return $candidates;
        });
    }

    /**
     * Get candidates who have actually raised funds (active campaigns).
     */
    public function getActiveCandidates(int $electionYear = 2024, ?int $limit = 200): array
    {
        return $this->getPresidentialCandidates($electionYear, [
            'has_raised_funds' => 'true',
        ], $limit);
    }

    /**
     * Get top candidates by total receipts.
     */
    public function getTopCandidatesByFundraising(int $electionYear = 2024, int $limit = 20): array
    {
        return array_slice($this->getActiveCandidates($electionYear), 0, $limit);
    }

    /**
     * Get candidates filtered by party.
     */
    public function getCandidatesByParty(string $party, int $electionYear = 2024): array
    {
        return $this->getPresidentialCandidates($electionYear, [
            'party' => strtoupper($party),
            'has_raised_funds' => 'true',
        ]);
    }

    /**
     * Get a single candidate by their FEC candidate ID.
     */
    public function getCandidate(string $candidateId): ?array
    {
        $cacheKey = "fec_candidate_{$candidateId}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($candidateId) {
            try {
                $response = Http::timeout(30)->get("{$this->baseUrl}/candidate/{$candidateId}/totals/", [
                    'api_key' => $this->apiKey,
                ]);

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();
                $results = $data['results'] ?? [];

                return !empty($results) ? $this->transformCandidate($results[0]) : null;
            } catch (\Exception $e) {
                Log::error('FEC API Exception', ['message' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Transform raw FEC API data into our unified format.
     */
    protected function transformCandidate(array $raw): array
    {
        return [
            'candidate_id' => $raw['candidate_id'] ?? null,
            'name' => $this->formatName($raw['name'] ?? 'Unknown'),
            'party' => $raw['party'] ?? 'UNK',
            'party_full' => $raw['party_full'] ?? 'Unknown',
            'state' => $raw['state'] ?? 'US',
            'incumbent_challenge' => $raw['incumbent_challenge'] ?? null,
            'incumbent_challenge_full' => $raw['incumbent_challenge_full'] ?? null,
            'has_raised_funds' => $raw['has_raised_funds'] ?? false,
            'cash_on_hand' => (float) ($raw['cash_on_hand_end_period'] ?? 0),
            'total_receipts' => (float) ($raw['receipts'] ?? 0),
            'total_disbursements' => (float) ($raw['disbursements'] ?? 0),
            'debts_owed' => (float) ($raw['debts_owed_by_committee'] ?? 0),
            'individual_contributions' => (float) ($raw['individual_itemized_contributions'] ?? 0),
            'pac_contributions' => (float) ($raw['other_political_committee_contributions'] ?? 0),
            'coverage_start' => $raw['coverage_start_date'] ?? null,
            'coverage_end' => $raw['coverage_end_date'] ?? null,
            'election_year' => $raw['election_year'] ?? null,
            'first_file_date' => $raw['first_file_date'] ?? null,
            'last_file_date' => $raw['last_file_date'] ?? null,
        ];
    }

    /**
     * Format candidate name from FEC format (LAST, FIRST) to readable format.
     */
    protected function formatName(string $name): string
    {
        // Remove titles like MR., MS., DR., etc.
        $name = preg_replace('/\b(MR\.?|MS\.?|MRS\.?|DR\.?|JR\.?|SR\.?|II|III|IV|PHD)\b/i', '', $name);
        
        // Handle LAST, FIRST format
        if (strpos($name, ',') !== false) {
            $parts = array_map('trim', explode(',', $name, 2));
            if (count($parts) === 2) {
                // Take first word of first name, and last name
                $firstName = explode(' ', trim($parts[1]))[0];
                $lastName = trim($parts[0]);
                return $firstName . ' ' . $lastName;
            }
        }

        return trim($name);
    }

    /**
     * Get aggregate statistics for all candidates.
     */
    public function getCandidateStats(int $electionYear = 2024): array
    {
        $candidates = $this->getActiveCandidates($electionYear);
        
        $byParty = [];
        $totalRaised = 0;
        $totalCash = 0;

        foreach ($candidates as $candidate) {
            $party = $candidate['party'];
            if (!isset($byParty[$party])) {
                $byParty[$party] = [
                    'count' => 0,
                    'total_receipts' => 0,
                    'total_cash' => 0,
                ];
            }
            $byParty[$party]['count']++;
            $byParty[$party]['total_receipts'] += $candidate['total_receipts'];
            $byParty[$party]['total_cash'] += $candidate['cash_on_hand'];
            $totalRaised += $candidate['total_receipts'];
            $totalCash += $candidate['cash_on_hand'];
        }

        return [
            'total_candidates' => count($candidates),
            'total_raised' => $totalRaised,
            'total_cash_on_hand' => $totalCash,
            'by_party' => $byParty,
            'top_fundraiser' => $candidates[0] ?? null,
        ];
    }

    /**
     * Clear the FEC data cache.
     */
    public function clearCache(): void
    {
        Cache::forget("fec_candidates_*");
    }
}
