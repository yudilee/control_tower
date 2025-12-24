<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\AuditLogArchive;
use App\Models\BackupSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $modelType = $request->input('model_type');
        $action = $request->input('action');
        $perPage = $request->input('per_page', 50);

        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"))
                  ->orWhere('auditable_type', 'like', "%{$search}%")
                  ->orWhere('auditable_id', $search);
            });
        }

        if ($modelType) {
            $query->where('auditable_type', 'like', "%{$modelType}%");
        }

        if ($action) {
            $query->where('action', $action);
        }

        $logs = $query->paginate($perPage)->withQueryString();
        
        // Get stats
        $stats = [
            'total' => AuditLog::count(),
            'archived' => AuditLogArchive::count(),
            'today' => AuditLog::whereDate('created_at', today())->count(),
            'this_week' => AuditLog::where('created_at', '>=', now()->startOfWeek())->count(),
        ];

        // Get unique model types for filter
        $modelTypes = AuditLog::select('auditable_type')
            ->distinct()
            ->pluck('auditable_type')
            ->map(fn($t) => class_basename($t));

        $schedule = BackupSchedule::first() ?? new BackupSchedule([
            'audit_archive_enabled' => true,
            'audit_archive_days' => 90,
        ]);

        return view('admin.audit-logs.index', compact('logs', 'stats', 'modelTypes', 'schedule'));
    }

    public function archives(Request $request)
    {
        $perPage = $request->input('per_page', 50);
        $archives = AuditLogArchive::with('user')->orderByDesc('original_created_at')->paginate($perPage);
        
        return view('admin.audit-logs.archives', compact('archives'));
    }

    public function archive(Request $request)
    {
        $schedule = BackupSchedule::first();
        $days = $schedule->audit_archive_days ?? 90;

        try {
            Artisan::call('audit:archive', ['--days' => $days]);
            $output = Artisan::output();
            return redirect()->route('admin.audit-logs.index')->with('success', 'Archive completed. ' . $output);
        } catch (\Exception $e) {
            return redirect()->route('admin.audit-logs.index')->with('error', 'Archive failed: ' . $e->getMessage());
        }
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'audit_archive_enabled' => 'nullable|boolean',
            'audit_archive_days' => 'required|integer|min:7|max:365',
        ]);

        BackupSchedule::updateOrCreate(
            ['id' => 1],
            [
                'audit_archive_enabled' => $request->boolean('audit_archive_enabled'),
                'audit_archive_days' => $request->input('audit_archive_days'),
            ]
        );

        return redirect()->route('admin.audit-logs.index')->with('success', 'Archive settings updated.');
    }

    public function clearArchives(Request $request)
    {
        $olderThan = $request->input('older_than', 365);
        $cutoff = now()->subDays($olderThan);

        $deleted = AuditLogArchive::where('original_created_at', '<', $cutoff)->delete();

        return redirect()->route('admin.audit-logs.archives')
            ->with('success', "Deleted {$deleted} archived logs older than {$olderThan} days.");
    }

    public function exportArchives(Request $request)
    {
        $archives = AuditLogArchive::orderBy('original_created_at')->get();
        
        $filename = 'audit_log_archives_' . now()->format('Y-m-d') . '.json';
        $content = $archives->toJson(JSON_PRETTY_PRINT);

        return response($content)
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
