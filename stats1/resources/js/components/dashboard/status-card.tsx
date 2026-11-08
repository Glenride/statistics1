import { cn } from '@/lib/utils';

interface StatusCardProps {
    title: string;
    subtitle: string;
    status: 'active' | 'inactive' | 'error';
    recordCount: number;
    lastSyncAt: string | null;
    icon: React.ReactNode;
    className?: string;
}

export function StatusCard({
    title,
    subtitle,
    status,
    recordCount,
    lastSyncAt,
    icon,
    className,
}: StatusCardProps) {
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

    const formatLastSync = (date: string | null) => {
        if (!date) return 'Never synced';
        const d = new Date(date);
        const now = new Date();
        const diffMs = now.getTime() - d.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        return `${diffDays}d ago`;
    };

    return (
        <div
            className={cn(
                'rounded-xl border border-border bg-card p-5 shadow-sm hover:shadow-md transition-shadow',
                className
            )}
        >
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        {icon}
                    </div>
                    <div>
                        <h3 className="font-semibold text-foreground">{title}</h3>
                        <p className="text-xs text-muted-foreground">{subtitle}</p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <span
                        className={cn(
                            'h-2 w-2 rounded-full',
                            statusStyles[status]
                        )}
                    />
                    <span className="text-xs text-muted-foreground">
                        {statusLabels[status]}
                    </span>
                </div>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <p className="text-xs text-muted-foreground">Records</p>
                    <p className="text-lg font-semibold text-foreground">
                        {recordCount.toLocaleString()}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">Last Sync</p>
                    <p className="text-sm font-medium text-foreground">
                        {formatLastSync(lastSyncAt)}
                    </p>
                </div>
            </div>
        </div>
    );
}
