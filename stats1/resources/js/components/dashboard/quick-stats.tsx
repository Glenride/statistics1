import { cn } from '@/lib/utils';

interface QuickStatsProps {
    totalRecords: number;
    lastSyncAt: string | null;
    dataQualityScore: number;
    activeConnectors: number;
    className?: string;
}

export function QuickStats({
    totalRecords,
    lastSyncAt,
    dataQualityScore,
    activeConnectors,
    className,
}: QuickStatsProps) {
    const formatLastSync = (date: string | null) => {
        if (!date) return 'Never';
        const d = new Date(date);
        return d.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getQualityColor = (score: number) => {
        if (score >= 80) return 'text-green-500';
        if (score >= 60) return 'text-yellow-500';
        return 'text-red-500';
    };

    const stats = [
        {
            label: 'Total Records',
            value: totalRecords.toLocaleString(),
            icon: (
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                </svg>
            ),
            color: 'text-primary',
        },
        {
            label: 'Last Sync',
            value: formatLastSync(lastSyncAt),
            icon: (
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            ),
            color: 'text-chart-2',
        },
        {
            label: 'Data Quality',
            value: `${dataQualityScore}%`,
            icon: (
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            ),
            color: getQualityColor(dataQualityScore),
        },
        {
            label: 'Active Connectors',
            value: `${activeConnectors}/3`,
            icon: (
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            ),
            color: activeConnectors === 3 ? 'text-green-500' : 'text-yellow-500',
        },
    ];

    return (
        <div className={cn('grid grid-cols-2 lg:grid-cols-4 gap-4', className)}>
            {stats.map((stat) => (
                <div
                    key={stat.label}
                    className="rounded-xl border border-border bg-card p-4 shadow-sm"
                >
                    <div className="flex items-center gap-2 mb-2">
                        <div className={cn('opacity-70', stat.color)}>{stat.icon}</div>
                        <span className="text-xs text-muted-foreground">{stat.label}</span>
                    </div>
                    <p className={cn('text-2xl font-bold', stat.color)}>{stat.value}</p>
                </div>
            ))}
        </div>
    );
}
