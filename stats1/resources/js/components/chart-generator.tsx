import { useState, useEffect, useMemo } from 'react';
import { BarChart3, Download, RefreshCw } from 'lucide-react';
import axios from 'axios';
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from 'recharts';

interface TrendDataPoint {
    date: string;
    average: number;
    poll_count: number;
}

interface Standing {
    name: string;
    average: number;
    party: string | null;
    poll_count: number;
}

interface ChartData {
    state: string;
    race_type: string;
    candidates: string[];
    trends: Record<string, TrendDataPoint[]>;
    latest_standings: Standing[];
    poll_count: number;
    date_range: {
        start: string;
        end: string;
    };
}

interface ChartGeneratorProps {
    className?: string;
}

const CANDIDATE_COLORS: Record<string, string> = {
    'Donald Trump': '#e53935',
    'Joe Biden': '#1e88e5',
    'Nikki Haley': '#8e24aa',
    'Ron DeSantis': '#43a047',
    'Kamala Harris': '#5e35b1',
    'Robert Kennedy': '#fb8c00',
};

const DEFAULT_COLORS = [
    '#e53935', '#1e88e5', '#43a047', '#8e24aa', '#fb8c00',
    '#00acc1', '#d81b60', '#3949ab', '#7cb342', '#546e7a'
];

export function ChartGenerator({ className = '' }: ChartGeneratorProps) {
    const [chartData, setChartData] = useState<ChartData | null>(null);
    const [states, setStates] = useState<string[]>([]);
    const [selectedState, setSelectedState] = useState<string>('');
    const [selectedRace, setSelectedRace] = useState<string>('presidential');
    const [rollingDays, setRollingDays] = useState<number>(7);
    const [isLoading, setIsLoading] = useState(false);
    const [isExporting, setIsExporting] = useState(false);

    // Fetch available states
    useEffect(() => {
        const fetchStates = async () => {
            try {
                const response = await axios.get('/charts/states', {
                    params: { race_type: selectedRace }
                });
                setStates(response.data.states);
            } catch (error) {
                console.error('Failed to fetch states:', error);
            }
        };
        fetchStates();
    }, [selectedRace]);

    // Fetch chart data
    useEffect(() => {
        const fetchChartData = async () => {
            setIsLoading(true);
            try {
                const params: Record<string, string | number> = {
                    race_type: selectedRace,
                    rolling_days: rollingDays,
                };
                if (selectedState) params.state = selectedState;

                const response = await axios.get('/charts/trends', { params });
                setChartData(response.data);
            } catch (error) {
                console.error('Failed to fetch chart data:', error);
            } finally {
                setIsLoading(false);
            }
        };
        fetchChartData();
    }, [selectedState, selectedRace, rollingDays]);

    // Transform data for Recharts
    const formattedChartData = useMemo(() => {
        if (!chartData?.trends) return [];

        // Get all unique dates
        const allDates = new Set<string>();
        Object.values(chartData.trends).forEach(trend => {
            trend.forEach(point => allDates.add(point.date));
        });

        const sortedDates = Array.from(allDates).sort();

        return sortedDates.map(date => {
            const point: Record<string, string | number> = { date };

            Object.entries(chartData.trends).forEach(([candidate, trend]) => {
                const dataPoint = trend.find(t => t.date === date);
                if (dataPoint) {
                    point[candidate] = dataPoint.average;
                }
            });

            return point;
        });
    }, [chartData]);

    const getCandidateColor = (candidate: string, index: number) => {
        return CANDIDATE_COLORS[candidate] || DEFAULT_COLORS[index % DEFAULT_COLORS.length];
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const params: Record<string, string> = { race_type: selectedRace };
            if (selectedState) params.state = selectedState;

            const response = await axios.get('/charts/export', { params });

            const blob = new Blob([JSON.stringify(response.data.export_data, null, 2)], {
                type: 'application/json'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `poll-trends-${selectedState || 'national'}-${selectedRace}.json`;
            a.click();
            URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Failed to export:', error);
        } finally {
            setIsExporting(false);
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    };

    return (
        <div className={`bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm ${className}`}>
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 border-b border-zinc-200 dark:border-zinc-800">
                <div className="flex items-center gap-2">
                    <BarChart3 className="w-5 h-5 text-zinc-600 dark:text-zinc-400" />
                    <h3 className="font-semibold text-zinc-900 dark:text-zinc-100">Trend Chart Generator</h3>
                </div>
                <button
                    onClick={handleExport}
                    disabled={isExporting || !chartData}
                    className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
                >
                    <Download className={`w-3.5 h-3.5 ${isExporting ? 'animate-bounce' : ''}`} />
                    Export
                </button>
            </div>

            {/* Controls */}
            <div className="flex flex-wrap items-center gap-3 px-4 py-3 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/50">
                <div className="flex items-center gap-2">
                    <label className="text-xs font-medium text-zinc-600 dark:text-zinc-400">State:</label>
                    <select
                        value={selectedState}
                        onChange={(e) => setSelectedState(e.target.value)}
                        className="text-sm px-2 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300"
                    >
                        <option value="">National</option>
                        {states.map(state => (
                            <option key={state} value={state}>{state}</option>
                        ))}
                    </select>
                </div>

                <div className="flex items-center gap-2">
                    <label className="text-xs font-medium text-zinc-600 dark:text-zinc-400">Race:</label>
                    <select
                        value={selectedRace}
                        onChange={(e) => setSelectedRace(e.target.value)}
                        className="text-sm px-2 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300"
                    >
                        <option value="presidential">Presidential</option>
                        <option value="senate">Senate</option>
                        <option value="governor">Governor</option>
                    </select>
                </div>

                <div className="flex items-center gap-2">
                    <label className="text-xs font-medium text-zinc-600 dark:text-zinc-400">Rolling Avg:</label>
                    <select
                        value={rollingDays}
                        onChange={(e) => setRollingDays(Number(e.target.value))}
                        className="text-sm px-2 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300"
                    >
                        <option value={3}>3-day</option>
                        <option value={7}>7-day</option>
                        <option value={14}>14-day</option>
                    </select>
                </div>
            </div>

            {/* Chart */}
            <div className="p-4">
                {isLoading ? (
                    <div className="flex items-center justify-center h-64 text-zinc-500">
                        <RefreshCw className="w-6 h-6 animate-spin mr-2" />
                        Loading chart data...
                    </div>
                ) : !chartData || formattedChartData.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-64 text-zinc-500">
                        <BarChart3 className="w-12 h-12 opacity-30 mb-3" />
                        <p>No polling data available</p>
                        <p className="text-xs mt-1">Run the pipeline to sync polls</p>
                    </div>
                ) : (
                    <>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={formattedChartData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis
                                        dataKey="date"
                                        tickFormatter={formatDate}
                                        tick={{ fontSize: 11 }}
                                        stroke="#9ca3af"
                                    />
                                    <YAxis
                                        domain={['auto', 'auto']}
                                        tick={{ fontSize: 11 }}
                                        stroke="#9ca3af"
                                        tickFormatter={(value) => `${value}%`}
                                    />
                                    <Tooltip
                                        contentStyle={{
                                            backgroundColor: 'white',
                                            border: '1px solid #e5e7eb',
                                            borderRadius: '8px',
                                            fontSize: '12px',
                                        }}
                                        formatter={(value: number) => [`${value}%`, '']}
                                        labelFormatter={(label) => formatDate(String(label))}
                                    />
                                    <Legend
                                        wrapperStyle={{ fontSize: '12px' }}
                                    />
                                    {chartData.candidates.map((candidate, index) => (
                                        <Line
                                            key={candidate}
                                            type="monotone"
                                            dataKey={candidate}
                                            stroke={getCandidateColor(candidate, index)}
                                            strokeWidth={2}
                                            dot={false}
                                            activeDot={{ r: 4 }}
                                        />
                                    ))}
                                </LineChart>
                            </ResponsiveContainer>
                        </div>

                        {/* Current Standings */}
                        {chartData.latest_standings.length > 0 && (
                            <div className="mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                                <h4 className="text-xs font-medium text-zinc-500 mb-2">Current Standings</h4>
                                <div className="flex flex-wrap gap-3">
                                    {chartData.latest_standings.slice(0, 4).map((standing, index) => (
                                        <div
                                            key={standing.name}
                                            className="flex items-center gap-2 px-3 py-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-lg"
                                        >
                                            <div
                                                className="w-3 h-3 rounded-full"
                                                style={{ backgroundColor: getCandidateColor(standing.name, index) }}
                                            />
                                            <span className="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {standing.name}
                                            </span>
                                            <span className="text-sm font-bold text-zinc-600 dark:text-zinc-400">
                                                {standing.average}%
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Stats */}
                        <div className="flex items-center gap-4 mt-3 text-xs text-zinc-500">
                            <span>📊 {chartData.poll_count} polls</span>
                            <span>📅 {chartData.date_range.start} to {chartData.date_range.end}</span>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
