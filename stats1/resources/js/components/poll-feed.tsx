import { useState, useEffect } from 'react';
import { Activity, TrendingUp, TrendingDown, Minus, AlertTriangle, CheckCircle } from 'lucide-react';
import axios from 'axios';

interface Poll {
    id: number;
    poll_id: string;
    source: string;
    pollster: string;
    race_type: string;
    state: string;
    end_date: string;
    sample_size: number | null;
    fte_grade: string | null;
    trust_score: number;
    source_type: string;
    leading: { name: string; percentage: number } | null;
    spread: number | null;
    needs_review: boolean;
    created_at: string;
}

interface PollFeedProps {
    className?: string;
    limit?: number;
}

export function PollFeed({ className = '', limit = 10 }: PollFeedProps) {
    const [polls, setPolls] = useState<Poll[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [sourceFilter, setSourceFilter] = useState<string>('');

    useEffect(() => {
        const fetchPolls = async () => {
            setIsLoading(true);
            try {
                const params: Record<string, string | number> = { limit };
                if (sourceFilter) params.source = sourceFilter;

                const response = await axios.get('/charts/poll-feed', { params });
                setPolls(response.data.polls);
            } catch (error) {
                console.error('Failed to fetch poll feed:', error);
            } finally {
                setIsLoading(false);
            }
        };

        fetchPolls();
    }, [limit, sourceFilter]);

    const getSourceBadge = (source: string) => {
        const badges: Record<string, string> = {
            '538': 'bg-orange-100 text-orange-700',
            'rcp': 'bg-blue-100 text-blue-700',
            'nyt': 'bg-purple-100 text-purple-700',
        };
        return badges[source.toLowerCase()] || 'bg-gray-100 text-gray-700';
    };

    const getSourceTypeBadge = (sourceType: string) => {
        const badges: Record<string, { color: string; label: string }> = {
            'standard': { color: 'bg-emerald-100 text-emerald-700', label: 'Standard' },
            'r_internal': { color: 'bg-red-100 text-red-700', label: 'R-Leaning' },
            'd_internal': { color: 'bg-blue-100 text-blue-700', label: 'D-Leaning' },
            'partisan': { color: 'bg-amber-100 text-amber-700', label: 'Partisan' },
        };
        return badges[sourceType] || { color: 'bg-gray-100 text-gray-700', label: 'Unknown' };
    };

    const getTrustIndicator = (score: number) => {
        if (score >= 80) return { color: 'text-emerald-500', icon: CheckCircle, label: 'High Trust' };
        if (score >= 50) return { color: 'text-yellow-500', icon: Activity, label: 'Medium Trust' };
        return { color: 'text-red-500', icon: AlertTriangle, label: 'Low Trust' };
    };

    const getGradeBadge = (grade: string | null) => {
        if (!grade) return null;

        const gradeColors: Record<string, string> = {
            'A+': 'bg-emerald-500 text-white',
            'A': 'bg-emerald-400 text-white',
            'A-': 'bg-emerald-300 text-emerald-800',
            'B+': 'bg-blue-400 text-white',
            'B': 'bg-blue-300 text-blue-800',
            'B-': 'bg-blue-200 text-blue-700',
            'C+': 'bg-yellow-300 text-yellow-800',
            'C': 'bg-yellow-200 text-yellow-700',
            'C-': 'bg-orange-200 text-orange-700',
            'D': 'bg-red-200 text-red-700',
            'F': 'bg-red-400 text-white',
        };

        return gradeColors[grade] || 'bg-gray-200 text-gray-700';
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    };

    return (
        <div className={`bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm ${className}`}>
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 border-b border-zinc-200 dark:border-zinc-800">
                <div className="flex items-center gap-2">
                    <Activity className="w-5 h-5 text-zinc-600 dark:text-zinc-400" />
                    <h3 className="font-semibold text-zinc-900 dark:text-zinc-100">Incoming Poll Feed</h3>
                </div>
                <div className="flex items-center gap-2">
                    <select
                        value={sourceFilter}
                        onChange={(e) => setSourceFilter(e.target.value)}
                        className="text-xs px-2 py-1 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300"
                    >
                        <option value="">All Sources</option>
                        <option value="538">FiveThirtyEight</option>
                        <option value="rcp">RealClearPolitics</option>
                    </select>
                </div>
            </div>

            {/* Poll List */}
            <div className="divide-y divide-zinc-100 dark:divide-zinc-800 max-h-96 overflow-y-auto">
                {isLoading ? (
                    <div className="px-4 py-8 text-center text-zinc-500">
                        Loading polls...
                    </div>
                ) : polls.length === 0 ? (
                    <div className="px-4 py-8 text-center text-zinc-500">
                        <Activity className="w-8 h-8 mx-auto mb-2 opacity-50" />
                        <p>No polls found</p>
                        <p className="text-xs mt-1">Run the pipeline to sync polls</p>
                    </div>
                ) : (
                    polls.map(poll => {
                        const trustInfo = getTrustIndicator(poll.trust_score);
                        const TrustIcon = trustInfo.icon;
                        const sourceTypeInfo = getSourceTypeBadge(poll.source_type);

                        return (
                            <div key={poll.id} className="px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex-1 min-w-0">
                                        {/* Top Row: Source badges */}
                                        <div className="flex flex-wrap items-center gap-2 mb-1.5">
                                            <span className={`inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded ${getSourceBadge(poll.source)}`}>
                                                {poll.source.toUpperCase()}
                                            </span>
                                            {poll.fte_grade && (
                                                <span className={`inline-flex items-center px-1.5 py-0.5 text-xs font-bold rounded ${getGradeBadge(poll.fte_grade)}`}>
                                                    {poll.fte_grade}
                                                </span>
                                            )}
                                            {poll.source_type !== 'standard' && (
                                                <span className={`inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded ${sourceTypeInfo.color}`}>
                                                    {sourceTypeInfo.label}
                                                </span>
                                            )}
                                            {poll.needs_review && (
                                                <span className="inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded bg-amber-100 text-amber-700">
                                                    Needs Review
                                                </span>
                                            )}
                                        </div>

                                        {/* Pollster & Race */}
                                        <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {poll.pollster}
                                        </p>
                                        <p className="text-xs text-zinc-500 mt-0.5">
                                            {poll.state} • {poll.race_type.charAt(0).toUpperCase() + poll.race_type.slice(1)}
                                            {poll.sample_size && ` • n=${poll.sample_size.toLocaleString()}`}
                                        </p>
                                    </div>

                                    {/* Right Side: Results & Date */}
                                    <div className="text-right flex-shrink-0">
                                        {poll.leading && (
                                            <div className="flex items-center gap-1 justify-end">
                                                <span className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                                    {poll.leading.name}
                                                </span>
                                                {poll.spread !== null && poll.spread !== 0 && (
                                                    <span className={`text-xs font-medium ${poll.spread > 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                                                        {poll.spread > 0 ? '+' : ''}{poll.spread.toFixed(1)}
                                                    </span>
                                                )}
                                            </div>
                                        )}
                                        <div className="flex items-center gap-2 justify-end mt-1">
                                            <TrustIcon className={`w-3 h-3 ${trustInfo.color}`} />
                                            <span className="text-xs text-zinc-400">
                                                {formatDate(poll.end_date)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
}
