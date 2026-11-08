<?php

namespace App\Http\Controllers;

use App\Models\UnifiedDataset;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExplorerController extends Controller
{
    public function index(Request $request)
    {
        $query = UnifiedDataset::query();

        // Apply filters
        if ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        if ($request->has('candidate')) {
            $query->where('candidate', $request->candidate);
        }
        if ($request->has('party')) {
            $query->where('party', $request->party);
        }

        // Get paginated data
        $data = $query->orderBy('date', 'desc')
            ->paginate(50)
            ->withQueryString();

        // Get unique values for filters
        $candidates = UnifiedDataset::distinct()->pluck('candidate')->filter();
        $parties = UnifiedDataset::distinct()->pluck('party')->filter();

        return Inertia::render('explorer', [
            'data' => $data,
            'filters' => [
                'candidates' => $candidates,
                'parties' => $parties,
            ],
            'currentFilters' => $request->only(['start_date', 'end_date', 'candidate', 'party']),
        ]);
    }

    public function export(Request $request)
    {
        $format = $request->get('format', 'csv');
        $query = UnifiedDataset::query();

        // Apply same filters as index
        if ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        if ($request->has('candidate')) {
            $query->where('candidate', $request->candidate);
        }
        if ($request->has('party')) {
            $query->where('party', $request->party);
        }

        $data = $query->orderBy('date', 'desc')->get();

        if ($format === 'json') {
            return response()->json($data)
                ->header('Content-Disposition', 'attachment; filename="unified_dataset.json"');
        }

        // CSV export
        $filename = 'unified_dataset_' . now()->format('Y-m-d') . '.csv';
        
        return new StreamedResponse(function () use ($data) {
            $handle = fopen('php://output', 'w');
            
            // Header row
            fputcsv($handle, [
                'Date', 'Candidate', 'Party', 'Poll Avg (%)', 'Cash on Hand ($)',
                'Total Receipts ($)', 'Total Disbursements ($)', 'Inflation Rate (%)',
                'Unemployment Rate (%)', 'GDP Growth (%)', 'Consumer Confidence', 'Source'
            ]);
            
            // Data rows
            foreach ($data as $row) {
                fputcsv($handle, [
                    $row->date->format('Y-m-d'),
                    $row->candidate,
                    $row->party,
                    $row->poll_avg,
                    $row->cash_on_hand,
                    $row->total_receipts,
                    $row->total_disbursements,
                    $row->inflation_rate,
                    $row->unemployment_rate,
                    $row->gdp_growth,
                    $row->consumer_confidence,
                    $row->source,
                ]);
            }
            
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    public function correlationAnalysis(Request $request)
    {
        $validated = $request->validate([
            'variable_x' => 'required|string',
            'variable_y' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = UnifiedDataset::query()
            ->whereNotNull($validated['variable_x'])
            ->whereNotNull($validated['variable_y']);

        if (isset($validated['start_date'])) {
            $query->where('date', '>=', $validated['start_date']);
        }
        if (isset($validated['end_date'])) {
            $query->where('date', '<=', $validated['end_date']);
        }

        $data = $query->get([
            'date',
            $validated['variable_x'] . ' as x',
            $validated['variable_y'] . ' as y',
        ]);

        // Calculate correlation coefficient (Pearson)
        $correlation = $this->calculateCorrelation(
            $data->pluck('x')->toArray(),
            $data->pluck('y')->toArray()
        );

        return response()->json([
            'data' => $data,
            'correlation' => round($correlation, 4),
            'n' => $data->count(),
        ]);
    }

    private function calculateCorrelation(array $x, array $y): float
    {
        $n = count($x);
        if ($n === 0 || $n !== count($y)) {
            return 0;
        }

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }

        $numerator = ($n * $sumXY) - ($sumX * $sumY);
        $denominator = sqrt(
            (($n * $sumX2) - ($sumX * $sumX)) * 
            (($n * $sumY2) - ($sumY * $sumY))
        );

        if ($denominator == 0) {
            return 0;
        }

        return $numerator / $denominator;
    }
}
