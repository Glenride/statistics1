import { useState } from 'react';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import axios from 'axios';

interface DataSource {
    id: number;
    type: string;
    name: string;
    status: 'active' | 'inactive' | 'error';
    config: Record<string, unknown>;
    record_count: number;
    data_quality_score: number;
    last_sync_at: string | null;
    last_error: string | null;
    category?: string;
}

interface ConnectorCardProps {
    dataSource: DataSource;
    className?: string;
}

// Sources that use scrapers and don't require API keys
const SCRAPER_SOURCES = ['rcp', 'press_monitor', 'pdf_hunter', 'ap_elections', 'ballotpedia', 'nationbuilder', 'cicero', 'polling', 'usagov'];

export function ConnectorCard({ dataSource, className }: ConnectorCardProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [isTesting, setIsTesting] = useState(false);
    const [isSyncing, setIsSyncing] = useState(false);
    const [syncResult, setSyncResult] = useState<{ success: boolean; message: string } | null>(null);
    const [config, setConfig] = useState(dataSource.config || {});

    // Check if this source uses a scraper (no API key needed)
    const isScraperSource = SCRAPER_SOURCES.includes(dataSource.type);

    const statusStyles = {
        active: 'bg-green-500',
        inactive: 'bg-gray-400',
        error: 'bg-red-500',
    };

    const statusLabels = {
        active: 'Connected',
        inactive: 'Not Configured',
        error: 'Error',
    };

    // Default scraper icon
    const scraperIcon = (
        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
        </svg>
    );

    const typeIcons: Record<string, React.ReactNode> = {
        fec: (
            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
        fred: (
            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
        ),
        polling: scraperIcon,
        rcp: scraperIcon,
        press_monitor: scraperIcon,
        pdf_hunter: scraperIcon,
        ap_elections: scraperIcon,
        ballotpedia: scraperIcon,
        nationbuilder: scraperIcon,
        cicero: scraperIcon,
        usagov: scraperIcon,
    };

    const typeColors: Record<string, string> = {
        fec: 'bg-primary/10 text-primary',
        fred: 'bg-chart-2/10 text-chart-2',
        polling: 'bg-chart-3/10 text-chart-3',
        rcp: 'bg-orange-500/10 text-orange-500',
        press_monitor: 'bg-blue-500/10 text-blue-500',
        pdf_hunter: 'bg-purple-500/10 text-purple-500',
        ap_elections: 'bg-red-500/10 text-red-500',
        ballotpedia: 'bg-teal-500/10 text-teal-500',
        nationbuilder: 'bg-green-500/10 text-green-500',
        cicero: 'bg-indigo-500/10 text-indigo-500',
        usagov: 'bg-pink-500/10 text-pink-500',
    };

    const handleSave = () => {
        router.patch(`/data-sources/${dataSource.id}`, { config: config as Record<string, string> }, {
            onSuccess: () => setIsEditing(false),
        });
    };

    const handleTestConnection = () => {
        setIsTesting(true);
        router.post(`/data-sources/${dataSource.id}/test`, {}, {
            onFinish: () => setIsTesting(false),
        });
    };

    const handleSync = async () => {
        setIsSyncing(true);
        setSyncResult(null);
        try {
            const response = await axios.post(`/data-sources/sync/${dataSource.type}`);
            setSyncResult({
                success: true,
                message: `Synced! ${JSON.stringify(response.data.result)}`
            });
            router.reload({ only: ['dataSources', 'groupedSources'] });
        } catch (error: unknown) {
            const axiosError = error as { response?: { data?: { error?: string } } };
            setSyncResult({
                success: false,
                message: axiosError.response?.data?.error || 'Sync failed'
            });
        } finally {
            setIsSyncing(false);
        }
    };

    const formatLastSync = (date: string | null) => {
        if (!date) return 'Never synced';
        return new Date(date).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <div className={cn('rounded-xl border border-border bg-card shadow-sm', className)}>
            {/* Header */}
            <div className="p-5 border-b border-border">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <div className={cn('flex h-12 w-12 items-center justify-center rounded-xl', typeColors[dataSource.type])}>
                            {typeIcons[dataSource.type]}
                        </div>
                        <div>
                            <h3 className="font-semibold text-foreground">{dataSource.name}</h3>
                            <p className="text-xs text-muted-foreground uppercase tracking-wide">
                                {dataSource.type} Connector
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className={cn('h-2.5 w-2.5 rounded-full', statusStyles[dataSource.status])} />
                        <span className="text-sm text-muted-foreground">{statusLabels[dataSource.status]}</span>
                    </div>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-3 divide-x divide-border border-b border-border">
                <div className="p-4 text-center">
                    <p className="text-xs text-muted-foreground">Records</p>
                    <p className="text-lg font-semibold text-foreground">{dataSource.record_count.toLocaleString()}</p>
                </div>
                <div className="p-4 text-center">
                    <p className="text-xs text-muted-foreground">Quality</p>
                    <p className="text-lg font-semibold text-foreground">{dataSource.data_quality_score}%</p>
                </div>
                <div className="p-4 text-center">
                    <p className="text-xs text-muted-foreground">Last Sync</p>
                    <p className="text-sm font-medium text-foreground">{formatLastSync(dataSource.last_sync_at)}</p>
                </div>
            </div>

            {/* Configuration */}
            <div className="p-5">
                {/* Show scraper ready message or error as appropriate */}
                {isScraperSource ? (
                    <div className="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                        <p className="text-sm text-green-700 dark:text-green-400">✓ Ready to sync - no API key needed!</p>
                    </div>
                ) : dataSource.last_error && (
                    <div className="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <p className="text-sm text-red-700 dark:text-red-400">{dataSource.last_error}</p>
                    </div>
                )}

                {/* Sync result feedback */}
                {syncResult && (
                    <div className={cn(
                        'mb-4 p-3 rounded-lg border',
                        syncResult.success
                            ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
                            : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
                    )}>
                        <p className={cn(
                            'text-sm',
                            syncResult.success ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'
                        )}>{syncResult.message}</p>
                    </div>
                )}

                {isEditing ? (
                    <div className="space-y-4">
                        {/* Only show API key field for sources that need it */}
                        {!isScraperSource && (
                            <div>
                                <label className="block text-sm font-medium text-foreground mb-1">
                                    API Key
                                </label>
                                <input
                                    type="password"
                                    value={(config.api_key as string) || ''}
                                    onChange={(e) => setConfig({ ...config, api_key: e.target.value })}
                                    placeholder="Enter your API key"
                                    className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>
                        )}

                        {dataSource.type === 'fec' && (
                            <div>
                                <label className="block text-sm font-medium text-foreground mb-1">
                                    Candidates to Track
                                </label>
                                <input
                                    type="text"
                                    value={(config.candidates as string[])?.join(', ') || ''}
                                    onChange={(e) => setConfig({ ...config, candidates: e.target.value.split(',').map(s => s.trim()) })}
                                    placeholder="e.g., Biden, Trump, Sanders"
                                    className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>
                        )}

                        {dataSource.type === 'fred' && (
                            <div>
                                <label className="block text-sm font-medium text-foreground mb-1">
                                    Economic Indicators
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    {['CPIAUCSL', 'UNRATE', 'GDP', 'UMCSENT'].map((indicator) => {
                                        const indicators = (config.indicators as string[]) || [];
                                        const isSelected = indicators.includes(indicator);
                                        return (
                                            <button
                                                key={indicator}
                                                type="button"
                                                onClick={() => {
                                                    const newIndicators = isSelected
                                                        ? indicators.filter(i => i !== indicator)
                                                        : [...indicators, indicator];
                                                    setConfig({ ...config, indicators: newIndicators });
                                                }}
                                                className={cn(
                                                    'px-3 py-1 rounded-full text-xs font-medium transition-colors',
                                                    isSelected
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                                )}
                                            >
                                                {indicator}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {dataSource.type === 'polling' && (
                            <div>
                                <label className="block text-sm font-medium text-foreground mb-1">
                                    Data Sources
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    {['538', 'rcp'].map((source) => {
                                        const sources = (config.sources as string[]) || [];
                                        const isSelected = sources.includes(source);
                                        return (
                                            <button
                                                key={source}
                                                type="button"
                                                onClick={() => {
                                                    const newSources = isSelected
                                                        ? sources.filter(s => s !== source)
                                                        : [...sources, source];
                                                    setConfig({ ...config, sources: newSources });
                                                }}
                                                className={cn(
                                                    'px-3 py-1 rounded-full text-xs font-medium transition-colors',
                                                    isSelected
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                                )}
                                            >
                                                {source === '538' ? 'FiveThirtyEight' : 'RealClearPolitics'}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        <div className="flex gap-2 pt-2">
                            <button
                                onClick={handleSave}
                                className="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            >
                                Save Configuration
                            </button>
                            <button
                                onClick={() => {
                                    setConfig(dataSource.config || {});
                                    setIsEditing(false);
                                }}
                                className="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-muted"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="flex gap-2">
                        {/* Sync button for scraper sources */}
                        {isScraperSource ? (
                            <button
                                onClick={handleSync}
                                disabled={isSyncing}
                                className="flex-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors disabled:opacity-50"
                            >
                                {isSyncing ? 'Syncing...' : 'Sync Now'}
                            </button>
                        ) : (
                            <>
                                <button
                                    onClick={() => setIsEditing(true)}
                                    className="flex-1 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
                                >
                                    Configure
                                </button>
                                <button
                                    onClick={handleTestConnection}
                                    disabled={isTesting}
                                    className="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50"
                                >
                                    {isTesting ? 'Testing...' : 'Test Connection'}
                                </button>
                            </>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
