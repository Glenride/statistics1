import { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Pipeline',
        href: '/pipeline',
    },
];

interface DataSource {
    id: number;
    type: string;
    name: string;
    description: string | null;
    category: 'core' | 'polling' | 'custom';
    pipeline_enabled: boolean;
    status: 'active' | 'inactive' | 'error';
}

interface PipelineRun {
    id: number;
    status: 'completed' | 'running' | 'failed' | 'pending';
    started_at: string | null;
    completed_at: string | null;
    records_processed: number;
    records_created: number;
    records_updated: number;
    connectors_run: string[];
    date_range: { start: string; end: string } | null;
    error_log: string | null;
    triggered_by: string;
    created_at: string;
    ai_insights: string | null;
}

interface PipelineProps {
    runs: {
        data: PipelineRun[];
        current_page: number;
        last_page: number;
    };
    dataSources: DataSource[];
    pipelineEnabledSources: DataSource[];
}

export default function Pipeline({ runs, dataSources, pipelineEnabledSources }: PipelineProps) {
    const [activeTab, setActiveTab] = useState<'run' | 'history'>('run');
    const [isRunning, setIsRunning] = useState(false);

    // Group sources
    const apiSources = pipelineEnabledSources.filter(s => ['fec', 'fred', 'polling'].includes(s.type));
    const scraperSources = pipelineEnabledSources.filter(s => !['fec', 'fred', 'polling'].includes(s.type));

    const [selectedConnectors, setSelectedConnectors] = useState<string[]>(
        pipelineEnabledSources.map(s => s.type)
    );
    const [dateRange, setDateRange] = useState({
        start: '2023-01-01',
        end: new Date().toISOString().split('T')[0],
    });
    const [generatingInsights, setGeneratingInsights] = useState<number | null>(null);
    const [insightsToShow, setInsightsToShow] = useState<{ id: number; insights: string } | null>(null);

    const statusStyles = {
        completed: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        running: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        failed: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    };

    const handleRunPipeline = () => {
        setIsRunning(true);
        router.post('/pipeline/run', {
            connectors: selectedConnectors,
            date_range: dateRange,
        }, {
            onFinish: () => setIsRunning(false),
        });
    };

    const toggleConnector = (type: string) => {
        if (selectedConnectors.includes(type)) {
            setSelectedConnectors(selectedConnectors.filter(c => c !== type));
        } else {
            setSelectedConnectors([...selectedConnectors, type]);
        }
    };

    const formatDuration = (start: string | null, end: string | null) => {
        if (!start || !end) return '--';
        const diffMs = new Date(end).getTime() - new Date(start).getTime();
        const seconds = Math.floor(diffMs / 1000);
        const minutes = Math.floor(seconds / 60);
        if (minutes > 0) return `${minutes}m ${seconds % 60}s`;
        return `${seconds}s`;
    };

    const formatDate = (date: string | null) => {
        if (!date) return '--';
        return new Date(date).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const generateInsights = async (runId: number) => {
        setGeneratingInsights(runId);
        try {
            const response = await fetch(`/ai/pipeline-summary/${runId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            const data = await response.json();
            if (data.success && data.insights) {
                setInsightsToShow({ id: runId, insights: data.insights });
                // Refresh page to update the button state
                router.reload({ only: ['runs'] });
            } else {
                console.error('AI Analysis failed:', data.error || 'No insights returned');
                alert('Failed to generate insights: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Failed to generate insights:', error);
            alert('Failed to generate insights. Please try again.');
        } finally {
            setGeneratingInsights(null);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pipeline Management" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-6">
                {/* Header */}
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold text-foreground">Pipeline Management</h1>
                    <p className="text-sm text-muted-foreground">
                        Configure runs and view detailed execution history.
                    </p>
                </div>

                {/* Tabs */}
                <div className="flex gap-1 p-1 bg-muted rounded-lg w-fit">
                    <button
                        onClick={() => setActiveTab('run')}
                        className={cn(
                            'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                            activeTab === 'run'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        )}
                    >
                        Run Configuration
                    </button>
                    <button
                        onClick={() => setActiveTab('history')}
                        className={cn(
                            'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                            activeTab === 'history'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        )}
                    >
                        Run History
                    </button>
                </div>

                {activeTab === 'run' && (
                    <div className="grid lg:grid-cols-2 gap-6">
                        {/* Run Configuration */}
                        <div className="rounded-xl border border-border bg-card p-6">
                            <h2 className="font-semibold text-foreground mb-4">Run Configuration</h2>

                            {/* API Data Sources */}
                            <div className="mb-6">
                                <label className="block text-xs font-semibold uppercase text-muted-foreground mb-3">
                                    Core API Sources (Fast)
                                </label>
                                <div className="space-y-2">
                                    {apiSources.map((source) => (
                                        <label
                                            key={source.id}
                                            className={cn(
                                                'flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors',
                                                selectedConnectors.includes(source.type)
                                                    ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/10'
                                                    : 'border-border hover:bg-muted/50'
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selectedConnectors.includes(source.type)}
                                                onChange={() => toggleConnector(source.type)}
                                                className="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                            />
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">{source.name}</p>
                                                    <span className="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">API</span>
                                                </div>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {/* Scraper Sources */}
                            <div className="mb-6">
                                <label className="block text-xs font-semibold uppercase text-muted-foreground mb-3">
                                    Web Scrapers (Slower)
                                </label>
                                <div className="space-y-2 max-h-64 overflow-y-auto">
                                    {scraperSources.map((source) => (
                                        <label
                                            key={source.id}
                                            className={cn(
                                                'flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors',
                                                selectedConnectors.includes(source.type)
                                                    ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/10'
                                                    : 'border-border hover:bg-muted/50'
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selectedConnectors.includes(source.type)}
                                                onChange={() => toggleConnector(source.type)}
                                                className="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                            />
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">{source.name}</p>
                                                    <span className="text-xs px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">Scraper</span>
                                                </div>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {/* Run Button */}
                            <button
                                onClick={handleRunPipeline}
                                disabled={isRunning || selectedConnectors.length === 0}
                                className="w-full rounded-lg bg-primary px-4 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                {isRunning ? (
                                    <>
                                        <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Running Pipeline...
                                    </>
                                ) : (
                                    <>
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Run Pipeline Now
                                    </>
                                )}
                            </button>
                        </div>

                        {/* Info Panel */}
                        <div className="space-y-6">
                            <div className="rounded-xl border border-border bg-card p-6">
                                <h3 className="font-semibold mb-2">Pipeline Info</h3>
                                <p className="text-sm text-muted-foreground">
                                    Core APIs usually finish in seconds. Web scrapers may take 2-5 minutes depending on the target site speed. Results will appear in History.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'history' && (
                    <div className="rounded-xl border border-border bg-card overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3 text-left">ID</th>
                                        <th className="px-4 py-3 text-left">Status</th>
                                        <th className="px-4 py-3 text-right">Processsed</th>
                                        <th className="px-4 py-3 text-left">Started</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {runs.data.length === 0 ? (
                                        <tr><td colSpan={5} className="p-4 text-center">No runs found.</td></tr>
                                    ) : (
                                        runs.data.map((run) => (
                                            <tr key={run.id} className="hover:bg-muted/30">
                                                <td className="px-4 py-3 font-medium">#{run.id}</td>
                                                <td className="px-4 py-3">
                                                    <span className={cn('px-2 py-1 rounded-full text-xs', statusStyles[run.status])}>
                                                        {run.status}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right">{run.records_processed}</td>
                                                <td className="px-4 py-3">{formatDate(run.started_at)}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <Link
                                                        href={`/pipeline/history/${run.id}`}
                                                        className="text-primary hover:underline font-medium"
                                                    >
                                                        View Details &rarr;
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* AI Insights Modal */}
                {insightsToShow && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-card rounded-xl border border-border max-w-2xl w-full max-h-[80vh] overflow-hidden">
                            <div className="p-4 border-b border-border flex items-center justify-between bg-gradient-to-r from-primary/10 to-purple-500/10">
                                <div className="flex items-center gap-2">
                                    <div className="h-8 w-8 rounded-lg bg-gradient-to-br from-primary to-purple-500 flex items-center justify-center">
                                        <svg className="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-foreground">AI Pipeline Analysis</h3>
                                        <p className="text-xs text-muted-foreground">Run #{insightsToShow.id}</p>
                                    </div>
                                </div>
                                <button
                                    onClick={() => setInsightsToShow(null)}
                                    className="p-2 rounded-lg hover:bg-muted transition-colors"
                                >
                                    <svg className="h-5 w-5 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <div className="p-6 overflow-y-auto max-h-[60vh]">
                                <div className="prose prose-sm dark:prose-invert max-w-none">
                                    <p className="whitespace-pre-wrap text-foreground">{insightsToShow.insights}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
