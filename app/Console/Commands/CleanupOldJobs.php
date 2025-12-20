<?php

namespace App\Console\Commands;

use App\Models\Job;
use Illuminate\Console\Command;

class CleanupOldJobs extends Command
{
    protected $signature = 'jobs:cleanup 
                            {--months=12 : Archive jobs older than this many months}
                            {--dry-run : Show what would be archived without making changes}';
    
    protected $description = 'Archive old invoiced jobs to keep the main table performant';

    public function handle()
    {
        $months = (int) $this->option('months');
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = now()->subMonths($months);
        
        $this->info("Finding invoiced jobs older than {$months} months (before {$cutoffDate->format('Y-m-d')})...");
        
        // Find old invoiced jobs
        $oldJobs = Job::where('status', 'invoiced')
            ->where(function($q) use ($cutoffDate) {
                $q->where('invoiced_at', '<=', $cutoffDate)
                  ->orWhere(function($q2) use ($cutoffDate) {
                      $q2->whereNull('invoiced_at')
                         ->where('invoice_date', '<=', $cutoffDate);
                  });
            })
            ->get();
            
        $this->info("Found {$oldJobs->count()} old invoiced jobs.");
        
        if ($oldJobs->isEmpty()) {
            $this->info('No old jobs to archive.');
            return 0;
        }
        
        // Summary by month
        $byMonth = $oldJobs->groupBy(fn($j) => ($j->invoiced_at ?? $j->invoice_date)?->format('Y-m') ?? 'Unknown');
        
        $this->table(
            ['Month', 'Job Count', 'Total Value'],
            $byMonth->map(fn($jobs, $month) => [
                $month,
                $jobs->count(),
                'Rp ' . number_format($jobs->sum('inv_ppn_meterai'), 0, ',', '.'),
            ])->values()
        );
        
        if ($dryRun) {
            $this->info('Dry run complete. No changes made.');
            $this->line('To actually archive these jobs, run without --dry-run.');
            $this->warn('Note: Currently this command only DISPLAYS old jobs. Archiving would require a separate archive table.');
            return 0;
        }
        
        // For now, just log that we found these jobs
        // A full archive system would move to a separate table
        $this->warn('Archiving not implemented yet. Consider exporting these to a separate table or backup.');
        $this->info("Jobs identified for future archiving: {$oldJobs->count()}");
        
        return 0;
    }
}
