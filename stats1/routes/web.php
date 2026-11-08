<?php

use App\Http\Controllers\AIAnalysisController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataSourceController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ExplorerController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Protected routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Data Sources
    Route::get('data-sources', [DataSourceController::class, 'index'])->name('data-sources');
    Route::post('data-sources', [DataSourceController::class, 'store'])->name('data-sources.store');
    Route::patch('data-sources/{dataSource}', [DataSourceController::class, 'update'])->name('data-sources.update');
    Route::delete('data-sources/{dataSource}', [DataSourceController::class, 'destroy'])->name('data-sources.destroy');
    Route::post('data-sources/{dataSource}/test', [DataSourceController::class, 'testConnection'])->name('data-sources.test');
    Route::post('data-sources/{dataSource}/toggle-pipeline', [DataSourceController::class, 'togglePipelineEnabled'])->name('data-sources.toggle-pipeline');

    // Pipeline
    Route::get('pipeline', [PipelineController::class, 'index'])->name('pipeline');
    Route::post('pipeline/run', [PipelineController::class, 'run'])->name('pipeline.run');
    Route::get('pipeline/history', [PipelineController::class, 'history'])->name('pipeline.history');
    Route::get('pipeline/history/{run}', [PipelineController::class, 'show'])->name('pipeline.show');

    // Explorer
    Route::get('explorer', [ExplorerController::class, 'index'])->name('explorer');
    Route::get('explorer/export', [ExplorerController::class, 'export'])->name('explorer.export');
    Route::post('explorer/correlation', [ExplorerController::class, 'correlationAnalysis'])->name('explorer.correlation');

    // AI Analysis
    Route::post('ai/analyze', [AIAnalysisController::class, 'analyze'])->name('ai.analyze');
    Route::post('ai/pipeline-summary/{run}', [AIAnalysisController::class, 'pipelineSummary'])->name('ai.pipeline-summary');
    Route::get('ai/suggestions', [AIAnalysisController::class, 'suggestions'])->name('ai.suggestions');

    // Alerts
    Route::get('alerts', [\App\Http\Controllers\AlertsController::class, 'index'])->name('alerts.index');
    Route::post('alerts/{alert}/read', [\App\Http\Controllers\AlertsController::class, 'markRead'])->name('alerts.read');
    Route::post('alerts/read-all', [\App\Http\Controllers\AlertsController::class, 'markAllRead'])->name('alerts.readAll');
    Route::post('alerts/check', [\App\Http\Controllers\AlertsController::class, 'checkNow'])->name('alerts.check');
    Route::get('alerts/monitors', [\App\Http\Controllers\AlertsController::class, 'monitorStatus'])->name('alerts.monitors');

    // Charts & Trends
    Route::get('charts/trends', [\App\Http\Controllers\ChartController::class, 'trendData'])->name('charts.trends');
    Route::get('charts/states', [\App\Http\Controllers\ChartController::class, 'availableStates'])->name('charts.states');
    Route::get('charts/poll-feed', [\App\Http\Controllers\ChartController::class, 'pollFeed'])->name('charts.pollFeed');
    Route::get('charts/stats', [\App\Http\Controllers\ChartController::class, 'stats'])->name('charts.stats');
    Route::get('charts/export', [\App\Http\Controllers\ChartController::class, 'exportJSON'])->name('charts.export');

    // Polling Source Sync
    Route::post('data-sources/sync/{sourceType}', [\App\Http\Controllers\DataSourceController::class, 'syncPollingSource'])->name('data-sources.sync');

    // Scrapers
    Route::post('scrapers/{type}/run', [\App\Http\Controllers\ScraperController::class, 'run'])->name('scrapers.run');
    Route::post('scrapers/run-all', [\App\Http\Controllers\ScraperController::class, 'runAll'])->name('scrapers.runAll');
    Route::get('scrapers/status', [\App\Http\Controllers\ScraperController::class, 'status'])->name('scrapers.status');

    // Alert API endpoints
    Route::get('api/alerts/recent', [\App\Http\Controllers\AlertController::class, 'recent'])->name('alerts.api.recent');
    Route::get('api/alerts/unread-count', [\App\Http\Controllers\AlertController::class, 'unreadCount'])->name('alerts.api.unreadCount');
    Route::post('api/alerts/{alert}/dismiss', [\App\Http\Controllers\AlertController::class, 'dismiss'])->name('alerts.api.dismiss');
});

require __DIR__.'/settings.php';
