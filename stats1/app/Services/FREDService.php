<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FREDService
{
    protected string $baseUrl = 'https://api.stlouisfed.org/fred';
    protected string $apiKey;
    protected int $cacheMinutes = 360; // Cache for 6 hours (FRED data updates infrequently)

    // Common economic indicator series IDs
    public const INFLATION = 'CPIAUCSL';      // Consumer Price Index
    public const UNEMPLOYMENT = 'UNRATE';      // Unemployment Rate
    public const CONSUMER_CONFIDENCE = 'UMCSENT';  // Consumer Sentiment
    public const GDP_GROWTH = 'A191RL1Q225SBEA';   // Real GDP Growth Rate
    public const FED_FUNDS_RATE = 'FEDFUNDS';      // Federal Funds Rate

    public function __construct()
    {
        $this->apiKey = config('services.fred.api_key', env('FRED_API_KEY'));
    }

    /**
     * Get multiple economic indicators for a date range.
     * 
     * @param array $seriesIds Array of FRED series IDs
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array Indexed by series ID
     */
    public function getIndicators(array $seriesIds, ?string $startDate = null, ?string $endDate = null): array
    {
        $result = [];

        foreach ($seriesIds as $seriesId) {
            $data = $this->getSeries($seriesId, $startDate, $endDate);
            if ($data) {
                $result[$seriesId] = $data;
            }
        }

        return $result;
    }

    /**
     * Get a single FRED series.
     */
    public function getSeries(string $seriesId, ?string $startDate = null, ?string $endDate = null): ?array
    {
        $cacheKey = "fred_{$seriesId}_{$startDate}_{$endDate}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($seriesId, $startDate, $endDate) {
            try {
                $params = [
                    'series_id' => $seriesId,
                    'api_key' => $this->apiKey,
                    'file_type' => 'json',
                ];

                if ($startDate) {
                    $params['observation_start'] = $startDate;
                }
                if ($endDate) {
                    $params['observation_end'] = $endDate;
                }

                $response = Http::timeout(30)->get("{$this->baseUrl}/series/observations", $params);

                if (!$response->successful()) {
                    Log::error('FRED API Error', [
                        'series' => $seriesId,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                $data = $response->json();
                $observations = $data['observations'] ?? [];

                return $this->transformObservations($seriesId, $observations);
            } catch (\Exception $e) {
                Log::error('FRED API Exception', ['message' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get all common economic indicators for a date range.
     */
    public function getEconomicSnapshot(?string $startDate = null, ?string $endDate = null): array
    {
        $indicators = $this->getIndicators([
            self::INFLATION,
            self::UNEMPLOYMENT,
            self::CONSUMER_CONFIDENCE,
        ], $startDate, $endDate);

        return [
            'inflation' => $indicators[self::INFLATION] ?? null,
            'unemployment' => $indicators[self::UNEMPLOYMENT] ?? null,
            'consumer_confidence' => $indicators[self::CONSUMER_CONFIDENCE] ?? null,
        ];
    }

    /**
     * Get the latest values for key economic indicators.
     */
    public function getLatestIndicators(): array
    {
        $endDate = Carbon::now()->format('Y-m-d');
        $startDate = Carbon::now()->subMonths(3)->format('Y-m-d');

        $snapshot = $this->getEconomicSnapshot($startDate, $endDate);

        return [
            'inflation_rate' => $this->getLatestValue($snapshot['inflation'] ?? []),
            'unemployment_rate' => $this->getLatestValue($snapshot['unemployment'] ?? []),
            'consumer_confidence' => $this->getLatestValue($snapshot['consumer_confidence'] ?? []),
            'as_of_date' => $endDate,
        ];
    }

    /**
     * Get indicator value for a specific date (or nearest available).
     */
    public function getIndicatorForDate(string $seriesId, string $date): ?float
    {
        // FRED data is usually monthly, so get a range around the target date
        $startDate = Carbon::parse($date)->subMonths(2)->format('Y-m-d');
        $endDate = Carbon::parse($date)->addMonths(1)->format('Y-m-d');

        $data = $this->getSeries($seriesId, $startDate, $endDate);

        if (!$data || empty($data['observations'])) {
            return null;
        }

        // Find the closest observation to the target date
        $targetTimestamp = Carbon::parse($date)->timestamp;
        $closest = null;
        $closestDiff = PHP_INT_MAX;

        foreach ($data['observations'] as $obs) {
            $obsTimestamp = Carbon::parse($obs['date'])->timestamp;
            $diff = abs($obsTimestamp - $targetTimestamp);
            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closest = $obs;
            }
        }

        return $closest ? (float) $closest['value'] : null;
    }

    /**
     * Resample monthly FRED data to daily (forward-fill).
     * This aligns economic data with daily polling/campaign data.
     */
    public function resampleToDaily(array $seriesData, string $startDate, string $endDate): array
    {
        $observations = $seriesData['observations'] ?? [];
        if (empty($observations)) {
            return [];
        }

        // Create a lookup of dates to values
        $valuesByDate = [];
        foreach ($observations as $obs) {
            $valuesByDate[$obs['date']] = $obs['value'];
        }

        // Forward-fill to create daily values
        $result = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $lastValue = null;

        // Sort observation dates
        ksort($valuesByDate);

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');

            // Check if we have an observation for this date
            if (isset($valuesByDate[$dateStr])) {
                $lastValue = $valuesByDate[$dateStr];
            } else {
                // Find the most recent observation before this date
                foreach ($valuesByDate as $obsDate => $value) {
                    if ($obsDate <= $dateStr) {
                        $lastValue = $value;
                    } else {
                        break;
                    }
                }
            }

            if ($lastValue !== null) {
                $result[] = [
                    'date' => $dateStr,
                    'value' => $lastValue,
                ];
            }

            $current->addDay();
        }

        return $result;
    }

    /**
     * Transform raw FRED observations.
     */
    protected function transformObservations(string $seriesId, array $observations): array
    {
        $transformed = [];
        foreach ($observations as $obs) {
            // Skip missing values (FRED uses "." for missing)
            if ($obs['value'] === '.') {
                continue;
            }

            $transformed[] = [
                'date' => $obs['date'],
                'value' => (float) $obs['value'],
            ];
        }

        return [
            'series_id' => $seriesId,
            'observations' => $transformed,
            'count' => count($transformed),
        ];
    }

    /**
     * Get the latest value from an observations array.
     */
    protected function getLatestValue(?array $data): ?float
    {
        if (!$data || empty($data['observations'])) {
            return null;
        }

        $observations = $data['observations'];
        return end($observations)['value'] ?? null;
    }

    /**
     * Get series metadata/info.
     */
    public function getSeriesInfo(string $seriesId): ?array
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/series", [
                'series_id' => $seriesId,
                'api_key' => $this->apiKey,
                'file_type' => 'json',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            return $data['seriess'][0] ?? null;
        } catch (\Exception $e) {
            Log::error('FRED API Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Clear the FRED data cache.
     */
    public function clearCache(): void
    {
        Cache::forget("fred_*");
    }
}
