<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledReport extends Model
{
    const TYPE_UNINVOICED = 'uninvoiced';
    const TYPE_PERFORMANCE = 'performance';
    const TYPE_AGING = 'aging';
    const TYPE_PARTS_PENDING = 'parts_pending';

    const SCHEDULE_DAILY = 'daily';
    const SCHEDULE_WEEKLY = 'weekly';
    const SCHEDULE_MONTHLY = 'monthly';

    protected $fillable = [
        'name',
        'type',
        'schedule',
        'time',
        'day_of_week',
        'recipients',
        'config',
        'is_active',
        'last_sent_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'config' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    /**
     * Get available report types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_UNINVOICED => 'Daily Uninvoiced Summary',
            self::TYPE_PERFORMANCE => 'SA Performance Report',
            self::TYPE_AGING => 'Aging Job Alerts',
            self::TYPE_PARTS_PENDING => 'Parts Pending Report',
        ];
    }

    /**
     * Get available schedules
     */
    public static function getSchedules(): array
    {
        return [
            self::SCHEDULE_DAILY => 'Daily',
            self::SCHEDULE_WEEKLY => 'Weekly',
            self::SCHEDULE_MONTHLY => 'Monthly (1st of month)',
        ];
    }

    /**
     * Get days of week
     */
    public static function getDaysOfWeek(): array
    {
        return [
            'mon' => 'Monday',
            'tue' => 'Tuesday',
            'wed' => 'Wednesday',
            'thu' => 'Thursday',
            'fri' => 'Friday',
            'sat' => 'Saturday',
            'sun' => 'Sunday',
        ];
    }

    /**
     * Check if report should run now
     */
    public function shouldRunNow(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        $scheduledTime = $this->time;
        $currentTime = $now->format('H:i');

        // Check if within 5 minutes of scheduled time
        if (abs(strtotime($currentTime) - strtotime($scheduledTime)) > 300) {
            return false;
        }

        // Check schedule type
        switch ($this->schedule) {
            case self::SCHEDULE_DAILY:
                return true;

            case self::SCHEDULE_WEEKLY:
                $dayMap = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 0];
                return $now->dayOfWeek === ($dayMap[$this->day_of_week] ?? 1);

            case self::SCHEDULE_MONTHLY:
                return $now->day === 1;

            default:
                return false;
        }
    }

    /**
     * Get config value with default
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}
