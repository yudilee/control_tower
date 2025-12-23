<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Carbon\Carbon;
use App\Models\BackupLog;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class BackupService
{
    protected $disk = 'local';
    protected $backupFolder = 'backups';

    public function create($remark = null)
    {
        $filename = 'backup-' . Carbon::now()->format('Y-m-d-H-i-s') . '.sql.gz';
        $path = storage_path('app/' . $this->backupFolder . '/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $config = config('database.connections.mysql');
        
        // Create temporary SQL file first, then gzip (more reliable than piping)
        $tempSqlPath = $path . '.tmp.sql';
        
        // Build mysqldump command - capture stderr
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s 2>&1',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['database'])
        );

        // Execute and capture output
        $sqlContent = shell_exec($command);
        
        // Check if mysqldump returned an error
        if (empty($sqlContent) || str_starts_with(trim($sqlContent), 'mysqldump:') || str_starts_with(trim($sqlContent), 'error')) {
            throw new \Exception('Backup failed: ' . ($sqlContent ?: 'mysqldump returned empty output'));
        }

        // Write SQL to temp file
        file_put_contents($tempSqlPath, $sqlContent);
        
        // Gzip the file
        $gzHandle = gzopen($path, 'wb9');
        if (!$gzHandle) {
            unlink($tempSqlPath);
            throw new \Exception('Failed to create gzip file');
        }
        gzwrite($gzHandle, $sqlContent);
        gzclose($gzHandle);
        
        // Clean up temp file
        if (file_exists($tempSqlPath)) {
            unlink($tempSqlPath);
        }

        // Get accurate file size
        clearstatcache(true, $path);
        $fileSize = file_exists($path) ? filesize($path) : 0;
        
        if ($fileSize < 100) {
            throw new \Exception('Backup file is too small (' . $fileSize . ' bytes), backup may have failed');
        }

        // Create BackupLog record
        BackupLog::create([
            'filename' => $filename,
            'path' => $this->backupFolder . '/' . $filename,
            'disk' => $this->disk,
            'size' => $fileSize,
            'remark' => $remark,
            'created_by' => Auth::check() ? Auth::user()->name : 'System/Scheduler',
        ]);

        return $filename;
    }

    public function list()
    {
        // Return BackupLogs ordered by latest
        return BackupLog::latest()->get();
    }

    public function restore($filename)
    {
        $path = storage_path('app/' . $this->backupFolder . '/' . $filename);
        
        if (!file_exists($path)) {
            throw new \Exception('Backup file not found.');
        }

        $this->restoreFromPath($path, $filename);
        return true;
    }

    /**
     * Restore from uploaded file
     */
    public function restoreFromFile(UploadedFile $file)
    {
        // Save uploaded file temporarily
        $tempPath = storage_path('app/temp_restore_' . time() . '.sql.gz');
        $file->move(dirname($tempPath), basename($tempPath));

        try {
            $this->restoreFromPath($tempPath, $file->getClientOriginalName());
        } finally {
            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        return true;
    }

    /**
     * Common restore logic
     */
    protected function restoreFromPath($path, $filename)
    {
        $config = config('database.connections.mysql');
        
        // Detect if file is gzipped
        $isGzipped = str_ends_with(strtolower($filename), '.gz');
        
        if ($isGzipped) {
            $command = sprintf(
                'gunzip < %s | mysql --user=%s --password=%s --host=%s --port=%s %s',
                escapeshellarg($path),
                escapeshellarg($config['username']),
                escapeshellarg($config['password']),
                escapeshellarg($config['host']),
                escapeshellarg($config['port']),
                escapeshellarg($config['database'])
            );
        } else {
            $command = sprintf(
                'mysql --user=%s --password=%s --host=%s --port=%s %s < %s',
                escapeshellarg($config['username']),
                escapeshellarg($config['password']),
                escapeshellarg($config['host']),
                escapeshellarg($config['port']),
                escapeshellarg($config['database']),
                escapeshellarg($path)
            );
        }

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \Exception('Restore failed with exit code ' . $returnVar);
        }
        
        // Log the restoration action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'RESTORE',
            'model_type' => 'Database',
            'model_id' => 0,
            'details' => json_encode([
                'file' => $filename,
                'restored_by' => Auth::check() ? Auth::user()->name : 'System',
                'timestamp' => now()->toDateTimeString()
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    public function delete($filename)
    {
        $path = $this->backupFolder . '/' . $filename;
        
        // Delete file
        if (Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->delete($path);
        }
        
        // Delete log record
        BackupLog::where('filename', $filename)->delete();
        
        return true;
    }
    
    public function download($filename)
    {
         $path = $this->backupFolder . '/' . $filename;
         if (Storage::disk($this->disk)->exists($path)) {
             return Storage::disk($this->disk)->download($path);
         }
         return null;
    }
}
