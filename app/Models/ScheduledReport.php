<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledReport extends Model
{
    const TYPE_UNINVOICED = 'uninvoiced';
    const TYPE_INVOICED = 'invoiced';
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
        'day_of_month',
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
     * Get available report types with descriptions
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_UNINVOICED => 'Uninvoiced Jobs Report',
            self::TYPE_INVOICED => 'Invoiced Jobs Report',
            self::TYPE_PERFORMANCE => 'SA Performance Report',
            self::TYPE_AGING => 'Aging Job Alerts',
            self::TYPE_PARTS_PENDING => 'Parts Pending Report',
        ];
    }

    /**
     * Get report type descriptions
     */
    public static function getTypeDescriptions(): array
    {
        return [
            self::TYPE_UNINVOICED => 'Summary of all uninvoiced jobs with franchise, SA, and work status breakdown',
            self::TYPE_INVOICED => 'Summary of invoiced jobs with amount breakdowns by franchise and department',
            self::TYPE_PERFORMANCE => 'Service Advisor performance metrics including job counts and sales',
            self::TYPE_AGING => 'Alert for jobs that have been open longer than the specified threshold',
            self::TYPE_PARTS_PENDING => 'List of jobs waiting for parts to arrive',
        ];
    }

    /**
     * Get available filters for each report type
     */
    public static function getAvailableFilters(string $type): array
    {
        $commonFilters = ['franchise', 'service_advisor', 'foreman'];
        
        return match($type) {
            self::TYPE_UNINVOICED => [...$commonFilters, 'work_status', 'need_part', 'department'],
            self::TYPE_INVOICED => [...$commonFilters, 'department', 'type_sale', 'date_from', 'date_to'],
            self::TYPE_PERFORMANCE => ['date_from', 'date_to'],
            self::TYPE_AGING => [...$commonFilters, 'aging_days'],
            self::TYPE_PARTS_PENDING => $commonFilters,
            default => $commonFilters,
        };
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

        // Check if already sent in current period
        if ($this->last_sent_at) {
            $lastSent = $this->last_sent_at;
            
            switch ($this->schedule) {
                case self::SCHEDULE_DAILY:
                    // Already sent today
                    if ($lastSent->isToday()) {
                        return false;
                    }
                    break;
                    
                case self::SCHEDULE_WEEKLY:
                    // Already sent this week
                    if ($lastSent->isSameWeek($now)) {
                        return false;
                    }
                    break;
                    
                case self::SCHEDULE_MONTHLY:
                    // Already sent this month
                    if ($lastSent->isSameMonth($now)) {
                        return false;
                    }
                    break;
            }
        }

        // Check schedule type matches current day
        switch ($this->schedule) {
            case self::SCHEDULE_DAILY:
                return true;

            case self::SCHEDULE_WEEKLY:
                $dayMap = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 0];
                return $now->dayOfWeek === ($dayMap[$this->day_of_week] ?? 1);

            case self::SCHEDULE_MONTHLY:
                return $now->day === ($this->day_of_month ?? 1);

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
