<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\AuditLogArchive;
use App\Models\BackupSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveAuditLogs extends Command
{
    protected $signature = 'audit:archive {--days= : Override days threshold} {--dry-run : Show what would be archived without doing it}';
    protected $description = 'Archive old audit logs to reduce main table size';

    public function handle(): int
    {
        $schedule = BackupSchedule::first();
        $days = $this->option('days') ?? $schedule->audit_archive_days ?? 90;
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = now()->subDays($days);
        
        $this->info("Archiving audit logs older than {$cutoffDate->format('Y-m-d')} ({$days} days ago)...");
        
        $query = AuditLog::where('created_at', '<', $cutoffDate);
        $totalToArchive = $query->count();
        
        if ($totalToArchive === 0) {
            $this->info('No logs to archive.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$totalToArchive} logs to archive.");
        
        if ($dryRun) {
            $this->warn('Dry run mode - no changes made.');
            return Command::SUCCESS;
        }
        
        $bar = $this->output->createProgressBar($totalToArchive);
        $bar->start();
        
        $archived = 0;
        $batchSize = 500;
        
        // Process in batches to avoid memory issues
        while (true) {
            $logs = AuditLog::where('created_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->get();
            
            if ($logs->isEmpty()) {
                break;
            }
            
            $archiveData = [];
            $idsToDelete = [];
            
            foreach ($logs as $log) {
                $archiveData[] = [
                    'auditable_type' => $log->auditable_type,
                    'auditable_id' => $log->auditable_id,
                    'user_id' => $log->user_id,
                    'action' => $log->action,
                    'old_values' => json_encode($log->old_values),
                    'new_values' => json_encode($log->new_values),
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'original_created_at' => $log->created_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $idsToDelete[] = $log->id;
                $bar->advance();
            }
            
            // Insert to archive
            DB::table('audit_log_archives')->insert($archiveData);
            
            // Delete from main table
            AuditLog::whereIn('id', $idsToDelete)->delete();
            
            $archived += count($idsToDelete);
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info("Archived {$archived} audit logs.");
        $this->info("Main table now has " . AuditLog::count() . " logs.");
        $this->info("Archive table now has " . AuditLogArchive::count() . " logs.");
        
        return Command::SUCCESS;
    }
}
