<?php

namespace App\Services;

use App\Models\Job;
use App\Models\ScheduledReport;
use Illuminate\Support\Facades\Mail;

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
        switch ($report->type) {
            case ScheduledReport::TYPE_UNINVOICED:
                return $this->getUninvoicedData();
            
            case ScheduledReport::TYPE_PERFORMANCE:
                return $this->getPerformanceData();
            
            case ScheduledReport::TYPE_AGING:
                $agingDays = $report->getConfig('aging_days', 14);
                return $this->getAgingData($agingDays);
            
            case ScheduledReport::TYPE_PARTS_PENDING:
                return $this->getPartsPendingData();
            
            default:
                return [];
        }
    }

    /**
     * Get uninvoiced jobs data
     */
    private function getUninvoicedData(): array
    {
        $jobs = Job::uninvoiced()
            ->orderBy('job_date', 'desc')
            ->get();

        $byFranchise = $jobs->groupBy('franchise');
        $bySA = $jobs->groupBy('service_advisor');

        return [
            'reportTitle' => 'Daily Uninvoiced Jobs Summary',
            'reportDate' => now()->format('d M Y'),
            'totalJobs' => $jobs->count(),
            'totalAmount' => $jobs->sum('total_sales'),
            'byFranchise' => $byFranchise->map(fn($g) => [
                'count' => $g->count(),
                'amount' => $g->sum('total_sales'),
            ]),
            'bySA' => $bySA->map(fn($g) => [
                'count' => $g->count(),
                'amount' => $g->sum('total_sales'),
            ])->sortByDesc('count')->take(10),
            'jobs' => $jobs->take(50), // Limit for email
        ];
    }

    /**
     * Get SA performance data
     */
    private function getPerformanceData(): array
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $jobs = Job::whereBetween('job_date', [$startOfWeek, $endOfWeek])->get();
        $invoiced = Job::where('status', 'invoiced')
            ->whereBetween('updated_at', [$startOfWeek, $endOfWeek])
            ->get();

        $bySA = $jobs->groupBy('service_advisor');
        $invoicedBySA = $invoiced->groupBy('service_advisor');

        $performance = [];
        foreach ($bySA as $sa => $saJobs) {
            $performance[$sa ?? 'Unassigned'] = [
                'new_jobs' => $saJobs->count(),
                'new_amount' => $saJobs->sum('total_sales'),
                'invoiced' => $invoicedBySA->get($sa)?->count() ?? 0,
                'invoiced_amount' => $invoicedBySA->get($sa)?->sum('inv_ppn_meterai') ?? 0,
            ];
        }

        return [
            'reportTitle' => 'Weekly SA Performance Report',
            'reportDate' => $startOfWeek->format('d M') . ' - ' . $endOfWeek->format('d M Y'),
            'performance' => collect($performance)->sortByDesc('new_jobs'),
            'totalNewJobs' => $jobs->count(),
            'totalInvoiced' => $invoiced->count(),
        ];
    }

    /**
     * Get aging jobs data
     */
    private function getAgingData(int $agingDays): array
    {
        $cutoffDate = now()->subDays($agingDays);

        $jobs = Job::uninvoiced()
            ->where('job_date', '<', $cutoffDate)
            ->orderBy('job_date', 'asc')
            ->get();

        return [
            'reportTitle' => "Aging Jobs Alert (>{$agingDays} days)",
            'reportDate' => now()->format('d M Y'),
            'agingDays' => $agingDays,
            'totalJobs' => $jobs->count(),
            'totalAmount' => $jobs->sum('total_sales'),
            'jobs' => $jobs,
        ];
    }

    /**
     * Get parts pending data
     */
    private function getPartsPendingData(): array
    {
        $jobs = Job::uninvoiced()
            ->where('need_part', true)
            ->orderBy('job_date', 'desc')
            ->get();

        return [
            'reportTitle' => 'Parts Pending Report',
            'reportDate' => now()->format('d M Y'),
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
                return "[Control Tower] Daily Uninvoiced Summary - {$date}";
            case ScheduledReport::TYPE_PERFORMANCE:
                return "[Control Tower] Weekly SA Performance Report";
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
            ScheduledReport::TYPE_PERFORMANCE => 'emails.reports.performance',
            ScheduledReport::TYPE_AGING => 'emails.reports.aging',
            ScheduledReport::TYPE_PARTS_PENDING => 'emails.reports.parts-pending',
            default => 'emails.reports.generic',
        };
    }
}
