<?php

namespace App\Console\Commands;

use App\Models\ScheduledReport;
use App\Services\ReportEmailService;
use Illuminate\Console\Command;

class SendScheduledReports extends Command
{
    protected $signature = 'reports:send {--force : Send all active reports regardless of schedule}';
    protected $description = 'Send scheduled email reports';

    public function handle(ReportEmailService $service): int
    {
        $reports = ScheduledReport::where('is_active', true)->get();

        if ($reports->isEmpty()) {
            $this->info('No active scheduled reports found.');
            return Command::SUCCESS;
        }

        $sent = 0;
        foreach ($reports as $report) {
            if ($this->option('force') || $report->shouldRunNow()) {
                try {
                    $this->info("Sending: {$report->name}...");
                    $service->sendReport($report);
                    $report->update(['last_sent_at' => now()]);
                    $sent++;
                    $this->info("✓ Sent successfully");
                } catch (\Exception $e) {
                    $this->error("✗ Failed: {$e->getMessage()}");
                }
            }
        }

        $this->info("Completed. {$sent} report(s) sent.");
        return Command::SUCCESS;
    }
}
