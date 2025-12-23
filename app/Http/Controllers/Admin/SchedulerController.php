<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchedulerSetting;
use App\Models\SchedulerLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SchedulerController extends Controller
{
    public function index()
    {
        // Ensure default tasks exist
        SchedulerSetting::getOrCreateDefaults();
        
        $schedules = SchedulerSetting::orderBy('name')->get();
        $recentLogs = SchedulerLog::orderByDesc('created_at')->limit(20)->get();
        
        return view('admin.scheduler.index', [
            'schedules' => $schedules,
            'recentLogs' => $recentLogs,
        ]);
    }

    /**
     * Toggle task enabled/disabled
     */
    public function toggle(SchedulerSetting $setting)
    {
        $setting->update(['is_enabled' => !$setting->is_enabled]);
        
        $status = $setting->is_enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "Task '{$setting->name}' {$status}.");
    }

    /**
     * Update task settings
     */
    public function update(Request $request, SchedulerSetting $setting)
    {
        $request->validate([
            'schedule' => 'required|in:hourly,daily,weekly,every_minute',
            'time' => 'required|date_format:H:i',
            'day_of_week' => 'nullable|integer|between:0,6',
        ]);

        $setting->update([
            'schedule' => $request->input('schedule'),
            'time' => $request->input('time'),
            'day_of_week' => $request->input('day_of_week'),
        ]);

        return redirect()->back()->with('success', "Schedule for '{$setting->name}' updated.");
    }

    /**
     * Run a task manually with logging
     */
    public function runNow(Request $request)
    {
        $command = $request->input('command');
        
        $setting = SchedulerSetting::where('command', $command)->first();
        if (!$setting) {
            return redirect()->back()->with('error', 'Unknown command.');
        }

        // Start log entry
        $log = SchedulerLog::start($command, 'manual');
        $startTime = microtime(true);

        try {
            Artisan::call($command);
            $output = Artisan::output();
            $duration = (int)(microtime(true) - $startTime);
            
            $log->markSuccess($output, $duration);
            
            // Update setting
            $setting->update([
                'last_run_at' => now(),
                'last_status' => 'success',
            ]);
            
            return redirect()->back()->with('success', "Task '{$setting->name}' completed successfully in {$duration}s.");
        } catch (\Exception $e) {
            $duration = (int)(microtime(true) - $startTime);
            $log->markFailed($e->getMessage(), $duration);
            
            $setting->update([
                'last_run_at' => now(),
                'last_status' => 'failed',
            ]);
            
            return redirect()->back()->with('error', "Task failed: " . $e->getMessage());
        }
    }

    /**
     * View logs for a specific command
     */
    public function logs(Request $request)
    {
        $command = $request->input('command');
        
        $query = SchedulerLog::orderByDesc('created_at');
        
        if ($command) {
            $query->where('command', $command);
        }
        
        $logs = $query->paginate(50);
        $commands = SchedulerSetting::pluck('name', 'command');
        
        return view('admin.scheduler.logs', [
            'logs' => $logs,
            'commands' => $commands,
            'selectedCommand' => $command,
        ]);
    }

    /**
     * Clear old logs
     */
    public function clearLogs()
    {
        $deleted = SchedulerLog::where('created_at', '<', now()->subDays(30))->delete();
        
        return redirect()->back()->with('success', "Cleared {$deleted} log entries older than 30 days.");
    }
}
