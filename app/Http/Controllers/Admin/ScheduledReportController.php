<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ScheduledReport;
use Illuminate\Http\Request;

class ScheduledReportController extends Controller
{
    public function index()
    {
        $reports = ScheduledReport::orderBy('name')->get();
        return view('admin.reports.index', compact('reports'));
    }

    public function create()
    {
        return view('admin.reports.form', [
            'report' => null,
            'types' => ScheduledReport::getTypes(),
            'schedules' => ScheduledReport::getSchedules(),
            'daysOfWeek' => ScheduledReport::getDaysOfWeek(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateReport($request);
        
        ScheduledReport::create($validated);

        return redirect()->route('admin.scheduled-reports.index')
            ->with('success', 'Scheduled report created successfully.');
    }

    public function edit(ScheduledReport $scheduledReport)
    {
        return view('admin.reports.form', [
            'report' => $scheduledReport,
            'types' => ScheduledReport::getTypes(),
            'schedules' => ScheduledReport::getSchedules(),
            'daysOfWeek' => ScheduledReport::getDaysOfWeek(),
        ]);
    }

    public function update(Request $request, ScheduledReport $scheduledReport)
    {
        $validated = $this->validateReport($request);
        
        $scheduledReport->update($validated);

        return redirect()->route('admin.scheduled-reports.index')
            ->with('success', 'Scheduled report updated successfully.');
    }

    public function destroy(ScheduledReport $scheduledReport)
    {
        $scheduledReport->delete();

        return redirect()->route('admin.scheduled-reports.index')
            ->with('success', 'Scheduled report deleted.');
    }

    public function toggle(ScheduledReport $scheduledReport)
    {
        $scheduledReport->update(['is_active' => !$scheduledReport->is_active]);

        $status = $scheduledReport->is_active ? 'enabled' : 'disabled';
        return redirect()->back()
            ->with('success', "Report {$status}.");
    }

    public function sendNow(ScheduledReport $scheduledReport)
    {
        try {
            app(\App\Services\ReportEmailService::class)->sendReport($scheduledReport);
            
            $scheduledReport->update(['last_sent_at' => now()]);
            
            return redirect()->back()
                ->with('success', 'Report sent successfully!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to send report: ' . $e->getMessage());
        }
    }

    private function validateReport(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', array_keys(ScheduledReport::getTypes())),
            'schedule' => 'required|string|in:' . implode(',', array_keys(ScheduledReport::getSchedules())),
            'time' => 'required|string',
            'day_of_week' => 'nullable|string|in:' . implode(',', array_keys(ScheduledReport::getDaysOfWeek())),
            'recipients' => 'required|string', // Comma-separated emails
            'config.aging_days' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        // Convert recipients string to array
        $validated['recipients'] = array_map('trim', explode(',', $validated['recipients']));
        $validated['is_active'] = $request->boolean('is_active', true);
        
        // Build config
        $validated['config'] = [
            'aging_days' => $request->input('config.aging_days', 14),
        ];

        return $validated;
    }
}
