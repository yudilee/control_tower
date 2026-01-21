<?php

namespace App\Http\Controllers;

use App\Models\DismissedDuplicateGroup;
use App\Models\DropdownOption;
use App\Models\Job;
use App\Models\PartOrder;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Collection;

/**
 * Dashboard Controller.
 * 
 * Manages the main dashboard view with cached statistics, charts,
 * and quick-access widgets for workshop operations overview.
 * 
 * @package App\Http\Controllers
 * @author Control Tower Team
 */
class DashboardController extends Controller
{
    /**
     * Cache TTL in seconds (5 minutes).
     * 
     * @var int
     */
    const CACHE_TTL = 300;
    
    /**
     * Display the dashboard with cached statistics.
     * 
     * Renders overview statistics, work status distribution,
     * trend charts, SA revenue ranking, aging breakdown,
     * and recent job lists. All data is cached for performance.
     *
     * @return View The dashboard view with all statistics
     */
    public function index(): View
    {
        $user = auth()->user();
        
        // Get user's dashboard preferences
        $dashboardPreference = $user->getDashboardPreference();
        $enabledWidgets = collect($dashboardPreference->getEnabledWidgets())->pluck('id')->toArray();
        
        // Cache dashboard stats for 5 minutes
        $stats = Cache::remember('dashboard_stats', self::CACHE_TTL, function () {
            return [
                'uninvoiced' => Job::uninvoiced()->count(),
                'invoiced' => Job::invoiced()->count(),
                'needs_parts' => Job::uninvoiced()->needsParts()->count(),
                'vehicles_in_workshop' => Vehicle::where('is_in_workshop', true)->count(),
            ];
        });

        $workStatusCounts = Cache::remember('dashboard_work_status_counts', self::CACHE_TTL, function () {
            $rawCounts = Job::uninvoiced()
                ->selectRaw('COALESCE(work_status, "belum_diproses") as work_status, COUNT(*) as count')
                ->groupBy('work_status')
                ->get();
            
            // Normalize legacy status values to new format
            $normalizedCounts = collect();
            foreach ($rawCounts as $item) {
                $normalizedStatus = Job::normalizeWorkStatus($item->work_status);
                $existing = $normalizedCounts->get($normalizedStatus);
                if ($existing) {
                    $existing->count += $item->count;
                    $normalizedCounts->put($normalizedStatus, $existing);
                } else {
                    $normalizedCounts->put($normalizedStatus, (object)[
                        'work_status' => $normalizedStatus,
                        'count' => $item->count
                    ]);
                }
            }
            return $normalizedCounts;
        });

        $workStatusOptions = Job::getWorkStatusOptions();

        // Count pending duplicate groups
        $duplicateCustomerCount = Cache::remember('dashboard_duplicate_count', self::CACHE_TTL, function () {
            return \App\Models\DuplicateCustomerGroup::pending()->count();
        });

        $chartData = Cache::remember('dashboard_chart_data', self::CACHE_TTL, function () use ($workStatusOptions) {
            $rawCounts = Job::uninvoiced()
                ->selectRaw('COALESCE(work_status, "belum_diproses") as work_status, COUNT(*) as count')
                ->groupBy('work_status')
                ->get();
            
            // Normalize legacy status values to new format  
            $normalizedCounts = collect();
            foreach ($rawCounts as $item) {
                $normalizedStatus = Job::normalizeWorkStatus($item->work_status);
                $existing = $normalizedCounts->get($normalizedStatus);
                if ($existing) {
                    $existing->count += $item->count;
                    $normalizedCounts->put($normalizedStatus, $existing);
                } else {
                    $normalizedCounts->put($normalizedStatus, (object)[
                        'work_status' => $normalizedStatus,
                        'count' => $item->count
                    ]);
                }
            }
            return $this->getChartData($workStatusOptions, $normalizedCounts);
        });

        // Recent jobs - shorter cache (2 minutes)
        $recentJobs = Cache::remember('dashboard_recent_jobs', 120, function () {
            return Job::uninvoiced()
                ->with('vehicle')
                ->latest()
                ->take(5)
                ->get();
        });

        $needsPartsJobs = Cache::remember('dashboard_needs_parts_jobs', 120, function () {
            return Job::uninvoiced()
                ->needsParts()
                ->latest()
                ->take(5)
                ->get();
        });

        // Parts tracking stats for sparepart role
        $partsStats = null;
        if (in_array($user->role, ['sparepart', 'admin', 'manager'])) {
            $partsStats = Cache::remember('dashboard_parts_stats', 120, function () {
                return [
                    'pending' => PartOrder::pending()->count(),
                    'due_soon' => PartOrder::dueSoon(7)->count(),
                    'overdue' => PartOrder::overdue()->count(),
                ];
            });
        }

        // My Jobs (for SA/Foreman)
        $myJobs = collect();
        if (in_array('my_jobs', $enabledWidgets)) {
            $myJobsQuery = Job::uninvoiced()->latest();
            if ($user->serviceAdvisor) {
                $myJobsQuery->where('service_advisor', $user->serviceAdvisor->name);
            } elseif ($user->foreman) {
                $myJobsQuery->where('foreman', $user->foreman->name);
            }
            $myJobs = $myJobsQuery->take(10)->get();
        }

        // Today's Bookings
        $bookingsToday = collect();
        if (in_array('bookings_today', $enabledWidgets)) {
            $bookingsToday = \App\Models\Booking::whereDate('booking_date', today())
                ->orderBy('booking_time')
                ->take(5)
                ->get();
        }

        // Pending Invoices
        $pendingInvoices = collect();
        if (in_array('pending_invoices', $enabledWidgets)) {
            $pendingInvoices = \App\Models\JobInvoice::whereIn('status', ['pending', 'partially_paid'])
                ->with('job')
                ->orderByDesc('invoice_date')
                ->take(5)
                ->get();
        }

        // Saved Filters
        $savedFilters = collect();
        if (in_array('saved_filters', $enabledWidgets)) {
            $savedFilters = \App\Models\SavedReport::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->take(5)
                ->get();
        }

        return view('dashboard', [
            'stats' => $stats,
            'workStatusCounts' => $workStatusCounts,
            'workStatusOptions' => $workStatusOptions,
            'duplicateCustomerCount' => $duplicateCustomerCount,
            'chartData' => $chartData,
            'recentJobs' => $recentJobs,
            'needsPartsJobs' => $needsPartsJobs,
            'partsStats' => $partsStats,
            'enabledWidgets' => $enabledWidgets,
            'myJobs' => $myJobs,
            'bookingsToday' => $bookingsToday,
            'pendingInvoices' => $pendingInvoices,
            'savedFilters' => $savedFilters,
        ]);
    }

    /**
     * Clear dashboard cache.
     * 
     * Call this method when jobs are modified to ensure
     * dashboard displays fresh data on next load.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        Cache::forget('dashboard_stats');
        Cache::forget('dashboard_work_status_counts');
        Cache::forget('dashboard_duplicate_count');
        Cache::forget('dashboard_chart_data');
        Cache::forget('dashboard_recent_jobs');
        Cache::forget('dashboard_needs_parts_jobs');
        Cache::forget('dashboard_parts_stats');
    }

    /**
     * Get chart data for dashboard visualizations.
     * 
     * Generates data for:
     * - Last 7 days job trend (new vs invoiced)
     * - Work status pie chart
     * - Top 5 SA revenue ranking
     * - Job aging breakdown by date range
     *
     * @param Collection $workStatusOptions Available work status dropdown options
     * @param Collection $workStatusCounts Current count per work status
     * @return array Chart data arrays for JavaScript rendering
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
                'purple' => '#6f42c1',
                'indigo' => '#6610f2',
                'cyan' => '#0dcaf0',
                'orange' => '#fd7e14',
                'teal' => '#20c997',
                'pink' => '#d63384',
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
