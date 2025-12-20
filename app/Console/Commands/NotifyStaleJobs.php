<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;

class NotifyStaleJobs extends Command
{
    protected $signature = 'notify:stale-jobs {--days=7 : Jobs older than this many days} {--dry-run : Show what would be notified without creating notifications}';
    
    protected $description = 'Send notifications for stale uninvoiced jobs';

    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $this->info("Checking for jobs older than {$days} days...");
        
        $cutoffDate = now()->subDays($days);
        
        // Get stale uninvoiced jobs
        $staleJobs = Job::uninvoiced()
            ->where('job_date', '<=', $cutoffDate)
            ->get();
            
        $this->info("Found {$staleJobs->count()} stale jobs.");
        
        if ($staleJobs->isEmpty()) {
            $this->info('No stale jobs to notify about.');
            return 0;
        }

        // Group by Service Advisor for targeted notifications
        $byServiceAdvisor = $staleJobs->groupBy('service_advisor');
        
        // Get users who should receive notifications (admins, managers, control_tower)
        $notifyRoles = ['admin', 'manager', 'control_tower'];
        $usersToNotify = User::whereIn('role', $notifyRoles)->get();
        
        $notificationCount = 0;
        
        foreach ($usersToNotify as $user) {
            // Create a summary notification for stale jobs
            $jobCount = $staleJobs->count();
            $totalValue = $staleJobs->sum('total_sales');
            
            $title = "⚠️ {$jobCount} Stale Jobs Detected";
            $message = "There are {$jobCount} uninvoiced jobs older than {$days} days, worth Rp " . number_format($totalValue, 0, ',', '.') . " in potential revenue.";
            
            if ($dryRun) {
                $this->line("Would notify {$user->name}: {$title}");
            } else {
                Notification::notify(
                    $user->id,
                    Notification::TYPE_STALE_JOB,
                    $title,
                    $message,
                    route('reports.aging'),
                    'clock-history',
                    'warning'
                );
                $notificationCount++;
            }
        }
        
        // Also notify SAs about their own stale jobs
        foreach ($byServiceAdvisor as $saName => $jobs) {
            if (!$saName) continue;
            
            $saUser = User::whereHas('serviceAdvisor', function($q) use ($saName) {
                $q->where('name', $saName);
            })->first();
            
            if ($saUser) {
                $jobCount = $jobs->count();
                $title = "⚠️ You have {$jobCount} stale jobs";
                $message = "Jobs: " . $jobs->take(3)->pluck('job_number')->implode(', ') . ($jobCount > 3 ? " and " . ($jobCount - 3) . " more" : "");
                
                if ($dryRun) {
                    $this->line("Would notify SA {$saUser->name}: {$title}");
                } else {
                    Notification::notify(
                        $saUser->id,
                        Notification::TYPE_STALE_JOB,
                        $title,
                        $message,
                        route('jobs.index', ['status' => 'uninvoiced', 'filter_service_advisor' => $saName]),
                        'clock-history',
                        'warning'
                    );
                    $notificationCount++;
                }
            }
        }
        
        if ($dryRun) {
            $this->info('Dry run complete. No notifications were created.');
        } else {
            $this->info("Created {$notificationCount} notifications.");
        }
        
        return 0;
    }
}
