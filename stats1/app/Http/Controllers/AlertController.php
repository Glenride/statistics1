<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlertController extends Controller
{
    /**
     * Display a listing of alerts
     */
    public function index(Request $request)
    {
        $query = Alert::active()->orderBy('created_at', 'desc');

        // Filter by severity
        if ($request->has('severity')) {
            $query->bySeverity($request->severity);
        }

        // Filter by source
        if ($request->has('source')) {
            $query->bySource($request->source);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Filter by read status
        if ($request->has('unread') && $request->unread) {
            $query->unread();
        }

        $alerts = $query->paginate(20);

        // Get counts for the dashboard
        $counts = [
            'total' => Alert::active()->count(),
            'unread' => Alert::active()->unread()->count(),
            'critical' => Alert::active()->critical()->count(),
        ];

        return Inertia::render('alerts', [
            'alerts' => $alerts,
            'counts' => $counts,
            'filters' => $request->only(['severity', 'source', 'type', 'unread']),
        ]);
    }

    /**
     * Get recent alerts for dashboard widget
     */
    public function recent()
    {
        $alerts = Alert::active()
            ->unread()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'alerts' => $alerts,
            'unread_count' => Alert::active()->unread()->count(),
        ]);
    }

    /**
     * Mark an alert as read
     */
    public function markAsRead(Alert $alert)
    {
        $alert->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Alert marked as read',
        ]);
    }

    /**
     * Mark all alerts as read
     */
    public function markAllAsRead()
    {
        Alert::active()->unread()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All alerts marked as read',
        ]);
    }

    /**
     * Dismiss an alert
     */
    public function dismiss(Alert $alert)
    {
        $alert->dismiss();

        return response()->json([
            'success' => true,
            'message' => 'Alert dismissed',
        ]);
    }

    /**
     * Get unread count (for header badge)
     */
    public function unreadCount()
    {
        return response()->json([
            'count' => Alert::active()->unread()->count(),
            'critical' => Alert::active()->unread()->critical()->count(),
        ]);
    }
}
