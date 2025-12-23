<?php

namespace App\Http\Controllers;

use App\Models\DismissedDuplicateGroup;
use App\Models\DropdownOption;
use App\Models\Job;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with real-time statistics.
     */
    public function index()
    {
        // Always fetch fresh data - no caching
        $stats = [
            'uninvoiced' => Job::uninvoiced()->count(),
            'invoiced' => Job::invoiced()->count(),
            'needs_parts' => Job::uninvoiced()->needsParts()->count(),
            'vehicles_in_workshop' => Vehicle::where('is_in_workshop', true)->count(),
        ];

        $workStatusCounts = Job::uninvoiced()
            ->selectRaw('COALESCE(work_status, "pending") as work_status, COUNT(*) as count')
            ->groupBy('work_status')
            ->get()
            ->keyBy('work_status');

        $workStatusOptions = DropdownOption::getOptions('work_status');

        // Count pending duplicate groups - instant lookup from cached table
        $duplicateCustomerCount = \App\Models\DuplicateCustomerGroup::pending()->count();

        $chartData = $this->getChartData($workStatusOptions, $workStatusCounts);

        // Recent jobs with eager loading
        $recentJobs = Job::uninvoiced()
            ->with('vehicle')
            ->latest()
            ->take(5)
            ->get();

        $needsPartsJobs = Job::uninvoiced()
            ->needsParts()
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', [
            'stats' => $stats,
            'workStatusCounts' => $workStatusCounts,
            'workStatusOptions' => $workStatusOptions,
            'duplicateCustomerCount' => $duplicateCustomerCount,
            'chartData' => $chartData,
            'recentJobs' => $recentJobs,
            'needsPartsJobs' => $needsPartsJobs,
        ]);
    }

    /**
     * Get chart data for dashboard.
     */
    protected function getChartData($workStatusOptions, $workStatusCounts): array
    {
        // Last 7 days job trend
        $last7Days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $last7Days->push([
                'date' => $date->format('d M'),
                'invoiced' => Job::whereDate('invoiced_at', $date)->count(),
                'new' => Job::whereDate('job_date', $date)->count(),
            ]);
        }

        // Status pie chart data
        $statusCounts = $workStatusOptions->map(fn($opt) => [
            'label' => $opt->label,
            'count' => $workStatusCounts->get($opt->value)?->count ?? 0,
            'color' => match ($opt->color) {
                'primary' => '#0d6efd',
                'success' => '#198754',
                'warning' => '#ffc107',
                'danger' => '#dc3545',
                'info' => '#0dcaf0',
                'secondary' => '#6c757d',
                default => '#6c757d'
            }
        ])->filter(fn($s) => $s['count'] > 0);

        // SA Revenue (Top 5)
        $saRevenue = Job::uninvoiced()
            ->selectRaw('service_advisor, SUM(COALESCE(total_sales, 0)) as revenue, COUNT(*) as job_count')
            ->whereNotNull('service_advisor')
            ->groupBy('service_advisor')
            ->orderByDesc('revenue')
            ->take(5)
            ->get();

        // Job Aging breakdown
        $today = now()->startOfDay();
        $agingData = [
            ['label' => '< 3 days', 'count' => Job::uninvoiced()->where('job_date', '>', $today->copy()->subDays(3))->count(), 'color' => '#198754'],
            ['label' => '3-7 days', 'count' => Job::uninvoiced()->whereBetween('job_date', [$today->copy()->subDays(7), $today->copy()->subDays(3)])->count(), 'color' => '#0dcaf0'],
            ['label' => '7-14 days', 'count' => Job::uninvoiced()->whereBetween('job_date', [$today->copy()->subDays(14), $today->copy()->subDays(7)])->count(), 'color' => '#ffc107'],
            ['label' => '14-30 days', 'count' => Job::uninvoiced()->whereBetween('job_date', [$today->copy()->subDays(30), $today->copy()->subDays(14)])->count(), 'color' => '#fd7e14'],
            ['label' => '> 30 days', 'count' => Job::uninvoiced()->where('job_date', '<', $today->copy()->subDays(30))->count(), 'color' => '#dc3545'],
        ];

        return [
            'last7Days' => $last7Days,
            'statusCounts' => $statusCounts,
            'saRevenue' => $saRevenue,
            'agingData' => $agingData,
        ];
    }
}
