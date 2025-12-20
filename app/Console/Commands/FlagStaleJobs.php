<?php

namespace App\Console\Commands;

use App\Models\Job;
use Illuminate\Console\Command;

class FlagStaleJobs extends Command
{
    protected $signature = 'jobs:flag-stale 
                            {--days=14 : Jobs older than this many days will be flagged}
                            {--status=needs_attention : Work status to set for stale jobs}
                            {--dry-run : Show what would be flagged without making changes}';
    
    protected $description = 'Flag stale uninvoiced jobs by updating their work status';

    public function handle()
    {
        $days = (int) $this->option('days');
        $newStatus = $this->option('status');
        $dryRun = $this->option('dry-run');
        
        $this->info("Checking for uninvoiced jobs older than {$days} days...");
        
        $cutoffDate = now()->subDays($days);
        
        // Get stale jobs that haven't already been flagged
        $staleJobs = Job::uninvoiced()
            ->where('job_date', '<=', $cutoffDate)
            ->where(function($q) use ($newStatus) {
                $q->where('work_status', '!=', $newStatus)
                  ->orWhereNull('work_status');
            })
            ->whereNotIn('work_status', ['selesai', 'completed', 'invoiced']) // Don't flag completed jobs
            ->get();
            
        $this->info("Found {$staleJobs->count()} stale jobs to flag.");
        
        if ($staleJobs->isEmpty()) {
            $this->info('No stale jobs to flag.');
            return 0;
        }

        if ($dryRun) {
            $this->table(
                ['Job #', 'Plate', 'SA', 'Days Old', 'Current Status'],
                $staleJobs->take(20)->map(fn($j) => [
                    $j->job_number,
                    $j->plate_number,
                    $j->service_advisor,
                    now()->diffInDays($j->job_date),
                    $j->work_status ?? 'pending',
                ])
            );
            
            if ($staleJobs->count() > 20) {
                $this->line("... and " . ($staleJobs->count() - 20) . " more");
            }
            
            $this->info('Dry run complete. No changes made.');
            return 0;
        }
        
        // Update all stale jobs
        $updated = Job::whereIn('id', $staleJobs->pluck('id'))
            ->update(['work_status' => $newStatus]);
        
        $this->info("Flagged {$updated} jobs with status '{$newStatus}'.");
        
        return 0;
    }
}
