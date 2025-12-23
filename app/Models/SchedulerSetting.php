<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerSetting extends Model
{
    protected $fillable = [
        'command',
        'name',
        'description',
        'schedule',
        'time',
        'day_of_week',
        'is_enabled',
        'last_run_at',
        'last_status',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    /**
     * Check if task is enabled
     */
    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    /**
     * Get schedule description
     */
    public function getScheduleDescriptionAttribute(): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        return match($this->schedule) {
            'hourly' => 'Every hour',
            'daily' => "Daily at {$this->time}",
            'weekly' => "Weekly on {$days[$this->day_of_week ?? 1]} at {$this->time}",
            'every_minute' => 'Every minute',
            default => $this->schedule,
        };
    }

    /**
     * Get next run time
     */
    public function getNextRunAttribute(): string
    {
        if (!$this->is_enabled) {
            return 'Disabled';
        }

        $now = now()->timezone('Asia/Jakarta');
        [$hour, $minute] = explode(':', $this->time ?? '07:00');

        if ($this->schedule === 'every_minute') {
            return $now->addMinute()->format('d M Y H:i');
        }

        if ($this->schedule === 'hourly') {
            return $now->addHour()->startOfHour()->format('d M Y H:i');
        }

        if ($this->schedule === 'daily') {
            $next = $now->copy()->setTime($hour, $minute);
            if ($next->isPast()) {
                $next->addDay();
            }
            return $next->format('d M Y H:i');
        }

        if ($this->schedule === 'weekly') {
            $next = $now->copy()->startOfWeek()->addDays($this->day_of_week ?? 1)->setTime($hour, $minute);
            if ($next->isPast()) {
                $next->addWeek();
            }
            return $next->format('d M Y H:i');
        }

        return 'Unknown';
    }

    /**
     * Get or create default settings for known tasks
     */
    public static function getOrCreateDefaults(): void
    {
        $defaults = [
            [
                'command' => 'customers:find-duplicates',
                'name' => 'Customer Duplicate Scan',
                'description' => 'Scan for duplicate customer names and update cache table',
                'schedule' => 'daily',
                'time' => '07:00',
            ],
            [
                'command' => 'reports:send',
                'name' => 'Scheduled Email Reports',
                'description' => 'Check and send scheduled email reports',
                'schedule' => 'every_minute',
                'time' => '00:00',
            ],
            [
                'command' => 'report:weekly',
                'name' => 'Weekly Report',
                'description' => 'Send weekly workshop report to admins',
                'schedule' => 'weekly',
                'time' => '08:00',
                'day_of_week' => 1,
            ],
        ];

        foreach ($defaults as $task) {
            self::firstOrCreate(
                ['command' => $task['command']],
                $task
            );
        }
    }
}
