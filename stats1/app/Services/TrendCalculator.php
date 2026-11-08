<?php

namespace App\Services;

use App\Models\Poll;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrendCalculator
{
    /**
     * Calculate rolling average for a candidate.
     */
    public function calculateRollingAverage(
        string $candidateName,
        int $days = 7,
        ?string $state = null,
        string $raceType = 'presidential'
    ): array {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays($days * 3); // Fetch more data for better averaging

        $query = Poll::forRace($raceType)
            ->inDateRange($startDate->format('Y-m-d'), $endDate->format('Y-m-d'))
            ->recent();

        if ($state) {
            $query->forState($state);
        } else {
            $query->whereNull('state');
        }

        $polls = $query->get();

        // Extract candidate data and calculate daily averages
        $dailyData = [];
        
        foreach ($polls as $poll) {
            $candidates = $poll->candidates_data ?? [];
            $pollDate = $poll->end_date->format('Y-m-d');
            
            foreach ($candidates as $candidate) {
                $name = strtolower($candidate['name'] ?? '');
                if (str_contains($name, strtolower($candidateName))) {
                    if (!isset($dailyData[$pollDate])) {
                        $dailyData[$pollDate] = [];
                    }
                    $dailyData[$pollDate][] = [
                        'value' => $candidate['percentage'] ?? 0,
                        'weight' => $poll->trust_score / 100,
                    ];
                }
            }
        }

        // Calculate weighted rolling averages
        $result = [];
        $sortedDates = array_keys($dailyData);
        sort($sortedDates);

        foreach ($sortedDates as $i => $date) {
            // Get data from the past N days
            $windowStart = Carbon::parse($date)->subDays($days);
            $windowData = [];

            foreach ($sortedDates as $checkDate) {
                $checkCarbon = Carbon::parse($checkDate);
                if ($checkCarbon >= $windowStart && $checkCarbon <= Carbon::parse($date)) {
                    $windowData = array_merge($windowData, $dailyData[$checkDate] ?? []);
                }
            }

            if (count($windowData) > 0) {
                $weightedSum = 0;
                $totalWeight = 0;
                
                foreach ($windowData as $dataPoint) {
                    $weightedSum += $dataPoint['value'] * $dataPoint['weight'];
                    $totalWeight += $dataPoint['weight'];
                }

                $result[] = [
                    'date' => $date,
                    'average' => $totalWeight > 0 ? round($weightedSum / $totalWeight, 1) : 0,
                    'poll_count' => count($windowData),
                ];
            }
        }

        return $result;
    }

    /**
     * Get chart data for a specific race.
     */
    public function getChartData(
        ?string $state = null,
        string $raceType = 'presidential',
        int $rollingDays = 7
    ): array {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subMonths(3);

        $query = Poll::forRace($raceType)
            ->inDateRange($startDate->format('Y-m-d'), $endDate->format('Y-m-d'))
            ->recent();

        if ($state) {
            $query->forState($state);
        } else {
            $query->whereNull('state');
        }

        $polls = $query->get();

        // Extract all unique candidates
        $candidates = [];
        foreach ($polls as $poll) {
            foreach ($poll->candidates_data ?? [] as $candidate) {
                $name = $candidate['name'] ?? '';
                if ($name && !in_array($name, $candidates)) {
                    $candidates[] = $name;
                }
            }
        }

        // Calculate trend for each candidate
        $trends = [];
        foreach ($candidates as $candidateName) {
            $trend = $this->calculateRollingAverage($candidateName, $rollingDays, $state, $raceType);
            if (!empty($trend)) {
                $trends[$candidateName] = $trend;
            }
        }

        // Get latest standings
        $latestStandings = $this->getLatestStandings($polls);

        return [
            'state' => $state ?? 'National',
            'race_type' => $raceType,
            'candidates' => $candidates,
            'trends' => $trends,
            'latest_standings' => $latestStandings,
            'poll_count' => $polls->count(),
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Get latest standings based on most recent polls.
     */
    protected function getLatestStandings(Collection $polls): array
    {
        // Take the 5 most recent high-quality polls
        $recentPolls = $polls->filter(fn($p) => $p->trust_score >= 50)->take(5);

        $standings = [];
        
        foreach ($recentPolls as $poll) {
            foreach ($poll->candidates_data ?? [] as $candidate) {
                $name = $candidate['name'] ?? '';
                if (!$name) continue;

                if (!isset($standings[$name])) {
                    $standings[$name] = [
                        'name' => $name,
                        'values' => [],
                        'party' => $candidate['party'] ?? null,
                    ];
                }
                $standings[$name]['values'][] = $candidate['percentage'] ?? 0;
            }
        }

        // Calculate averages
        $result = [];
        foreach ($standings as $name => $data) {
            $avg = count($data['values']) > 0 
                ? round(array_sum($data['values']) / count($data['values']), 1) 
                : 0;
            $result[] = [
                'name' => $name,
                'average' => $avg,
                'party' => $data['party'],
                'poll_count' => count($data['values']),
            ];
        }

        // Sort by average descending
        usort($result, fn($a, $b) => $b['average'] <=> $a['average']);

        return $result;
    }

    /**
     * Export chart data as JSON.
     */
    public function exportToJSON(?string $state = null, string $raceType = 'presidential'): string
    {
        $data = $this->getChartData($state, $raceType);
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Get available states with poll data.
     */
    public function getAvailableStates(string $raceType = 'presidential'): array
    {
        return Poll::forRace($raceType)
            ->whereNotNull('state')
            ->distinct()
            ->pluck('state')
            ->sort()
            ->values()
            ->toArray();
    }
}
