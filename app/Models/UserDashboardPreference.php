<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User Dashboard Preference Model.
 * 
 * Stores each user's personalized dashboard widget configuration.
 * 
 * @property int $id
 * @property int $user_id
 * @property array|null $widget_config
 * @property array|null $theme_settings
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserDashboardPreference extends Model
{
    protected $fillable = [
        'user_id',
        'widget_config',
        'theme_settings',
    ];

    protected $casts = [
        'widget_config' => 'array',
        'theme_settings' => 'array',
    ];

    /**
     * Available widgets with their metadata.
     */
    const AVAILABLE_WIDGETS = [
        'stat_cards' => [
            'name' => 'Overview Stats',
            'description' => 'Uninvoiced, Needs Parts, Invoiced, In Workshop counts',
            'icon' => 'bar-chart-fill',
            'roles' => ['*'], // Available to all roles
            'default_size' => 'full',
        ],
        'my_jobs' => [
            'name' => 'My Jobs',
            'description' => 'Jobs assigned to you',
            'icon' => 'briefcase-fill',
            'roles' => ['service_advisor', 'foreman', 'admin', 'manager'],
            'default_size' => 'half',
        ],
        'work_status' => [
            'name' => 'Work Status Breakdown',
            'description' => 'Status distribution with counts',
            'icon' => 'pie-chart-fill',
            'roles' => ['*'],
            'default_size' => 'full',
        ],
        'recent_jobs' => [
            'name' => 'Recent Jobs',
            'description' => 'Last 5 jobs in system',
            'icon' => 'clock-history',
            'roles' => ['*'],
            'default_size' => 'half',
        ],
        'needs_parts' => [
            'name' => 'Needs Parts',
            'description' => 'Jobs waiting for parts',
            'icon' => 'gear-fill',
            'roles' => ['*'],
            'default_size' => 'half',
        ],
        'parts_tracking' => [
            'name' => 'Parts Tracking',
            'description' => 'Pending, Due Soon, Overdue orders',
            'icon' => 'box-seam-fill',
            'roles' => ['sparepart', 'admin', 'manager'],
            'default_size' => 'full',
        ],
        'job_trend_chart' => [
            'name' => 'Job Trend (7 Days)',
            'description' => 'New vs Invoiced jobs chart',
            'icon' => 'graph-up-arrow',
            'roles' => ['manager', 'admin', 'control_tower'],
            'default_size' => 'half',
        ],
        'aging_breakdown' => [
            'name' => 'Job Aging',
            'description' => 'Age distribution of uninvoiced jobs',
            'icon' => 'hourglass-split',
            'roles' => ['manager', 'admin', 'control_tower'],
            'default_size' => 'half',
        ],
        'sa_revenue' => [
            'name' => 'SA Revenue Ranking',
            'description' => 'Top 5 Service Advisors by revenue',
            'icon' => 'trophy-fill',
            'roles' => ['manager', 'admin', 'finance'],
            'default_size' => 'half',
        ],
        'quick_actions' => [
            'name' => 'Quick Actions',
            'description' => 'Add Job, View Kanban, Reports shortcuts',
            'icon' => 'lightning-fill',
            'roles' => ['*'],
            'default_size' => 'full',
        ],
        'bookings_today' => [
            'name' => "Today's Bookings",
            'description' => 'Scheduled bookings for today',
            'icon' => 'calendar-check-fill',
            'roles' => ['service_advisor', 'admin', 'control_tower'],
            'default_size' => 'half',
        ],
        'pending_invoices' => [
            'name' => 'Pending Invoices',
            'description' => 'Invoices awaiting payment',
            'icon' => 'receipt',
            'roles' => ['finance', 'admin', 'manager'],
            'default_size' => 'half',
        ],
        'saved_filters' => [
            'name' => 'My Saved Filters',
            'description' => 'Quick access to your saved report filters',
            'icon' => 'bookmark-fill',
            'roles' => ['*'],
            'default_size' => 'half',
        ],
    ];

    /**
     * Default widget configurations per role.
     */
    const ROLE_DEFAULTS = [
        'service_advisor' => [
            ['id' => 'stat_cards', 'enabled' => true, 'position' => 0],
            ['id' => 'my_jobs', 'enabled' => true, 'position' => 1],
            ['id' => 'work_status', 'enabled' => true, 'position' => 2],
            ['id' => 'bookings_today', 'enabled' => true, 'position' => 3],
            ['id' => 'quick_actions', 'enabled' => true, 'position' => 4],
            ['id' => 'needs_parts', 'enabled' => true, 'position' => 5],
        ],
        'foreman' => [
            ['id' => 'stat_cards', 'enabled' => true, 'position' => 0],
            ['id' => 'my_jobs', 'enabled' => true, 'position' => 1],
            ['id' => 'needs_parts', 'enabled' => true, 'position' => 2],
            ['id' => 'work_status', 'enabled' => true, 'position' => 3],
            ['id' => 'quick_actions', 'enabled' => true, 'position' => 4],
        ],
        'finance' => [
            ['id' => 'stat_cards', 'enabled' => true, 'position' => 0],
            ['id' => 'pending_invoices', 'enabled' => true, 'position' => 1],
            ['id' => 'sa_revenue', 'enabled' => true, 'position' => 2],
            ['id' => 'quick_actions', 'enabled' => true, 'position' => 3],
        ],
        'sparepart' => [
            ['id' => 'stat_cards', 'enabled' => true, 'position' => 0],
            ['id' => 'parts_tracking', 'enabled' => true, 'position' => 1],
            ['id' => 'needs_parts', 'enabled' => true, 'position' => 2],
            ['id' => 'quick_actions', 'enabled' => true, 'position' => 3],
        ],
        'manager' => [
            ['id' => 'stat_cards', 'enabled' => true, 'position' => 0],
            ['id' => 'job_trend_chart', 'enabled' => true, 'position' => 1],
            ['id' => 'aging_breakdown', 'enabled' => true, 'position' => 2],
            ['id' => 'sa_revenue', 'enabled' => true, 'position' => 3],
            ['id' => 'work_status', 'enabled' => true, 'position' => 4],
            ['id' => 'quick_actions', 'enabled' => true, 'position' => 5],
        ],
        'admin' => [
            ['id' => 'stat_cards', 'enabled' => true, 'position' => 0],
            ['id' => 'work_status', 'enabled' => true, 'position' => 1],
            ['id' => 'job_trend_chart', 'enabled' => true, 'position' => 2],
            ['id' => 'aging_breakdown', 'enabled' => true, 'position' => 3],
            ['id' => 'sa_revenue', 'enabled' => true, 'position' => 4],
            ['id' => 'parts_tracking', 'enabled' => true, 'position' => 5],
            ['id' => 'quick_actions', 'enabled' => true, 'position' => 6],
            ['id' => 'recent_jobs', 'enabled' => true, 'position' => 7],
            ['id' => 'needs_parts', 'enabled' => true, 'position' => 8],
        ],
        'control_tower' => [
            ['id' => 'stat_cards', 'enabled' => true, 'position' => 0],
            ['id' => 'work_status', 'enabled' => true, 'position' => 1],
            ['id' => 'job_trend_chart', 'enabled' => true, 'position' => 2],
            ['id' => 'quick_actions', 'enabled' => true, 'position' => 3],
            ['id' => 'recent_jobs', 'enabled' => true, 'position' => 4],
        ],
    ];

    /**
     * Relationship to User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the effective widget configuration.
     * Returns user's config or role defaults if not set.
     */
    public function getEffectiveWidgets(): array
    {
        if (!empty($this->widget_config['widgets'])) {
            return $this->widget_config['widgets'];
        }

        return self::getDefaultForRole($this->user->role);
    }

    /**
     * Get enabled widgets sorted by position.
     */
    public function getEnabledWidgets(): array
    {
        $widgets = $this->getEffectiveWidgets();
        
        // Filter enabled and sort by position
        $enabled = array_filter($widgets, fn($w) => $w['enabled'] ?? true);
        usort($enabled, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
        
        return $enabled;
    }

    /**
     * Update widget configuration.
     */
    public function setWidgetConfig(array $widgets): self
    {
        $this->widget_config = ['widgets' => $widgets];
        $this->save();
        
        return $this;
    }

    /**
     * Reset to role default configuration.
     */
    public function resetToDefault(): self
    {
        $this->widget_config = ['widgets' => self::getDefaultForRole($this->user->role)];
        $this->save();
        
        return $this;
    }

    /**
     * Get default widget configuration for a role.
     */
    public static function getDefaultForRole(string $role): array
    {
        return self::ROLE_DEFAULTS[$role] ?? self::ROLE_DEFAULTS['control_tower'];
    }

    /**
     * Get widgets available for a specific role.
     */
    public static function getAvailableWidgetsForRole(string $role): array
    {
        return array_filter(self::AVAILABLE_WIDGETS, function ($widget) use ($role) {
            return in_array('*', $widget['roles']) || in_array($role, $widget['roles']);
        });
    }

    /**
     * Get or create preference for a user.
     */
    public static function getOrCreateForUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            ['widget_config' => ['widgets' => self::getDefaultForRole($user->role)]]
        );
    }
}
