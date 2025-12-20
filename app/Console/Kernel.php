<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Dynamic backup schedule from database
        $backupSchedule = \App\Models\BackupSchedule::first();
        
        if ($backupSchedule && $backupSchedule->enabled) {
            $command = $schedule->command('backup:run', ['--remark' => $backupSchedule->remark ?? 'Scheduled backup']);
            
            switch ($backupSchedule->frequency) {
                case 'daily':
                    $command->dailyAt($backupSchedule->time);
                    break;
                case 'weekly':
                    $dayOfWeek = $backupSchedule->day_of_week ?? 0; // Default Sunday
                    $command->weeklyOn($dayOfWeek, $backupSchedule->time);
                    break;
                case 'monthly':
                    $dayOfMonth = $backupSchedule->day_of_month ?? 1; // Default 1st
                    $command->monthlyOn($dayOfMonth, $backupSchedule->time);
                    break;
                default:
                    $command->dailyAt($backupSchedule->time);
            }
        }
        
        // Daily stale job notifications at 8 AM
        $schedule->command('notify:stale-jobs', ['--days' => 7])
            ->dailyAt('08:00')
            ->withoutOverlapping();
            
        // Weekly stale job flagging (Mondays at 7 AM)
        $schedule->command('jobs:flag-stale', ['--days' => 14, '--status' => 'needs_attention'])
            ->weeklyOn(1, '07:00')
            ->withoutOverlapping();
            
        // Monthly old job report (1st of month at 6 AM)
        $schedule->command('jobs:cleanup', ['--months' => 12, '--dry-run'])
            ->monthlyOn(1, '06:00')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
