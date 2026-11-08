<?php

namespace App\Http\Controllers;

use App\Services\TrendCalculator;
use App\Services\PollingService;
use App\Models\Poll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    protected TrendCalculator $trendCalculator;
    protected PollingService $pollingService;

    public function __construct(TrendCalculator $trendCalculator, PollingService $pollingService)
    {
        $this->trendCalculator = $trendCalculator;
        $this->pollingService = $pollingService;
    }

    /**
     * Get trend data for charts.
     */
    public function trendData(Request $request): JsonResponse
    {
        $state = $request->get('state'); // null for national
        $raceType = $request->get('race_type', 'presidential');
        $rollingDays = $request->integer('rolling_days', 7);

        $data = $this->trendCalculator->getChartData($state, $raceType, $rollingDays);

        return response()->json($data);
    }

    /**
     * Get available states for dropdown.
     */
    public function availableStates(Request $request): JsonResponse
    {
        $raceType = $request->get('race_type', 'presidential');
        $states = $this->trendCalculator->getAvailableStates($raceType);

        return response()->json([
            'states' => $states,
        ]);
    }

    /**
     * Get recent polls feed.
     */
    public function pollFeed(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 20);
        $source = $request->get('source'); // Filter by source

        $query = Poll::recent();
        
        if ($source) {
            $query->fromSource($source);
        }

        $polls = $query->limit($limit)->get()->map(function ($poll) {
            return [
                'id' => $poll->id,
                'poll_id' => $poll->poll_id,
                'source' => $poll->source,
                'pollster' => $poll->pollster,
                'race_type' => $poll->race_type,
                'state' => $poll->state ?? 'National',
                'end_date' => $poll->end_date->format('Y-m-d'),
                'sample_size' => $poll->sample_size,
                'fte_grade' => $poll->fte_grade,
                'trust_score' => $poll->trust_score,
                'source_type' => $poll->source_type,
                'leading' => $poll->leading_candidate,
                'spread' => $poll->spread,
                'needs_review' => $poll->needs_review,
                'created_at' => $poll->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'polls' => $polls,
            'total' => Poll::count(),
        ]);
    }

    /**
     * Export chart data as JSON.
     */
    public function exportJSON(Request $request): JsonResponse
    {
        $state = $request->get('state');
        $raceType = $request->get('race_type', 'presidential');

        $json = $this->trendCalculator->exportToJSON($state, $raceType);

        return response()->json([
            'export_data' => json_decode($json, true),
            'exported_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get poll statistics for dashboard.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->pollingService->getPollStats();

        return response()->json($stats);
    }
}
