import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { StatusCard } from '@/components/dashboard/status-card';
import { QuickStats } from '@/components/dashboard/quick-stats';
import { ActivityFeed } from '@/components/dashboard/activity-feed';
import { DataPreview } from '@/components/dashboard/data-preview';
import { AlertsWidget } from '@/components/alerts-widget';
import { PollFeed } from '@/components/poll-feed';
import { ChartGenerator } from '@/components/chart-generator';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface DataSource {
    id: number;
    type: string;
    name: string;
    status: 'active' | 'inactive' | 'error';
    record_count: number;
    last_sync_at: string | null;
}

interface PipelineRun {
    id: number;
    status: 'completed' | 'running' | 'failed' | 'pending';
    started_at: string | null;
    completed_at: string | null;
    records_processed: number;
    triggered_by: string;
}

interface DataRow {
    id: number;
    date: string;
    candidate: string | null;
    party: string | null;
    poll_avg: number | null;
    cash_on_hand: number | null;
    inflation_rate: number | null;
    unemployment_rate: number | null;
}

interface DashboardProps {
    dataSources: DataSource[];
    recentRuns: PipelineRun[];
    stats: {
        totalRecords: number;
        lastSyncAt: string | null;
        dataQualityScore: number;
        activeConnectors: number;
    };
    dataPreview: DataRow[];
}

// Icons for data sources
const FecIcon = () => (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
);

const FredIcon = () => (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
    </svg>
);

const PollingIcon = () => (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
);

const getSourceIcon = (type: string) => {
    switch (type) {
        case 'fec':
            return <FecIcon />;
        case 'fred':
            return <FredIcon />;
        case 'polling':
            return <PollingIcon />;
        default:
            return <FecIcon />;
    }
};

const getSourceSubtitle = (type: string) => {
    switch (type) {
        case 'fec':
            return 'Campaign Finance';
        case 'fred':
            return 'Economic Indicators';
        case 'polling':
            return 'Public Opinion';
        default:
            return 'Data Source';
    }
};

export default function Dashboard({ dataSources, recentRuns, stats, dataPreview }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Dashboard</h1>
                        <p className="text-sm text-muted-foreground">
                            Monitor your data pipeline and explore unified datasets
                        </p>
                    </div>
                    <Link
                        href="/pipeline"
                        className="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                    >
                        <svg className="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Run Pipeline
                    </Link>
                </div>

                {/* Quick Stats */}
                <QuickStats
                    totalRecords={stats.totalRecords}
                    lastSyncAt={stats.lastSyncAt}
                    dataQualityScore={stats.dataQualityScore}
                    activeConnectors={stats.activeConnectors}
                />

                {/* Pipeline Status Cards */}
                <div>
                    <h2 className="text-lg font-semibold text-foreground mb-4">Data Connectors</h2>
                    <div className="grid md:grid-cols-3 gap-4">
                        {dataSources.map((source) => (
                            <StatusCard
                                key={source.id}
                                title={source.name}
                                subtitle={getSourceSubtitle(source.type)}
                                status={source.status}
                                recordCount={source.record_count}
                                lastSyncAt={source.last_sync_at}
                                icon={getSourceIcon(source.type)}
                            />
                        ))}
                    </div>
                </div>

                {/* Alerts and Poll Feed Row */}
                <div className="grid lg:grid-cols-3 gap-6">
                    <AlertsWidget className="lg:col-span-1" maxAlerts={10} />
                    <PollFeed className="lg:col-span-2" limit={15} />
                </div>

                {/* Chart Generator */}
                <ChartGenerator />

                {/* Activity and Preview */}
                <div className="grid lg:grid-cols-2 gap-6">
                    <ActivityFeed activities={recentRuns} />
                    <div className="flex flex-col gap-4">
                        <Link
                            href="/explorer"
                            className="text-sm text-primary hover:underline self-end"
                        >
                            View All Data →
                        </Link>
                    </div>
                </div>

                {/* Data Preview */}
                <DataPreview data={dataPreview} />
            </div>
        </AppLayout>
    );
}

