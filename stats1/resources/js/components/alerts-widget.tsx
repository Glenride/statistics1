import { useState, useEffect, useCallback } from 'react';
import { Bell, CheckCircle, AlertCircle, FileText, TrendingUp, X, RefreshCw } from 'lucide-react';
import axios from 'axios';

interface Alert {
    id: number;
    type: string;
    source: string;
    title: string;
    description: string | null;
    metadata: Record<string, unknown> | null;
    is_read: boolean;
    created_at: string;
}

interface AlertsWidgetProps {
    className?: string;
    maxAlerts?: number;
}

export function AlertsWidget({ className = '', maxAlerts = 5 }: AlertsWidgetProps) {
    const [alerts, setAlerts] = useState<Alert[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [isExpanded, setIsExpanded] = useState(false);
    const [isChecking, setIsChecking] = useState(false);

    const fetchAlerts = useCallback(async () => {
        setIsLoading(true);
        try {
            const response = await axios.get('/alerts', { params: { limit: maxAlerts } });
            setAlerts(response.data.alerts);
            setUnreadCount(response.data.unread_count);
        } catch (error) {
            console.error('Failed to fetch alerts:', error);
        } finally {
            setIsLoading(false);
        }
    }, [maxAlerts]);

    useEffect(() => {
        fetchAlerts();
        // Poll for new alerts every 30 seconds
        const interval = setInterval(fetchAlerts, 30000);
        return () => clearInterval(interval);
    }, [fetchAlerts]);

    const markAsRead = async (alertId: number) => {
        try {
            await axios.post(`/alerts/${alertId}/read`);
            setAlerts(prev => prev.map(a => a.id === alertId ? { ...a, is_read: true } : a));
            setUnreadCount(prev => Math.max(0, prev - 1));
        } catch (error) {
            console.error('Failed to mark alert as read:', error);
        }
    };

    const markAllRead = async () => {
        try {
            await axios.post('/alerts/read-all');
            setAlerts(prev => prev.map(a => ({ ...a, is_read: true })));
            setUnreadCount(0);
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    };

    const checkNow = async () => {
        setIsChecking(true);
        try {
            await axios.post('/alerts/check');
            await fetchAlerts();
        } catch (error) {
            console.error('Failed to check for updates:', error);
        } finally {
            setIsChecking(false);
        }
    };

    const getAlertIcon = (type: string) => {
        switch (type) {
            case 'new_poll':
                return <TrendingUp className="w-4 h-4 text-emerald-500" />;
            case 'page_change':
                return <AlertCircle className="w-4 h-4 text-amber-500" />;
            case 'new_pdf':
                return <FileText className="w-4 h-4 text-blue-500" />;
            default:
                return <Bell className="w-4 h-4 text-gray-500" />;
        }
    };

    const getAlertBadgeColor = (type: string) => {
        switch (type) {
            case 'new_poll':
                return 'bg-emerald-100 text-emerald-700';
            case 'page_change':
                return 'bg-amber-100 text-amber-700';
            case 'new_pdf':
                return 'bg-blue-100 text-blue-700';
            default:
                return 'bg-gray-100 text-gray-700';
        }
    };

    const formatTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        return `${diffDays}d ago`;
    };

    return (
        <div className={`bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm ${className}`}>
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 border-b border-zinc-200 dark:border-zinc-800">
                <div className="flex items-center gap-2">
                    <Bell className="w-5 h-5 text-zinc-600 dark:text-zinc-400" />
                    <h3 className="font-semibold text-zinc-900 dark:text-zinc-100">Alerts</h3>
                    {unreadCount > 0 && (
                        <span className="inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium bg-red-500 text-white rounded-full">
                            {unreadCount}
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <button
                        onClick={checkNow}
                        disabled={isChecking}
                        className="p-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors disabled:opacity-50"
                        title="Check for updates"
                    >
                        <RefreshCw className={`w-4 h-4 text-zinc-500 ${isChecking ? 'animate-spin' : ''}`} />
                    </button>
                    {unreadCount > 0 && (
                        <button
                            onClick={markAllRead}
                            className="text-xs text-blue-600 hover:text-blue-700 font-medium"
                        >
                            Mark all read
                        </button>
                    )}
                </div>
            </div>

            {/* Alert List */}
            <div className="divide-y divide-zinc-100 dark:divide-zinc-800">
                {isLoading && alerts.length === 0 ? (
                    <div className="px-4 py-8 text-center text-zinc-500">
                        Loading alerts...
                    </div>
                ) : alerts.length === 0 ? (
                    <div className="px-4 py-8 text-center text-zinc-500">
                        <Bell className="w-8 h-8 mx-auto mb-2 opacity-50" />
                        <p>No alerts yet</p>
                        <p className="text-xs mt-1">We'll notify you when new polls drop</p>
                    </div>
                ) : (
                    alerts.slice(0, isExpanded ? undefined : 3).map(alert => (
                        <div
                            key={alert.id}
                            className={`px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors ${!alert.is_read ? 'bg-blue-50/50 dark:bg-blue-900/10' : ''
                                }`}
                        >
                            <div className="flex items-start gap-3">
                                <div className="mt-0.5">
                                    {getAlertIcon(alert.type)}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-1">
                                        <span className={`inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded ${getAlertBadgeColor(alert.type)}`}>
                                            {alert.source}
                                        </span>
                                        <span className="text-xs text-zinc-400">
                                            {formatTime(alert.created_at)}
                                        </span>
                                        {!alert.is_read && (
                                            <span className="w-2 h-2 bg-blue-500 rounded-full"></span>
                                        )}
                                    </div>
                                    <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                        {alert.title}
                                    </p>
                                    {alert.description && (
                                        <p className="text-xs text-zinc-500 mt-0.5 truncate">
                                            {alert.description}
                                        </p>
                                    )}
                                </div>
                                {!alert.is_read && (
                                    <button
                                        onClick={() => markAsRead(alert.id)}
                                        className="p-1 hover:bg-zinc-200 dark:hover:bg-zinc-700 rounded transition-colors"
                                        title="Mark as read"
                                    >
                                        <CheckCircle className="w-4 h-4 text-zinc-400" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))
                )}
            </div>

            {/* Footer */}
            {alerts.length > 3 && (
                <div className="px-4 py-2 border-t border-zinc-200 dark:border-zinc-800">
                    <button
                        onClick={() => setIsExpanded(!isExpanded)}
                        className="text-sm text-blue-600 hover:text-blue-700 font-medium w-full text-center"
                    >
                        {isExpanded ? 'Show less' : `Show ${alerts.length - 3} more`}
                    </button>
                </div>
            )}
        </div>
    );
}
