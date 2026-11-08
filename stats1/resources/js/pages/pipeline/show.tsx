import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { useState } from 'react';

interface ScrapedRecord {
    id: number;
    source: string;
    data_type: string;
    title: string;
    url: string | null;
    scraped_at: string;
    content: any;
}

interface UnifiedRecord {
    id: number;
    candidate: string;
    party: string;
    date: string;
    poll_avg: number | null;
    cash_on_hand: number | null;
    inflation_rate: number | null;
}

interface RunProps {
    run: {
        id: number;
        status: string;
        started_at: string;
        completed_at: string;
        records_processed: number;
        records_created: number;
        connectors_run: string[];
        ai_insights: string | null;
        duration: string;
    };
    scrapedData: {
        data: ScrapedRecord[];
        links: any[];
    };
    unifiedData: {
        data: UnifiedRecord[];
        links: any[];
    };
}

export default function Show({ run, scrapedData, unifiedData }: RunProps) {
    const [activeTab, setActiveTab] = useState<'scraped' | 'unified'>('scraped');

    const breadcrumbs = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Pipeline', href: '/pipeline' },
        { title: `Run #${run.id}`, href: `/pipeline/history/${run.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Pipeline Run #${run.id}`} />
            <div className="flex h-full flex-col gap-6 p-4 lg:p-6">

                {/* Header Section */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Pipeline Run #{run.id}</h1>
                        <p className="text-muted-foreground">
                            {new Date(run.started_at).toLocaleString()} • {run.duration} duration
                        </p>
                    </div>
                    <div className={cn(
                        'px-3 py-1 rounded-full text-sm font-medium',
                        run.status === 'completed' ? 'bg-green-100 text-green-700' :
                            run.status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'
                    )}>
                        {run.status.toUpperCase()}
                    </div>
                </div>

                {/* AI Insights Card */}
                {run.ai_insights && (
                    <div className="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-xl p-6">
                        <h3 className="font-semibold text-purple-900 dark:text-purple-300 mb-2 flex items-center gap-2">
                            ✨ AI Analysis
                        </h3>
                        <p className="whitespace-pre-wrap text-sm text-purple-800 dark:text-purple-200">
                            {run.ai_insights}
                        </p>
                    </div>
                )}

                {/* Data Tabs */}
                <div className="flex flex-col gap-4">
                    <div className="flex gap-2 border-b border-border">
                        <button
                            onClick={() => setActiveTab('scraped')}
                            className={cn(
                                'px-4 py-2 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'scraped'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            )}
                        >
                            Scraped Data ({scrapedData.data.length})
                        </button>
                        <button
                            onClick={() => setActiveTab('unified')}
                            className={cn(
                                'px-4 py-2 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'unified'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            )}
                        >
                            Unified Dataset ({unifiedData.data.length})
                        </button>
                    </div>

                    {/* Scraped Data Table */}
                    {activeTab === 'scraped' && (
                        <div className="rounded-xl border border-border overflow-hidden">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3 text-left">Date</th>
                                        <th className="px-4 py-3 text-left">Source</th>
                                        <th className="px-4 py-3 text-left">Type</th>
                                        <th className="px-4 py-3 text-left">Title</th>
                                        <th className="px-4 py-3 text-left">Raw Content</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {scrapedData.data.map(record => (
                                        <tr key={record.id} className="hover:bg-muted/50">
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {new Date(record.scraped_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-3 capitalize">{record.source}</td>
                                            <td className="px-4 py-3 capitalize">{record.data_type}</td>
                                            <td className="px-4 py-3 font-medium">
                                                {record.url ? (
                                                    <a href={record.url} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                                                        {record.title}
                                                    </a>
                                                ) : record.title}
                                            </td>
                                            <td className="px-4 py-3">
                                                <details>
                                                    <summary className="cursor-pointer text-xs text-muted-foreground">View JSON</summary>
                                                    <pre className="mt-2 text-xs bg-muted p-2 rounded overflow-auto max-w-xs">
                                                        {JSON.stringify(record.content, null, 2)}
                                                    </pre>
                                                </details>
                                            </td>
                                        </tr>
                                    ))}
                                    {scrapedData.data.length === 0 && (
                                        <tr>
                                            <td colSpan={5} className="px-4 py-12 text-center text-muted-foreground">
                                                No scraped data found for this run.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Unified Data Table */}
                    {activeTab === 'unified' && (
                        <div className="rounded-xl border border-border overflow-hidden">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3 text-left">Date</th>
                                        <th className="px-4 py-3 text-left">Candidate</th>
                                        <th className="px-4 py-3 text-left">Party</th>
                                        <th className="px-4 py-3 text-right">Poll Avg</th>
                                        <th className="px-4 py-3 text-right">Cash on Hand</th>
                                        <th className="px-4 py-3 text-right">Inflation</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {unifiedData.data.map(record => (
                                        <tr key={record.id} className="hover:bg-muted/50">
                                            <td className="px-4 py-3">{record.date}</td>
                                            <td className="px-4 py-3 font-medium">{record.candidate}</td>
                                            <td className="px-4 py-3">{record.party}</td>
                                            <td className="px-4 py-3 text-right">{record.poll_avg ?? '-'}%</td>
                                            <td className="px-4 py-3 text-right">${(record.cash_on_hand ?? 0).toLocaleString()}</td>
                                            <td className="px-4 py-3 text-right">{record.inflation_rate ?? '-'}%</td>
                                        </tr>
                                    ))}
                                    {unifiedData.data.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-4 py-12 text-center text-muted-foreground">
                                                No unified data generated in this run.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
