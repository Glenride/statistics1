import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { ConnectorCard } from '@/components/data-sources/connector-card';
import type { BreadcrumbItem } from '@/types';
import axios from 'axios';
import { RefreshCw, Plus, X, Zap, ZapOff, Trash2, Check } from 'lucide-react';
import { cn } from '@/lib/utils';

// Sources that use scrapers and don't require API keys
const SCRAPER_SOURCES = ['rcp', 'press_monitor', 'pdf_hunter', 'ap_elections', 'ballotpedia', 'nationbuilder', 'cicero', 'polling', 'usagov'];

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Data Sources',
        href: '/data-sources',
    },
];

interface DataSource {
    id: number;
    type: string;
    name: string;
    description: string | null;
    category: 'core' | 'polling' | 'custom';
    is_custom: boolean;
    pipeline_enabled: boolean;
    status: 'active' | 'inactive' | 'error';
    config: Record<string, unknown>;
    record_count: number;
    data_quality_score: number;
    last_sync_at: string | null;
    last_error: string | null;
}

interface GroupedSources {
    core: DataSource[];
    polling: DataSource[];
    custom: DataSource[];
}

interface DataSourcesProps {
    dataSources: DataSource[];
    groupedSources: GroupedSources;
}

export default function DataSources({ dataSources, groupedSources }: DataSourcesProps) {
    const [syncingSource, setSyncingSource] = useState<string | null>(null);
    const [syncResults, setSyncResults] = useState<Record<string, { success: boolean; message: string }>>({});
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [togglingPipeline, setTogglingPipeline] = useState<number | null>(null);
    const [deletingSource, setDeletingSource] = useState<number | null>(null);
    const [newSource, setNewSource] = useState({
        name: '',
        type: '',
        description: '',
        config: { url: '', api_key: '' },
    });

    const handleSync = async (sourceType: string) => {
        setSyncingSource(sourceType);
        try {
            const response = await axios.post(`/data-sources/sync/${sourceType}`);
            setSyncResults(prev => ({
                ...prev,
                [sourceType]: {
                    success: true,
                    message: `Synced successfully! ${JSON.stringify(response.data.result)}`
                }
            }));
        } catch (error: any) {
            setSyncResults(prev => ({
                ...prev,
                [sourceType]: {
                    success: false,
                    message: error.response?.data?.error || 'Sync failed'
                }
            }));
        } finally {
            setSyncingSource(null);
        }
    };

    const handleTogglePipeline = async (source: DataSource) => {
        setTogglingPipeline(source.id);
        try {
            await axios.post(`/data-sources/${source.id}/toggle-pipeline`);
            router.reload({ only: ['dataSources', 'groupedSources'] });
        } catch (error) {
            console.error('Failed to toggle pipeline:', error);
        } finally {
            setTogglingPipeline(null);
        }
    };

    const handleCreateSource = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await axios.post('/data-sources', newSource);
            setShowCreateModal(false);
            setNewSource({ name: '', type: '', description: '', config: { url: '', api_key: '' } });
            router.reload();
        } catch (error: any) {
            alert(error.response?.data?.message || 'Failed to create source');
        }
    };

    const handleDeleteSource = async (source: DataSource) => {
        if (!confirm(`Delete "${source.name}"? This cannot be undone.`)) return;
        setDeletingSource(source.id);
        try {
            await axios.delete(`/data-sources/${source.id}`);
            router.reload();
        } catch (error: any) {
            alert(error.response?.data?.message || 'Failed to delete source');
        } finally {
            setDeletingSource(null);
        }
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'Never';
        return new Date(dateString).toLocaleString();
    };

    const getCategoryColor = (category: string) => {
        switch (category) {
            case 'core': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
            case 'polling': return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
            case 'custom': return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
            default: return 'bg-gray-100 text-gray-700';
        }
    };

    const getCategoryBorder = (category: string) => {
        switch (category) {
            case 'core': return 'border-blue-200 dark:border-blue-800';
            case 'polling': return 'border-orange-200 dark:border-orange-800';
            case 'custom': return 'border-purple-200 dark:border-purple-800';
            default: return 'border-border';
        }
    };

    const renderSourceCard = (source: DataSource) => {
        const isScraperSource = SCRAPER_SOURCES.includes(source.type);

        return (
            <div
                key={source.id}
                className={cn(
                    'rounded-xl border p-4 bg-card transition-all hover:shadow-md',
                    getCategoryBorder(source.category)
                )}
            >
                <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                        <div className={cn('px-2 py-0.5 rounded text-xs font-medium', getCategoryColor(source.category))}>
                            {source.category}
                        </div>
                        <h3 className="font-semibold text-foreground">{source.name}</h3>
                    </div>
                    <div className="flex items-center gap-2">
                        {/* Show ready for scrapers, otherwise show status */}
                        {isScraperSource ? (
                            <span className="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                ready
                            </span>
                        ) : (
                            <span className={cn(
                                'px-2 py-1 text-xs font-medium rounded-full',
                                source.status === 'active' ? 'bg-green-100 text-green-700' :
                                    source.status === 'error' ? 'bg-red-100 text-red-700' :
                                        'bg-gray-100 text-gray-700'
                            )}>
                                {source.status}
                            </span>
                        )}
                    </div>
                </div>

                {source.description && (
                    <p className="text-xs text-muted-foreground mb-3">{source.description}</p>
                )}

                {/* Show ready message for scrapers */}
                {isScraperSource && (
                    <div className="flex items-center gap-2 p-2 mb-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                        <Check className="h-4 w-4 text-green-600" />
                        <span className="text-xs text-green-700 dark:text-green-400">Ready to sync - no API key needed!</span>
                    </div>
                )}

                <div className="flex items-center justify-between text-xs text-muted-foreground mb-3">
                    <span>{source.record_count} records</span>
                    <span>Last sync: {formatDate(source.last_sync_at)}</span>
                </div>

                {/* Pipeline Toggle */}
                <div className="flex items-center justify-between py-2 px-3 rounded-lg bg-muted/50 mb-3">
                    <div className="flex items-center gap-2">
                        {source.pipeline_enabled ? (
                            <Zap className="h-4 w-4 text-green-500" />
                        ) : (
                            <ZapOff className="h-4 w-4 text-gray-400" />
                        )}
                        <span className="text-xs font-medium">Pipeline</span>
                    </div>
                    <button
                        onClick={() => handleTogglePipeline(source)}
                        disabled={togglingPipeline === source.id}
                        className={cn(
                            'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
                            source.pipeline_enabled ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'
                        )}
                    >
                        <span
                            className={cn(
                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                source.pipeline_enabled ? 'translate-x-4' : 'translate-x-1'
                            )}
                        />
                    </button>
                </div>

                {/* Sync Results */}
                {syncResults[source.type] && (
                    <div className={`text-xs mb-2 p-2 rounded ${syncResults[source.type].success
                        ? 'bg-green-100 text-green-700'
                        : 'bg-red-100 text-red-700'
                        }`}>
                        {syncResults[source.type].message}
                    </div>
                )}

                {/* Action Buttons */}
                <div className="flex gap-2">
                    <button
                        onClick={() => handleSync(source.type)}
                        disabled={syncingSource === source.type}
                        className={cn(
                            'flex-1 flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-colors disabled:opacity-50',
                            isScraperSource
                                ? 'text-white bg-green-600 hover:bg-green-700'
                                : 'text-white bg-primary hover:bg-primary/90'
                        )}
                    >
                        <RefreshCw className={`h-4 w-4 ${syncingSource === source.type ? 'animate-spin' : ''}`} />
                        {syncingSource === source.type ? 'Syncing...' : 'Sync Now'}
                    </button>

                    {source.is_custom && (
                        <button
                            onClick={() => handleDeleteSource(source)}
                            disabled={deletingSource === source.id}
                            className="px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors disabled:opacity-50"
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    )}
                </div>
            </div>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Data Sources" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Data Sources</h1>
                        <p className="text-sm text-muted-foreground">
                            Configure and manage your data connectors. Enable sources for pipeline runs.
                        </p>
                    </div>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary/90 rounded-lg transition-colors"
                    >
                        <Plus className="h-4 w-4" />
                        Add Data Source
                    </button>
                </div>

                {/* Core Data Sources */}
                <div>
                    <h2 className="text-lg font-semibold text-foreground mb-3 flex items-center gap-2">
                        <span className="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">Core</span>
                        Core Data Sources
                    </h2>
                    <div className="grid md:grid-cols-3 gap-4">
                        {groupedSources.core.map(renderSourceCard)}
                    </div>
                </div>

                {/* Polling Sources */}
                {groupedSources.polling.length > 0 && (
                    <div>
                        <h2 className="text-lg font-semibold text-foreground mb-3 flex items-center gap-2">
                            <span className="px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">Polling</span>
                            Advanced Polling Sources
                        </h2>
                        <div className="grid md:grid-cols-3 gap-4">
                            {groupedSources.polling.map(renderSourceCard)}
                        </div>
                    </div>
                )}

                {/* Custom Sources */}
                {groupedSources.custom.length > 0 && (
                    <div>
                        <h2 className="text-lg font-semibold text-foreground mb-3 flex items-center gap-2">
                            <span className="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">Custom</span>
                            Custom Data Sources
                        </h2>
                        <div className="grid md:grid-cols-3 gap-4">
                            {groupedSources.custom.map(renderSourceCard)}
                        </div>
                    </div>
                )}

                {/* Empty State for Custom */}
                {groupedSources.custom.length === 0 && (
                    <div className="rounded-xl border-2 border-dashed border-border p-8 text-center">
                        <Plus className="h-12 w-12 mx-auto text-muted-foreground mb-3" />
                        <h3 className="font-semibold text-foreground mb-1">No Custom Sources</h3>
                        <p className="text-sm text-muted-foreground mb-4">
                            Add your own data sources to include in pipeline runs.
                        </p>
                        <button
                            onClick={() => setShowCreateModal(true)}
                            className="px-4 py-2 text-sm font-medium text-primary hover:bg-primary/10 rounded-lg transition-colors"
                        >
                            Add Your First Source
                        </button>
                    </div>
                )}

                {/* Help Section */}
                <div className="rounded-xl border border-border bg-muted/30 p-6">
                    <h3 className="font-semibold text-foreground mb-3">Need API Keys?</h3>
                    <div className="grid md:grid-cols-3 gap-4 text-sm mb-4">
                        <div>
                            <p className="font-medium text-foreground">FEC API Key</p>
                            <p className="text-muted-foreground mb-1">Get your key from the FEC developer portal.</p>
                            <a href="https://api.open.fec.gov/developers/" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                                api.open.fec.gov →
                            </a>
                        </div>
                        <div>
                            <p className="font-medium text-foreground">FRED API Key</p>
                            <p className="text-muted-foreground mb-1">Register at the St. Louis Fed for free access.</p>
                            <a href="https://fred.stlouisfed.org/docs/api/api_key.html" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                                fred.stlouisfed.org →
                            </a>
                        </div>
                        <div>
                            <p className="font-medium text-foreground">Google Civic API</p>
                            <p className="text-muted-foreground mb-1">Free tier: 25,000 requests/day.</p>
                            <a href="https://console.cloud.google.com/apis/library/civicinfo.googleapis.com" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                                console.cloud.google.com →
                            </a>
                        </div>
                    </div>
                    <div className="grid md:grid-cols-3 gap-4 text-sm mb-4">
                        <div>
                            <p className="font-medium text-foreground">OpenSecrets API</p>
                            <p className="text-muted-foreground mb-1">Campaign finance and donor data.</p>
                            <a href="https://www.opensecrets.org/api/admin/index.php?function=signup" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                                opensecrets.org →
                            </a>
                        </div>
                        <div>
                            <p className="font-medium text-foreground">ProPublica API</p>
                            <p className="text-muted-foreground mb-1">FEC filings updated every 15 minutes.</p>
                            <a href="https://www.propublica.org/datastore/api/campaign-finance-api" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                                propublica.org →
                            </a>
                        </div>
                        <div>
                            <p className="font-medium text-foreground">Vote Smart API</p>
                            <p className="text-muted-foreground mb-1">Candidate bios and voting records.</p>
                            <a href="https://votesmart.org/share/api" target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                                votesmart.org →
                            </a>
                        </div>
                    </div>
                    <div className="grid md:grid-cols-3 gap-4 text-sm border-t border-border pt-4">
                        <div>
                            <p className="font-medium text-foreground">Polling Data</p>
                            <p className="text-muted-foreground mb-1">FiveThirtyEight public data.</p>
                            <span className="text-green-600">✓ Ready to use</span>
                        </div>
                        <div>
                            <p className="font-medium text-foreground">Scrapers (RCP, Press, PDF)</p>
                            <p className="text-muted-foreground mb-1">No API needed. Uses web scraping + AI.</p>
                            <span className="text-green-600">✓ Ready to use</span>
                        </div>
                        <div>
                            <p className="font-medium text-foreground">USA.gov Social Media</p>
                            <p className="text-muted-foreground mb-1">Federal officials' social accounts.</p>
                            <span className="text-green-600">✓ No key needed</span>
                        </div>
                    </div>
                </div>

                {/* Create Modal */}
                {showCreateModal && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                        <div className="bg-card rounded-xl border border-border max-w-md w-full">
                            <div className="p-4 border-b border-border flex items-center justify-between">
                                <h3 className="font-semibold text-foreground">Add Data Source</h3>
                                <button
                                    onClick={() => setShowCreateModal(false)}
                                    className="p-2 rounded-lg hover:bg-muted transition-colors"
                                >
                                    <X className="h-5 w-5 text-muted-foreground" />
                                </button>
                            </div>
                            <form onSubmit={handleCreateSource} className="p-4 space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-foreground mb-1">Name</label>
                                    <input
                                        type="text"
                                        required
                                        value={newSource.name}
                                        onChange={(e) => setNewSource({ ...newSource, name: e.target.value })}
                                        className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="My Custom Source"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-foreground mb-1">Type (unique identifier)</label>
                                    <input
                                        type="text"
                                        required
                                        value={newSource.type}
                                        onChange={(e) => setNewSource({ ...newSource, type: e.target.value.toLowerCase().replace(/\s+/g, '_') })}
                                        className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="my_custom_source"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-foreground mb-1">Description</label>
                                    <textarea
                                        value={newSource.description}
                                        onChange={(e) => setNewSource({ ...newSource, description: e.target.value })}
                                        className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="What this data source provides..."
                                        rows={2}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-foreground mb-1">Data URL (optional)</label>
                                    <input
                                        type="url"
                                        value={newSource.config.url}
                                        onChange={(e) => setNewSource({ ...newSource, config: { ...newSource.config, url: e.target.value } })}
                                        className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="https://api.example.com/data"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-foreground mb-1">API Key (optional)</label>
                                    <input
                                        type="password"
                                        value={newSource.config.api_key}
                                        onChange={(e) => setNewSource({ ...newSource, config: { ...newSource.config, api_key: e.target.value } })}
                                        className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                        placeholder="Your API key"
                                    />
                                </div>
                                <div className="flex gap-3 pt-2">
                                    <button
                                        type="button"
                                        onClick={() => setShowCreateModal(false)}
                                        className="flex-1 px-4 py-2 text-sm font-medium text-foreground hover:bg-muted rounded-lg transition-colors"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        className="flex-1 px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary/90 rounded-lg transition-colors"
                                    >
                                        Create Source
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
