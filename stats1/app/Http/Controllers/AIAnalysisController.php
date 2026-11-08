<?php

namespace App\Http\Controllers;

use App\Models\PipelineRun;
use App\Services\AIAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIAnalysisController extends Controller
{
    public function __construct(
        protected AIAnalysisService $aiService
    ) {}

    /**
     * Analyze data with AI based on user query.
     */
    public function analyze(Request $request): JsonResponse
    {
        // Extend execution time for AI generation
        set_time_limit(120);
        
        $validated = $request->validate([
            'query' => 'required|string|max:1000',
            'filters' => 'nullable|array',
            'filters.start_date' => 'nullable|date',
            'filters.end_date' => 'nullable|date',
            'filters.candidate' => 'nullable|string',
            'filters.party' => 'nullable|string',
        ]);

        $result = $this->aiService->analyzeData(
            $validated['query'],
            $validated['filters'] ?? null
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Analysis failed',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'response' => $result['response'],
            'tokens_used' => $result['tokens_used'] ?? null,
        ]);
    }

    /**
     * Generate AI insights for a pipeline run.
     */
    public function pipelineSummary(PipelineRun $run): JsonResponse
    {
        // Extend execution time for AI generation (can take 30-60 seconds)
        set_time_limit(120);
        
        $result = $this->aiService->generatePipelineSummary($run);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to generate summary',
            ], 500);
        }

        // Store insights in the pipeline run
        $run->update(['ai_insights' => $result['insights']]);

        return response()->json([
            'success' => true,
            'insights' => $result['insights'],
            'tokens_used' => $result['tokens_used'] ?? null,
        ]);
    }

    /**
     * Get suggested analysis questions.
     */
    public function suggestions(): JsonResponse
    {
        $suggestions = $this->aiService->getSuggestedQuestions();

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }
}
