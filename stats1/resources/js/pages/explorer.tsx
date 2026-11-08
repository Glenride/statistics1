import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import AIChat from '@/components/explorer/AIChat';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Explorer',
        href: '/explorer',
    },
];

interface DataRow {
    id: number;
    date: string;
    candidate: string | null;
    party: string | null;
    poll_avg: number | null;
    cash_on_hand: number | null;
    total_receipts: number | null;
    total_disbursements: number | null;
    inflation_rate: number | null;
    unemployment_rate: number | null;
    gdp_growth: number | null;
    consumer_confidence: number | null;
    source: string | null;
}

interface ExplorerProps {
    data: {
        data: DataRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        candidates: string[];
        parties: string[];
    };
    currentFilters: {
        start_date?: string;
        end_date?: string;
        candidate?: string;
        party?: string;
    };
}

export default function Explorer({ data, filters, currentFilters }: ExplorerProps) {
    const [activeTab, setActiveTab] = useState<'browse' | 'analysis' | 'ai'>('browse');
    const [localFilters, setLocalFilters] = useState(currentFilters);
    const [correlationResult, setCorrelationResult] = useState<{
        correlation: number;
        n: number;
    } | null>(null);
    const [correlationVars, setCorrelationVars] = useState({
        variable_x: 'inflation_rate',
        variable_y: 'cash_on_hand',
    });

    const partyColors: Record<string, string> = {
        R: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        D: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        I: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    };

    const formatCurrency = (value: number | string | null | undefined) => {
        if (value === null || value === undefined) return '--';
        const num = typeof value === 'string' ? parseFloat(value) : value;
        if (isNaN(num)) return '--';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(num);
    };

    const formatPercent = (value: number | string | null | undefined) => {
        if (value === null || value === undefined) return '--';
        const num = typeof value === 'string' ? parseFloat(value) : value;
        if (isNaN(num)) return '--';
        return `${num.toFixed(1)}%`;
    };

    const formatDate = (date: string | null | undefined) => {
        if (!date) return '--';
        try {
            return new Date(date).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            });
        } catch {
            return '--';
        }
    };

    const handleFilter = () => {
        router.get('/explorer', localFilters, { preserveState: true });
    };

    const handleClearFilters = () => {
        setLocalFilters({});
        router.get('/explorer', {}, { preserveState: true });
    };

    const handleExport = (format: 'csv' | 'json') => {
        const params = new URLSearchParams({
            format,
            ...localFilters,
        });
        window.location.href = `/explorer/export?${params.toString()}`;
    };

    const handleCorrelation = async () => {
        const response = await fetch('/explorer/correlation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(correlationVars),
        });
        const result = await response.json();
        setCorrelationResult(result);
    };

    const availableVariables = [
        { value: 'poll_avg', label: 'Poll Average' },
        { value: 'cash_on_hand', label: 'Cash on Hand' },
        { value: 'total_receipts', label: 'Total Receipts' },
        { value: 'total_disbursements', label: 'Disbursements' },
        { value: 'inflation_rate', label: 'Inflation Rate' },
        { value: 'unemployment_rate', label: 'Unemployment' },
        { value: 'gdp_growth', label: 'GDP Growth' },
        { value: 'consumer_confidence', label: 'Consumer Confidence' },
    ];

    const getCorrelationStrength = (r: number) => {
        const absR = Math.abs(r);
        if (absR >= 0.7) return { label: 'Strong', color: 'text-green-500' };
        if (absR >= 0.4) return { label: 'Moderate', color: 'text-yellow-500' };
        if (absR >= 0.2) return { label: 'Weak', color: 'text-orange-500' };
        return { label: 'Very Weak', color: 'text-red-500' };
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Data Explorer" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Data Explorer</h1>
                        <p className="text-sm text-muted-foreground">
                            Browse the unified dataset and run correlation analysis
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={() => handleExport('csv')}
                            className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export CSV
                        </button>
                        <button
                            onClick={() => handleExport('json')}
                            className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
                        >
                            Export JSON
                        </button>
                    </div>
                </div>

                {/* Tabs */}
                <div className="flex gap-1 p-1 bg-muted rounded-lg w-fit">
                    <button
                        onClick={() => setActiveTab('browse')}
                        className={cn(
                            'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                            activeTab === 'browse'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        )}
                    >
                        Browse Data
                    </button>
                    <button
                        onClick={() => setActiveTab('analysis')}
                        className={cn(
                            'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                            activeTab === 'analysis'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        )}
                    >
                        Correlation Analysis
                    </button>
                    <button
                        onClick={() => setActiveTab('ai')}
                        className={cn(
                            'px-4 py-2 text-sm font-medium rounded-md transition-colors flex items-center gap-1.5',
                            activeTab === 'ai'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        )}
                    >
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        AI Insights
                    </button>
                </div>

                {activeTab === 'browse' && (
                    <>
                        {/* Filters */}
                        <div className="rounded-xl border border-border bg-card p-4">
                            <div className="flex flex-wrap gap-4 items-end">
                                <div>
                                    <label className="block text-xs text-muted-foreground mb-1">Start Date</label>
                                    <input
                                        type="date"
                                        value={localFilters.start_date || ''}
                                        onChange={(e) => setLocalFilters({ ...localFilters, start_date: e.target.value })}
                                        className="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs text-muted-foreground mb-1">End Date</label>
                                    <input
                                        type="date"
                                        value={localFilters.end_date || ''}
                                        onChange={(e) => setLocalFilters({ ...localFilters, end_date: e.target.value })}
                                        className="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs text-muted-foreground mb-1">Candidate</label>
                                    <select
                                        value={localFilters.candidate || ''}
                                        onChange={(e) => setLocalFilters({ ...localFilters, candidate: e.target.value })}
                                        className="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        <option value="">All Candidates</option>
                                        {filters.candidates.map((c) => (
                                            <option key={c} value={c}>{c}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs text-muted-foreground mb-1">Party</label>
                                    <select
                                        value={localFilters.party || ''}
                                        onChange={(e) => setLocalFilters({ ...localFilters, party: e.target.value })}
                                        className="rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        <option value="">All Parties</option>
                                        {filters.parties.map((p) => (
                                            <option key={p} value={p}>{p}</option>
                                        ))}
                                    </select>
                                </div>
                                <button
                                    onClick={handleFilter}
                                    className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                                >
                                    Apply Filters
                                </button>
                                <button
                                    onClick={handleClearFilters}
                                    className="rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>

                        {/* Data Table */}
                        <div className="rounded-xl border border-border bg-card overflow-hidden">
                            <div className="p-4 border-b border-border bg-muted/30">
                                <p className="text-sm text-muted-foreground">
                                    Showing {data.data.length} of {data.total} records
                                </p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50">
                                        <tr>
                                            <th className="text-left px-4 py-3 font-medium text-muted-foreground">Date</th>
                                            <th className="text-left px-4 py-3 font-medium text-muted-foreground">Candidate</th>
                                            <th className="text-left px-4 py-3 font-medium text-muted-foreground">Party</th>
                                            <th className="text-right px-4 py-3 font-medium text-muted-foreground">Poll Avg</th>
                                            <th className="text-right px-4 py-3 font-medium text-muted-foreground">Cash on Hand</th>
                                            <th className="text-right px-4 py-3 font-medium text-muted-foreground">Inflation</th>
                                            <th className="text-right px-4 py-3 font-medium text-muted-foreground">Unemployment</th>
                                            <th className="text-left px-4 py-3 font-medium text-muted-foreground">Source</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {data.data.length === 0 ? (
                                            <tr>
                                                <td colSpan={8} className="px-4 py-12 text-center text-muted-foreground">
                                                    No data found. Run a pipeline to populate the unified dataset.
                                                </td>
                                            </tr>
                                        ) : (
                                            data.data.map((row) => (
                                                <tr key={row.id} className="hover:bg-muted/30 transition-colors">
                                                    <td className="px-4 py-3 text-foreground">{formatDate(row.date)}</td>
                                                    <td className="px-4 py-3 font-medium text-foreground">{row.candidate || '--'}</td>
                                                    <td className="px-4 py-3">
                                                        {row.party ? (
                                                            <span className={cn('px-2 py-0.5 rounded text-xs font-medium', partyColors[row.party])}>
                                                                {row.party}
                                                            </span>
                                                        ) : '--'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right text-foreground">{formatPercent(row.poll_avg)}</td>
                                                    <td className="px-4 py-3 text-right text-foreground">{formatCurrency(row.cash_on_hand)}</td>
                                                    <td className="px-4 py-3 text-right text-foreground">{formatPercent(row.inflation_rate)}</td>
                                                    <td className="px-4 py-3 text-right text-foreground">{formatPercent(row.unemployment_rate)}</td>
                                                    <td className="px-4 py-3 text-muted-foreground uppercase text-xs">{row.source || '--'}</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {/* Pagination */}
                            {data.last_page > 1 && (
                                <div className="p-4 border-t border-border flex justify-between items-center">
                                    <p className="text-sm text-muted-foreground">
                                        Page {data.current_page} of {data.last_page}
                                    </p>
                                    <div className="flex gap-2">
                                        <button
                                            disabled={data.current_page === 1}
                                            onClick={() => router.get('/explorer', { ...localFilters, page: data.current_page - 1 })}
                                            className="px-3 py-1 rounded border border-border text-sm hover:bg-muted disabled:opacity-50"
                                        >
                                            Previous
                                        </button>
                                        <button
                                            disabled={data.current_page === data.last_page}
                                            onClick={() => router.get('/explorer', { ...localFilters, page: data.current_page + 1 })}
                                            className="px-3 py-1 rounded border border-border text-sm hover:bg-muted disabled:opacity-50"
                                        >
                                            Next
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </>
                )}

                {activeTab === 'analysis' && (
                    <div className="grid lg:grid-cols-2 gap-6">
                        {/* Query Builder */}
                        <div className="rounded-xl border border-border bg-card p-6">
                            <h2 className="font-semibold text-foreground mb-4">Correlation Query</h2>
                            <p className="text-sm text-muted-foreground mb-6">
                                Test relationships between variables. For example: "Does inflation impact fundraising?"
                            </p>

                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-foreground mb-2">
                                        Variable X (Independent)
                                    </label>
                                    <select
                                        value={correlationVars.variable_x}
                                        onChange={(e) => setCorrelationVars({ ...correlationVars, variable_x: e.target.value })}
                                        className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        {availableVariables.map((v) => (
                                            <option key={v.value} value={v.value}>{v.label}</option>
                                        ))}
                                    </select>
                                </div>

                                <div className="flex justify-center">
                                    <div className="px-4 py-2 rounded-full bg-muted text-muted-foreground text-sm font-medium">
                                        vs
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-foreground mb-2">
                                        Variable Y (Dependent)
                                    </label>
                                    <select
                                        value={correlationVars.variable_y}
                                        onChange={(e) => setCorrelationVars({ ...correlationVars, variable_y: e.target.value })}
                                        className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                    >
                                        {availableVariables.map((v) => (
                                            <option key={v.value} value={v.value}>{v.label}</option>
                                        ))}
                                    </select>
                                </div>

                                <button
                                    onClick={handleCorrelation}
                                    className="w-full rounded-lg bg-primary px-4 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                                >
                                    Calculate Correlation
                                </button>
                            </div>
                        </div>

                        {/* Results */}
                        <div className="rounded-xl border border-border bg-card p-6">
                            <h2 className="font-semibold text-foreground mb-4">Results</h2>

                            {correlationResult ? (
                                <div className="space-y-6">
                                    <div className="text-center py-8">
                                        <p className="text-sm text-muted-foreground mb-2">Correlation Coefficient (r)</p>
                                        <p className="text-5xl font-bold text-foreground">
                                            {correlationResult.correlation.toFixed(4)}
                                        </p>
                                        <p className={cn('text-sm font-medium mt-2', getCorrelationStrength(correlationResult.correlation).color)}>
                                            {getCorrelationStrength(correlationResult.correlation).label} {correlationResult.correlation > 0 ? 'Positive' : correlationResult.correlation < 0 ? 'Negative' : ''} Correlation
                                        </p>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="p-4 rounded-lg bg-muted/50 text-center">
                                            <p className="text-xs text-muted-foreground">Data Points</p>
                                            <p className="text-xl font-semibold text-foreground">{correlationResult.n}</p>
                                        </div>
                                        <div className="p-4 rounded-lg bg-muted/50 text-center">
                                            <p className="text-xs text-muted-foreground">R-Squared</p>
                                            <p className="text-xl font-semibold text-foreground">
                                                {(correlationResult.correlation ** 2 * 100).toFixed(1)}%
                                            </p>
                                        </div>
                                    </div>

                                    <div className="p-4 rounded-lg bg-primary/5 border border-primary/20">
                                        <p className="text-sm text-foreground">
                                            <strong>Interpretation:</strong> {' '}
                                            {Math.abs(correlationResult.correlation) >= 0.4
                                                ? `There appears to be a meaningful relationship between ${availableVariables.find(v => v.value === correlationVars.variable_x)?.label} and ${availableVariables.find(v => v.value === correlationVars.variable_y)?.label}.`
                                                : `The relationship between ${availableVariables.find(v => v.value === correlationVars.variable_x)?.label} and ${availableVariables.find(v => v.value === correlationVars.variable_y)?.label} appears to be weak.`
                                            }
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-center py-12 text-muted-foreground">
                                    <svg className="mx-auto h-12 w-12 mb-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                                    </svg>
                                    <p className="text-sm">Select variables and click "Calculate Correlation"</p>
                                    <p className="text-xs mt-1">to see the relationship strength</p>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {activeTab === 'ai' && (
                    <div className="grid lg:grid-cols-3 gap-6">
                        {/* AI Chat */}
                        <div className="lg:col-span-2">
                            <AIChat filters={localFilters} />
                        </div>

                        {/* Tips Panel */}
                        <div className="rounded-xl border border-border bg-card p-6">
                            <h3 className="font-semibold text-foreground mb-4 flex items-center gap-2">
                                <svg className="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Analysis Tips
                            </h3>
                            <div className="space-y-4">
                                <div className="p-3 rounded-lg bg-muted/50">
                                    <p className="text-sm font-medium text-foreground mb-1">Ask about trends</p>
                                    <p className="text-xs text-muted-foreground">
                                        "What are the polling trends for each party over time?"
                                    </p>
                                </div>
                                <div className="p-3 rounded-lg bg-muted/50">
                                    <p className="text-sm font-medium text-foreground mb-1">Find correlations</p>
                                    <p className="text-xs text-muted-foreground">
                                        "Is there a relationship between inflation and fundraising?"
                                    </p>
                                </div>
                                <div className="p-3 rounded-lg bg-muted/50">
                                    <p className="text-sm font-medium text-foreground mb-1">Compare candidates</p>
                                    <p className="text-xs text-muted-foreground">
                                        "Compare the financial positions of all candidates."
                                    </p>
                                </div>
                                <div className="p-3 rounded-lg bg-muted/50">
                                    <p className="text-sm font-medium text-foreground mb-1">Identify anomalies</p>
                                    <p className="text-xs text-muted-foreground">
                                        "Are there any unusual patterns in the data?"
                                    </p>
                                </div>
                            </div>

                            <div className="mt-6 p-3 rounded-lg bg-primary/5 border border-primary/20">
                                <p className="text-xs text-muted-foreground">
                                    <strong className="text-foreground">Pro tip:</strong> The AI has access to your full dataset. Use the filters to focus analysis on specific time periods or candidates.
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
