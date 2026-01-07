<?php

namespace App\Services;

use App\Models\Job;
use App\Models\ScheduledReport;
use App\Models\DropdownOption;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Builder;

class ReportEmailService
{
    /**
     * Send a scheduled report
     */
    public function sendReport(ScheduledReport $report): void
    {
        $data = $this->generateReportData($report);
        
        if (empty($report->recipients)) {
            throw new \Exception('No recipients configured for this report.');
        }

        $subject = $this->getSubject($report);
        $view = $this->getEmailView($report->type);

        foreach ($report->recipients as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            Mail::send($view, $data, function ($message) use ($email, $subject) {
                $message->to($email)
                        ->subject($subject);
            });
        }
    }

    /**
     * Generate report data based on type
     */
    private function generateReportData(ScheduledReport $report): array
    {
        $config = $report->config ?? [];
        
        switch ($report->type) {
            case ScheduledReport::TYPE_UNINVOICED:
                return $this->getUninvoicedData($config);
            
            case ScheduledReport::TYPE_INVOICED:
                return $this->getInvoicedData($config);
            
            case ScheduledReport::TYPE_PERFORMANCE:
                return $this->getPerformanceData($config);
            
            case ScheduledReport::TYPE_AGING:
                $agingDays = $config['aging_days'] ?? 14;
                return $this->getAgingData($agingDays, $config);
            
            case ScheduledReport::TYPE_PARTS_PENDING:
                return $this->getPartsPendingData($config);
            
            default:
                return [];
        }
    }

    /**
     * Apply common filters to a query
     * @param bool $useInvoiceDate - if true, filter on invoice_date; if false, filter on job_date
     */
    private function applyFilters(Builder $query, array $config, bool $useInvoiceDate = false): Builder
    {
        if (!empty($config['franchise'])) {
            $query->where('franchise', $config['franchise']);
        }
        
        if (!empty($config['service_advisor'])) {
            $query->where('service_advisor', $config['service_advisor']);
        }
        
        if (!empty($config['foreman'])) {
            $query->where('foreman', $config['foreman']);
        }
        
        if (!empty($config['department'])) {
            $query->where('department', $config['department']);
        }
        
        if (!empty($config['work_status'])) {
            $query->where('work_status', $config['work_status']);
        }
        
        if (isset($config['need_part']) && $config['need_part'] !== '') {
            $query->where('need_part', (bool) $config['need_part']);
        }
        
        if (!empty($config['type_sale'])) {
            $query->where('type_sale', $config['type_sale']);
        }
        
        // Handle date period - use correct date field based on job status
        if (!empty($config['date_period'])) {
            $dates = $this->getDateRangeFromPeriod($config['date_period']);
            if ($dates) {
                $dateField = $useInvoiceDate ? 'invoice_date' : 'job_date';
                $query->whereBetween($dateField, [$dates['from'], $dates['to']]);
            }
        }
        
        return $query;
    }

    /**
     * Get date range from period string
     */
    private function getDateRangeFromPeriod(string $period): ?array
    {
        return match($period) {
            'last_7_days' => ['from' => now()->subDays(7)->startOfDay(), 'to' => now()->endOfDay()],
            'last_30_days' => ['from' => now()->subDays(30)->startOfDay(), 'to' => now()->endOfDay()],
            'this_week' => ['from' => now()->startOfWeek(), 'to' => now()->endOfWeek()],
            'this_month' => ['from' => now()->startOfMonth(), 'to' => now()->endOfMonth()],
            'last_month' => ['from' => now()->subMonth()->startOfMonth(), 'to' => now()->subMonth()->endOfMonth()],
            default => null,
        };
    }

    /**
     * Get applied filters for display
     */
    private function getAppliedFilters(array $config): array
    {
        $filters = [];
        $filterLabels = [
            'franchise' => 'Franchise',
            'service_advisor' => 'Service Advisor',
            'foreman' => 'Foreman',
            'department' => 'Department',
            'work_status' => 'Work Status',
            'type_sale' => 'Type Sale',
            'date_period' => 'Period',
        ];
        
        foreach ($filterLabels as $key => $label) {
            if (!empty($config[$key])) {
                $value = $config[$key];
                if ($key === 'date_period') {
                    $value = str_replace('_', ' ', ucfirst($value));
                }
                $filters[$key] = $value;
            }
        }
        
        return $filters;
    }

    /**
     * Get uninvoiced jobs data
     */
    private function getUninvoicedData(array $config = []): array
    {
        $query = Job::uninvoiced()->orderBy('job_date', 'desc');
        $query = $this->applyFilters($query, $config, false); // Use job_date for uninvoiced
        
        $jobs = $query->get();

        $byFranchise = $jobs->groupBy('franchise');
        $bySA = $jobs->groupBy('service_advisor');
        
        // PC/CV breakdown
        $pcJobs = $jobs->where('franchise', 'PC');
        $cvJobs = $jobs->where('franchise', 'CV');
        
        // Work status breakdown - normalize using DropdownOption definitions
        $allWorkStatuses = DropdownOption::getOptions('work_status');
        
        // Create lookup map
        $statusLookup = [];
        foreach ($allWorkStatuses as $status) {
            $statusLookup[strtolower(trim($status->value))] = $status;
            $statusLookup[strtolower(trim($status->label))] = $status;
        }
        
        // Aggregate by normalized status
        $aggregatedCounts = [];
        foreach ($jobs as $job) {
            $rawStatus = $job->work_status;
            if (empty($rawStatus)) continue;
            
            $lookupKey = strtolower(trim($rawStatus));
            if (isset($statusLookup[$lookupKey])) {
                $option = $statusLookup[$lookupKey];
                $optionId = $option->id;
                
                if (!isset($aggregatedCounts[$optionId])) {
                    $aggregatedCounts[$optionId] = [
                        'name' => $option->label,
                        'count' => 0,
                        'amount' => 0,
                        'sort_order' => $option->sort_order,
                    ];
                }
                $aggregatedCounts[$optionId]['count']++;
                $aggregatedCounts[$optionId]['amount'] += $job->total_sales ?? 0;
            }
        }
        
        // Sort by sort_order
        $workStatusBreakdown = collect($aggregatedCounts)
            ->sortBy('sort_order')
            ->values();

        return [
            'reportTitle' => 'Uninvoiced Jobs Report',
            'reportDate' => now()->format('d M Y'),
            'appliedFilters' => $this->getAppliedFilters($config),
            'totalJobs' => $jobs->count(),
            'totalAmount' => $jobs->sum('total_sales'),
            'pcJobs' => $pcJobs->count(),
            'pcAmount' => $pcJobs->sum('total_sales'),
            'cvJobs' => $cvJobs->count(),
            'cvAmount' => $cvJobs->sum('total_sales'),
            'workStatusBreakdown' => $workStatusBreakdown,
            'byFranchise' => $byFranchise->map(fn($g) => [
                'count' => $g->count(),
                'amount' => $g->sum('total_sales'),
            ]),
            'bySA' => $bySA->map(fn($g) => [
                'count' => $g->count(),
                'amount' => $g->sum('total_sales'),
            ])->sortByDesc('count')->take(10),
            'jobs' => $jobs->take(50),
        ];
    }

    /**
     * Get invoiced jobs data
     */
    private function getInvoicedData(array $config = []): array
    {
        $query = Job::invoiced()->orderBy('invoice_date', 'desc');
        $query = $this->applyFilters($query, $config, true); // Use invoice_date for invoiced
        
        $jobs = $query->get();
        
        // PC/CV breakdown
        $pcJobs = $jobs->where('franchise', 'PC');
        $cvJobs = $jobs->where('franchise', 'CV');
        
        // Department breakdown (PC only)
        $deptBreakdown = $pcJobs->groupBy('department')
            ->map(fn($g, $dept) => [
                'name' => $dept ?: 'No Department',
                'count' => $g->count(),
                'amount' => $g->sum('inv_ppn_meterai'),
            ])
            ->sortByDesc('amount')
            ->values();
        
        // Type Sale breakdown
        $typeSaleLabels = ['INT' => 'Internal', 'WAR' => 'Warranty', 'CASH' => 'Cash', 'CREDIT' => 'Credit'];
        
        $typeSalePC = $pcJobs->groupBy('type_sale')
            ->map(fn($g, $type) => [
                'name' => $typeSaleLabels[$type] ?? ($type ?: 'Unknown'),
                'count' => $g->count(),
                'amount' => $g->sum('inv_ppn_meterai'),
            ])
            ->sortByDesc('amount')
            ->values();
        
        $typeSaleCV = $cvJobs->groupBy('type_sale')
            ->map(fn($g, $type) => [
                'name' => $typeSaleLabels[$type] ?? ($type ?: 'Unknown'),
                'count' => $g->count(),
                'amount' => $g->sum('inv_ppn_meterai'),
            ])
            ->sortByDesc('amount')
            ->values();

        return [
            'reportTitle' => 'Invoiced Jobs Report',
            'reportDate' => now()->format('d M Y'),
            'appliedFilters' => $this->getAppliedFilters($config),
            'totalJobs' => $jobs->count(),
            'totalAmount' => $jobs->sum('inv_ppn_meterai'),
            'pcJobs' => $pcJobs->count(),
            'pcAmount' => $pcJobs->sum('inv_ppn_meterai'),
            'cvJobs' => $cvJobs->count(),
            'cvAmount' => $cvJobs->sum('inv_ppn_meterai'),
            'deptBreakdown' => $deptBreakdown,
            'typeSalePC' => $typeSalePC,
            'typeSaleCV' => $typeSaleCV,
            'jobs' => $jobs->take(50),
        ];
    }

    /**
     * Get SA performance data
     */
    private function getPerformanceData(array $config = []): array
    {
        // Get date range
        $datePeriod = $config['date_period'] ?? 'this_week';
        $dates = $this->getDateRangeFromPeriod($datePeriod);
        
        if (!$dates) {
            $dates = ['from' => now()->startOfWeek(), 'to' => now()->endOfWeek()];
        }

        $jobs = Job::whereBetween('job_date', [$dates['from'], $dates['to']])->get();
        $invoiced = Job::where('status', 'invoiced')
            ->whereBetween('invoice_date', [$dates['from'], $dates['to']])
            ->get();

        $bySA = $jobs->groupBy('service_advisor');
        $invoicedBySA = $invoiced->groupBy('service_advisor');

        $performance = [];
        foreach ($bySA as $sa => $saJobs) {
            $saName = $sa ?: 'Unassigned';
            $invoicedData = $invoicedBySA->get($sa);
            
            $performance[$saName] = [
                'new_jobs' => $saJobs->count(),
                'new_amount' => $saJobs->sum('total_sales'),
                'invoiced' => $invoicedData?->count() ?? 0,
                'invoiced_amount' => $invoicedData?->sum('inv_ppn_meterai') ?? 0,
            ];
        }

        // Sort by new_jobs descending
        $performance = collect($performance)->sortByDesc('new_jobs');

        return [
            'reportTitle' => 'SA Performance Report',
            'reportDate' => $dates['from']->format('d M') . ' - ' . $dates['to']->format('d M Y'),
            'appliedFilters' => ['Period' => str_replace('_', ' ', ucfirst($datePeriod))],
            'performance' => $performance,
            'totalNewJobs' => $jobs->count(),
            'totalNewAmount' => $jobs->sum('total_sales'),
            'totalInvoiced' => $invoiced->count(),
            'totalInvoicedAmount' => $invoiced->sum('inv_ppn_meterai'),
        ];
    }

    /**
     * Get aging jobs data
     */
    private function getAgingData(int $agingDays, array $config = []): array
    {
        $cutoffDate = now()->subDays($agingDays);

        $query = Job::uninvoiced()
            ->where('job_date', '<', $cutoffDate)
            ->orderBy('job_date', 'asc');
        
        // Remove date_period from config - aging already has its own date logic
        $agingConfig = $config;
        unset($agingConfig['date_period']);
        
        $query = $this->applyFilters($query, $agingConfig, false);
        
        $jobs = $query->get();
        
        // Calculate average age
        $avgAge = $jobs->avg(fn($job) => $job->job_date ? abs(now()->diffInDays($job->job_date)) : 0);
        
        // Count critical (30+ days)
        $criticalCount = $jobs->filter(fn($job) => $job->job_date && abs(now()->diffInDays($job->job_date)) >= 30)->count();
        
        // Aging buckets
        $agingGroups = [
            '0-7' => ['label' => '0-7 Days', 'color' => 'green', 'count' => 0, 'amount' => 0],
            '7-14' => ['label' => '7-14 Days', 'color' => 'blue', 'count' => 0, 'amount' => 0],
            '14-30' => ['label' => '14-30 Days', 'color' => 'orange', 'count' => 0, 'amount' => 0],
            '30+' => ['label' => '30+ Days', 'color' => 'red', 'count' => 0, 'amount' => 0],
        ];
        
        foreach ($jobs as $job) {
            $days = $job->job_date ? abs(now()->diffInDays($job->job_date)) : 0;
            $bucket = $days >= 30 ? '30+' : ($days >= 14 ? '14-30' : ($days >= 7 ? '7-14' : '0-7'));
            $agingGroups[$bucket]['count']++;
            $agingGroups[$bucket]['amount'] += $job->total_sales ?? 0;
        }

        return [
            'reportTitle' => "Aging Jobs Alert (>{$agingDays} days)",
            'reportDate' => now()->format('d M Y'),
            'appliedFilters' => $this->getAppliedFilters($config),
            'agingDays' => $agingDays,
            'totalJobs' => $jobs->count(),
            'totalAmount' => $jobs->sum('total_sales'),
            'avgAge' => $avgAge,
            'criticalCount' => $criticalCount,
            'agingGroups' => $agingGroups,
            'jobs' => $jobs,
        ];
    }

    /**
     * Get parts pending data
     */
    private function getPartsPendingData(array $config = []): array
    {
        $query = Job::uninvoiced()
            ->where('need_part', true)
            ->orderBy('job_date', 'desc');
        
        $query = $this->applyFilters($query, $config, false); // Use job_date for parts pending
        
        $jobs = $query->get();

        return [
            'reportTitle' => 'Parts Pending Report',
            'reportDate' => now()->format('d M Y'),
            'appliedFilters' => $this->getAppliedFilters($config),
            'totalJobs' => $jobs->count(),
            'jobs' => $jobs,
        ];
    }

    /**
     * Get email subject
     */
    private function getSubject(ScheduledReport $report): string
    {
        $date = now()->format('d M Y');
        
        switch ($report->type) {
            case ScheduledReport::TYPE_UNINVOICED:
                return "[Control Tower] Uninvoiced Jobs Report - {$date}";
            case ScheduledReport::TYPE_INVOICED:
                return "[Control Tower] Invoiced Jobs Report - {$date}";
            case ScheduledReport::TYPE_PERFORMANCE:
                return "[Control Tower] SA Performance Report";
            case ScheduledReport::TYPE_AGING:
                return "[Control Tower] Aging Jobs Alert - {$date}";
            case ScheduledReport::TYPE_PARTS_PENDING:
                return "[Control Tower] Parts Pending Report - {$date}";
            default:
                return "[Control Tower] {$report->name}";
        }
    }

    /**
     * Get email view name
     */
    private function getEmailView(string $type): string
    {
        return match($type) {
            ScheduledReport::TYPE_UNINVOICED => 'emails.reports.uninvoiced',
            ScheduledReport::TYPE_INVOICED => 'emails.reports.invoiced',
            ScheduledReport::TYPE_PERFORMANCE => 'emails.reports.performance',
            ScheduledReport::TYPE_AGING => 'emails.reports.aging',
            ScheduledReport::TYPE_PARTS_PENDING => 'emails.reports.parts-pending',
            default => 'emails.reports.generic',
        };
    }
}
