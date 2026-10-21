<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of system activity and audit logs.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        $user = $request->user();
        if (! $user instanceof User || (! $user->can('activity_logs.view') && ! $user->hasRole('superadmin'))) {
            abort(403, 'Unauthorized to view system activity logs.');
        }

        $query = ActivityLog::query()
            ->with(['causer:id,name,email,role'])
            ->latest('id');

        // Filter: Keyword search
        if ($search = $request->input('search')) {
            $query->search((string) $search);
        }

        // Filter: Module / Log Name
        if ($logName = $request->input('log_name')) {
            if ($logName !== 'all') {
                $query->where('log_name', $logName);
            }
        }

        // Filter: Event type
        if ($event = $request->input('event')) {
            if ($event !== 'all') {
                $query->where('event', $event);
            }
        }

        // Filter: Causer User
        if ($causerId = $request->input('causer_id')) {
            if ($causerId !== 'all') {
                $query->where('causer_id', (int) $causerId);
            }
        }

        // Filter: Date presets or range
        $datePreset = (string) $request->input('date_range', 'all');
        if ($datePreset === 'today') {
            $query->whereDate('created_at', Carbon::today());
        } elseif ($datePreset === 'week') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($datePreset === 'month') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        } elseif ($datePreset === 'custom') {
            if ($from = $request->input('date_from')) {
                $query->whereDate('created_at', '>=', (string) $from);
            }
            if ($to = $request->input('date_to')) {
                $query->whereDate('created_at', '<=', (string) $to);
            }
        }

        // CSV Export Handler
        if ($request->boolean('export')) {
            if (! $user->can('activity_logs.export') && ! $user->hasRole('superadmin')) {
                abort(403, 'Unauthorized to export activity logs.');
            }

            return $this->exportCsv($query);
        }

        // KPI Summary Statistics
        $todayStart = Carbon::today();
        $stats = [
            'total_activities' => ActivityLog::count(),
            'today_activities' => ActivityLog::whereDate('created_at', $todayStart)->count(),
            'active_actors_today' => ActivityLog::whereDate('created_at', $todayStart)
                ->whereNotNull('causer_id')
                ->distinct('causer_id')
                ->count('causer_id'),
            'actions_created' => ActivityLog::where('event', 'created')->count(),
            'actions_updated' => ActivityLog::where('event', 'updated')->count(),
            'actions_deleted' => ActivityLog::where('event', 'deleted')->count(),
            'actions_promoted' => ActivityLog::where('event', 'promoted')->count(),
            'actions_security' => ActivityLog::whereIn('event', ['login', 'logout', 'password_reset'])->count(),
        ];

        // Filter catalogs
        $distinctLogNames = ActivityLog::query()
            ->select('log_name')
            ->distinct()
            ->pluck('log_name')
            ->filter()
            ->values();

        $distinctEvents = ActivityLog::query()
            ->select('event')
            ->distinct()
            ->pluck('event')
            ->filter()
            ->values();

        $causers = User::query()
            ->whereIn('id', ActivityLog::query()->select('causer_id')->whereNotNull('causer_id')->distinct())
            ->get(['id', 'name', 'email', 'role']);

        $logs = $query->paginate(20)->withQueryString();

        return Inertia::render('admin/activity-logs/Index', [
            'logs' => $logs,
            'stats' => $stats,
            'filters' => [
                'search' => $request->input('search', ''),
                'log_name' => $request->input('log_name', 'all'),
                'event' => $request->input('event', 'all'),
                'causer_id' => $request->input('causer_id', 'all'),
                'date_range' => $datePreset,
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
            'modules' => $distinctLogNames,
            'events' => $distinctEvents,
            'causers' => $causers,
            'can_delete' => $user->can('activity_logs.delete') || $user->hasRole('superadmin'),
        ]);
    }

    /**
     * Stream activity logs to CSV format for download.
     *
     * @param  Builder<ActivityLog>  $query
     */
    protected function exportCsv(Builder $query): StreamedResponse
    {
        $fileName = 'activity_audit_logs_'.Carbon::now()->format('Y_m_d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return ResponseFactory::stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Microsoft Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // CSV Header
            fputcsv($handle, [
                'Log ID',
                'Timestamp',
                'Module / Channel',
                'Action / Event',
                'Actor Name',
                'Actor Email',
                'Actor Role',
                'Description',
                'Subject Type',
                'Subject ID',
                'IP Address',
                'HTTP Method',
                'Request URL',
            ]);

            $query->chunk(500, function ($batch) use ($handle) {
                /** @var ActivityLog $item */
                foreach ($batch as $item) {
                    /** @var User|null $causer */
                    $causer = $item->causer instanceof User ? $item->causer : null;

                    $causerRole = '—';
                    if ($causer !== null) {
                        $causerRole = $causer->role->value;
                    }

                    fputcsv($handle, [
                        (string) $item->id,
                        $item->created_at->toDateTimeString(),
                        $item->log_name,
                        $item->event ?? '—',
                        $causer !== null ? $causer->name : 'System / Anonymous',
                        $causer !== null ? (string) $causer->email : '—',
                        $causerRole,
                        $item->description,
                        $item->subject_type ? class_basename($item->subject_type) : '—',
                        $item->subject_id !== null ? (string) $item->subject_id : '—',
                        $item->ip_address ?? '—',
                        $item->method ?? '—',
                        $item->url ?? '—',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Delete a single activity log record.
     */
    public function destroy(ActivityLog $activityLog, Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User || (! $user->can('activity_logs.delete') && ! $user->hasRole('superadmin'))) {
            abort(403, 'Unauthorized to delete activity logs.');
        }

        $activityLog->delete();

        return redirect()->back()->with('success', 'Activity log record deleted successfully.');
    }

    /**
     * Delete multiple selected activity log records.
     */
    public function destroyBatch(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User || (! $user->can('activity_logs.delete') && ! $user->hasRole('superadmin'))) {
            abort(403, 'Unauthorized to delete activity logs.');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:activity_logs,id'],
        ]);

        /** @var array<int> $ids */
        $ids = $validated['ids'];
        $count = ActivityLog::whereIn('id', $ids)->delete();

        return redirect()->back()->with('success', "{$count} activity log records deleted successfully.");
    }

    /**
     * Purge or clear activity logs by scope.
     */
    public function clearAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User || (! $user->can('activity_logs.delete') && ! $user->hasRole('superadmin'))) {
            abort(403, 'Unauthorized to purge activity logs.');
        }

        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:all,older_than_30,older_than_60,older_than_90'],
        ]);

        $scope = $validated['scope'];
        $query = ActivityLog::query();

        if ($scope === 'older_than_30') {
            $query->where('created_at', '<', Carbon::now()->subDays(30));
        } elseif ($scope === 'older_than_60') {
            $query->where('created_at', '<', Carbon::now()->subDays(60));
        } elseif ($scope === 'older_than_90') {
            $query->where('created_at', '<', Carbon::now()->subDays(90));
        }

        $count = $query->delete();

        return redirect()->back()->with('success', "Purged {$count} activity log records successfully.");
    }
}
