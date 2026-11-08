import { cn } from '@/lib/utils';

interface ActivityItem {
    id: number;
    status: 'completed' | 'running' | 'failed' | 'pending';
    started_at: string | null;
    completed_at: string | null;
    records_processed: number;
    triggered_by: string;
}

interface ActivityFeedProps {
    activities: ActivityItem[];
    className?: string;
}

export function ActivityFeed({ activities, className }: ActivityFeedProps) {
    const statusStyles = {
        completed: 'bg-green-500',
        running: 'bg-blue-500 animate-pulse',
        failed: 'bg-red-500',
        pending: 'bg-yellow-500',
    };

    const statusIcons = {
        completed: (
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
        ),
        running: (
            <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        ),
        failed: (
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
        ),
        pending: (
            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        ),
    };

    const formatTime = (date: string | null) => {
        if (!date) return '--';
        return new Date(date).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    if (activities.length === 0) {
        return (
            <div className={cn('rounded-xl border border-border bg-card p-5', className)}>
                <h3 className="font-semibold text-foreground mb-4">Recent Activity</h3>
                <div className="text-center py-8 text-muted-foreground">
                    <svg className="mx-auto h-12 w-12 mb-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <p className="text-sm">No pipeline runs yet</p>
                    <p className="text-xs mt-1">Run your first pipeline to see activity here</p>
                </div>
            </div>
        );
    }

    return (
        <div className={cn('rounded-xl border border-border bg-card p-5', className)}>
            <h3 className="font-semibold text-foreground mb-4">Recent Activity</h3>
            <div className="space-y-3">
                {activities.map((activity) => (
                    <div
                        key={activity.id}
                        className="flex items-center gap-3 p-3 rounded-lg bg-muted/30 hover:bg-muted/50 transition-colors"
                    >
                        <div
                            className={cn(
                                'flex h-8 w-8 items-center justify-center rounded-full text-white',
                                statusStyles[activity.status]
                            )}
                        >
                            {statusIcons[activity.status]}
                        </div>
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-foreground">
                                Pipeline Run #{activity.id}
                            </p>
                            <p className="text-xs text-muted-foreground truncate">
                                {activity.triggered_by === 'manual' ? 'Manual trigger' : 'Scheduled'} · {formatTime(activity.started_at)}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-sm font-medium text-foreground">
                                {activity.records_processed.toLocaleString()}
                            </p>
                            <p className="text-xs text-muted-foreground">records</p>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
