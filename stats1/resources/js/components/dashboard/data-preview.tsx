import { cn } from '@/lib/utils';

interface DataRow {
    id: number;
    date: string;
    candidate: string | null;
    party: string | null;
    poll_avg: number | string | null;
    cash_on_hand: number | string | null;
    inflation_rate: number | string | null;
    unemployment_rate: number | string | null;
}

interface DataPreviewProps {
    data: DataRow[];
    className?: string;
}

export function DataPreview({ data, className }: DataPreviewProps) {
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

    const partyColors: Record<string, string> = {
        R: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        D: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        I: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    };

    if (!data || data.length === 0) {
        return (
            <div className={cn('rounded-xl border border-border bg-card p-5', className)}>
                <h3 className="font-semibold text-foreground mb-4">Data Preview</h3>
                <div className="text-center py-12 text-muted-foreground">
                    <svg className="mx-auto h-12 w-12 mb-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                    </svg>
                    <p className="text-sm">No data yet</p>
                    <p className="text-xs mt-1">Run a pipeline to populate the unified dataset</p>
                </div>
            </div>
        );
    }

    return (
        <div className={cn('rounded-xl border border-border bg-card p-5', className)}>
            <div className="flex items-center justify-between mb-4">
                <h3 className="font-semibold text-foreground">Data Preview</h3>
                <span className="text-xs text-muted-foreground">
                    Showing {data.length} most recent records
                </span>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border">
                            <th className="text-left py-2 px-3 font-medium text-muted-foreground">Date</th>
                            <th className="text-left py-2 px-3 font-medium text-muted-foreground">Candidate</th>
                            <th className="text-left py-2 px-3 font-medium text-muted-foreground">Party</th>
                            <th className="text-right py-2 px-3 font-medium text-muted-foreground">Poll Avg</th>
                            <th className="text-right py-2 px-3 font-medium text-muted-foreground">Cash on Hand</th>
                            <th className="text-right py-2 px-3 font-medium text-muted-foreground">Inflation</th>
                            <th className="text-right py-2 px-3 font-medium text-muted-foreground">Unemployment</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((row) => (
                            <tr key={row.id} className="border-b border-border/50 hover:bg-muted/30 transition-colors">
                                <td className="py-2 px-3 text-foreground">{formatDate(row.date)}</td>
                                <td className="py-2 px-3 text-foreground font-medium">{row.candidate || '--'}</td>
                                <td className="py-2 px-3">
                                    {row.party ? (
                                        <span className={cn('px-2 py-0.5 rounded text-xs font-medium', partyColors[row.party] || 'bg-gray-100 text-gray-700')}>
                                            {row.party}
                                        </span>
                                    ) : '--'}
                                </td>
                                <td className="py-2 px-3 text-right text-foreground">{formatPercent(row.poll_avg)}</td>
                                <td className="py-2 px-3 text-right text-foreground">{formatCurrency(row.cash_on_hand)}</td>
                                <td className="py-2 px-3 text-right text-foreground">{formatPercent(row.inflation_rate)}</td>
                                <td className="py-2 px-3 text-right text-foreground">{formatPercent(row.unemployment_rate)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
