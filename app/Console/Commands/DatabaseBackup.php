<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupService;
use Illuminate\Support\Facades\Log;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run {--remark= : Optional remark for the backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup of the database';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService)
    {
        $this->info('Starting database backup...');

        try {
            $remark = $this->option('remark') ?? 'Scheduled backup';
            $filename = $backupService->create($remark);
            $this->info('Backup created successfully: ' . $filename);
            Log::info('Database backup created successfully: ' . $filename);
        } catch (\Exception $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            Log::error('Database backup failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
