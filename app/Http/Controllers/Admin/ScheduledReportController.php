<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\ScheduledReport;
use App\Models\DropdownOption;
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
            'descriptions' => ScheduledReport::getTypeDescriptions(),
            'schedules' => ScheduledReport::getSchedules(),
            'daysOfWeek' => ScheduledReport::getDaysOfWeek(),
            'filterOptions' => $this->getFilterOptions(),
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
            'descriptions' => ScheduledReport::getTypeDescriptions(),
            'schedules' => ScheduledReport::getSchedules(),
            'daysOfWeek' => ScheduledReport::getDaysOfWeek(),
            'filterOptions' => $this->getFilterOptions(),
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

        $status = $scheduledReport->is_active ? 'activated' : 'paused';
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

    /**
     * Get filter options for the form
     */
    private function getFilterOptions(): array
    {
        return [
            'franchise' => Job::whereNotNull('franchise')
                ->distinct()
                ->pluck('franchise')
                ->sort()
                ->values()
                ->toArray(),
            
            'service_advisor' => Job::whereNotNull('service_advisor')
                ->distinct()
                ->pluck('service_advisor')
                ->sort()
                ->values()
                ->toArray(),
            
            'foreman' => Job::whereNotNull('foreman')
                ->distinct()
                ->pluck('foreman')
                ->sort()
                ->values()
                ->toArray(),
            
            'department' => Job::whereNotNull('department')
                ->distinct()
                ->pluck('department')
                ->sort()
                ->values()
                ->toArray(),
            
            'work_status' => DropdownOption::getOptions('work_status')
                ->pluck('value')
                ->toArray(),
            
            'type_sale' => Job::whereNotNull('type_sale')
                ->distinct()
                ->pluck('type_sale')
                ->sort()
                ->values()
                ->toArray(),
        ];
    }

    private function validateReport(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', array_keys(ScheduledReport::getTypes())),
            'schedule' => 'required|string|in:' . implode(',', array_keys(ScheduledReport::getSchedules())),
            'time' => 'required|string',
            'day_of_week' => 'nullable|string|in:' . implode(',', array_keys(ScheduledReport::getDaysOfWeek())),
            'day_of_month' => 'nullable|integer|min:1|max:28',
            'recipients' => 'required|string',
            'config' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Convert recipients string to array
        $validated['recipients'] = array_map('trim', explode(',', $validated['recipients']));
        $validated['is_active'] = $request->boolean('is_active', true);
        
        // Clean up config - remove empty values but keep include_pdf and aging_days
        $config = $request->input('config', []);
        $cleanConfig = [];
        
        foreach ($config as $key => $value) {
            if ($key === 'include_pdf') {
                $cleanConfig[$key] = (bool) $value;
            } elseif ($key === 'aging_days' && !empty($value)) {
                $cleanConfig[$key] = (int) $value;
            } elseif (!empty($value)) {
                $cleanConfig[$key] = $value;
            }
        }
        
        $validated['config'] = $cleanConfig;

        return $validated;
    }
}
