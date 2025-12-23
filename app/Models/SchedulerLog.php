<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerLog extends Model
{
    protected $fillable = [
        'command',
        'status',
        'output',
        'error',
        'duration_seconds',
        'triggered_by',
    ];

    /**
     * Start a log entry for a running task
     */
    public static function start(string $command, string $triggeredBy = 'scheduler'): self
    {
        return self::create([
            'command' => $command,
            'status' => 'running',
            'triggered_by' => $triggeredBy,
        ]);
    }

    /**
     * Mark as success
     */
    public function markSuccess(string $output, int $durationSeconds): void
    {
        $this->update([
            'status' => 'success',
            'output' => $output,
            'duration_seconds' => $durationSeconds,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markFailed(string $error, int $durationSeconds): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'duration_seconds' => $durationSeconds,
        ]);
    }

    /**
     * Format duration for display
     */
    public function getDurationFormattedAttribute(): string
    {
        if (!$this->duration_seconds) {
            return '-';
        }

        if ($this->duration_seconds < 60) {
            return $this->duration_seconds . 's';
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;
        return "{$minutes}m {$seconds}s";
    }
}
