<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use App\Traits\Auditable; // Add this line

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Auditable; // Add this trait

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'column_preferences',
        'booking_preferences',
        'pdi_preferences',
        'towing_preferences',
        'vehicle_preferences',
        'customer_preferences',
        'role',
        'auth_source',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'column_preferences' => 'array',
            'booking_preferences' => 'array',
            'pdi_preferences' => 'array',
            'towing_preferences' => 'array',
            'vehicle_preferences' => 'array',
            'customer_preferences' => 'array',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Get the foreman linked to this user
     */
    public function foreman(): HasOne
    {
        return $this->hasOne(Foreman::class);
    }

    /**
     * Get the service advisor linked to this user
     */
    public function serviceAdvisor(): HasOne
    {
        return $this->hasOne(ServiceAdvisor::class);
    }

    /**
     * Get the remarks created by this user
     */
    public function remarks(): HasMany
    {
        return $this->hasMany(Remark::class);
    }

    /**
     * Get the notifications for the user.
     * Overrides Notifiable trait to use standard hasMany instead of morphMany
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->latest();
    }

    /**
     * Get the entity's unread notifications.
     */
    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->unread();
    }

    /**
     * Get push subscriptions for this user
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Get the user's dashboard preference settings.
     */
    public function dashboardPreference(): HasOne
    {
        return $this->hasOne(UserDashboardPreference::class);
    }

    /**
     * Get or create dashboard preferences for this user.
     */
    public function getDashboardPreference(): UserDashboardPreference
    {
        return UserDashboardPreference::getOrCreateForUser($this);
    }

    /**
     * Get column preferences with defaults
     */
    public function getColumnPrefs(): array
    {
        $defaults = [
            'no' => true,
            'wip' => true,
            'created' => true,
            'reg_no' => true,
            'customer' => true,
            'sa' => true,
            'foreman' => false,
            'unit' => false,
            'labour' => false,
            'part' => false,
            'total' => true,
            'rq' => false,
            'remarks' => true,
            'status' => true,
        ];

        return array_merge($defaults, $this->column_preferences ?? []);
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Check if user can edit jobs/data
     */
    public function canEdit(): bool
    {
        return $this->hasAnyRole(['admin', 'manager', 'control_tower']);
    }

    /**
     * Check if user can add remarks
     */
    public function canAddRemarks(): bool
    {
        return $this->hasAnyRole(['admin', 'manager', 'control_tower', 'sparepart', 'sa', 'foreman']);
    }

    /**
     * Check if user can import data
     */
    public function canImport(): bool
    {
        return $this->canDo('Job', 'create') || $this->hasAnyRole(['admin', 'manager', 'control_tower']);
    }

    /**
     * Check if user can manage master data
     */
    public function canManageMasterData(): bool
    {
        return $this->canDo('Settings', 'write') || $this->hasAnyRole(['admin', 'manager', 'control_tower']);
    }

    /**
     * Check if user can mark job as invoiced
     */
    public function canMarkInvoiced(): bool
    {
        return $this->canWriteField('Job', 'invoiced') || $this->hasAnyRole(['admin', 'control_tower']);
    }

    /**
     * Check if user can manage users
     */
    public function canManageUsers(): bool
    {
        return $this->canDo('User', 'write') || $this->hasRole('admin');
    }

    /**
     * Get role display name
     */
    public function getRoleDisplayName(): string
    {
        // Try new role system first
        $roles = $this->roles;
        if ($roles->isNotEmpty()) {
            return $roles->pluck('name')->implode(', ');
        }
        
        // Fallback to old role field
        return match($this->role) {
            'admin' => 'Administrator',
            'manager' => 'Workshop Manager',
            'control_tower' => 'Control Tower',
            'sparepart' => 'Sparepart',
            'sa' => 'Service Advisor',
            'foreman' => 'Foreman',
            'audit' => 'Audit',
            'finance' => 'Finance',
            default => 'User',
        };
    }

    /**
     * Check if user is Finance role
     */
    public function isFinance(): bool
    {
        return $this->role === 'finance';
    }

    /**
     * Check if user is Control Tower role
     */
    public function isControlTower(): bool
    {
        return $this->role === 'control_tower';
    }

    /**
     * Check if user can edit Kanban (drag/drop work status) in general
     * SA/Foreman can only edit their assigned jobs (checked separately)
     */
    public function canEditKanban(): bool
    {
        return $this->hasAnyRole(['admin', 'manager', 'control_tower', 'finance', 'sa', 'foreman']);
    }

    /**
     * Check if user can update work status for a specific job
     * Returns array with 'allowed' boolean and 'reason' message
     */
    public function canUpdateJobWorkStatus(Job $job): array
    {
        // Admin, Manager, and Control Tower can update any job
        if ($this->hasAnyRole(['admin', 'manager', 'control_tower'])) {
            return ['allowed' => true, 'reason' => null];
        }

        // Finance can only update invoiced jobs (and only specific statuses - checked in controller)
        if ($this->isFinance()) {
            if ($job->status !== 'invoiced') {
                return ['allowed' => false, 'reason' => 'Finance can only update work status for invoiced jobs.'];
            }
            return ['allowed' => true, 'reason' => null];
        }

        // Service Advisor can only update their assigned jobs
        if ($this->role === 'sa') {
            $linkedSaName = $this->serviceAdvisor?->name;
            if (!$linkedSaName) {
                return ['allowed' => false, 'reason' => 'Your user account is not linked to a Service Advisor in master data.'];
            }
            if (strtolower(trim($job->service_advisor ?? '')) !== strtolower(trim($linkedSaName))) {
                return ['allowed' => false, 'reason' => "This job is assigned to SA '{$job->service_advisor}', not you."];
            }
            return ['allowed' => true, 'reason' => null];
        }

        // Foreman can only update their assigned jobs
        if ($this->role === 'foreman') {
            $linkedForemanName = $this->foreman?->name;
            if (!$linkedForemanName) {
                return ['allowed' => false, 'reason' => 'Your user account is not linked to a Foreman in master data.'];
            }
            if (strtolower(trim($job->foreman ?? '')) !== strtolower(trim($linkedForemanName))) {
                return ['allowed' => false, 'reason' => "This job is assigned to Foreman '{$job->foreman}', not you."];
            }
            return ['allowed' => true, 'reason' => null];
        }

        // Sparepart can only update jobs that need parts
        if ($this->role === 'sparepart') {
            if (!$job->need_part) {
                return ['allowed' => false, 'reason' => 'Sparepart role can only update jobs that require parts.'];
            }
            return ['allowed' => true, 'reason' => null];
        }

        return ['allowed' => false, 'reason' => 'You do not have permission to update work status.'];
    }

    /**
     * Check if user can add remark to a specific job
     */
    public function canAddRemarkToJob(Job $job): bool
    {
        // Admin, Manager, and Control Tower can add remarks to any job
        if ($this->hasAnyRole(['admin', 'manager', 'control_tower'])) {
            return true;
        }

        // Finance can only add remarks to invoiced jobs (or jobs at work_status step 10+)
        if ($this->isFinance()) {
            return $job->status === 'invoiced';
        }

        // Service Advisor can only add remarks to jobs they are assigned to
        // Compare job's service_advisor field with the linked ServiceAdvisor's name
        if ($this->role === 'sa') {
            $linkedSaName = $this->serviceAdvisor?->name;
            if (!$linkedSaName) {
                return false; // No linked SA record
            }
            return strtolower(trim($job->service_advisor ?? '')) === strtolower(trim($linkedSaName));
        }

        // Foreman can only add remarks to jobs they are assigned to
        // Compare job's foreman field with the linked Foreman's name
        if ($this->role === 'foreman') {
            $linkedForemanName = $this->foreman?->name;
            if (!$linkedForemanName) {
                return false; // No linked Foreman record
            }
            return strtolower(trim($job->foreman ?? '')) === strtolower(trim($linkedForemanName));
        }

        // Sparepart can only add remarks to jobs that need parts
        if ($this->role === 'sparepart') {
            return $job->need_part === true;
        }

        return false;
    }

    /**
     * Get initials for avatar display
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($this->name, 0, 2));
    }

    // ========== NEW ROLE PERMISSION SYSTEM ==========

    /**
     * Get user's roles (many-to-many)
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Check if user can perform action on doctype
     * Checks all user's roles - any permission grants access
     */
    public function canDo(string $doctype, string $action): bool
    {
        // Admin role always has full access
        if ($this->role === 'admin' || $this->roles()->where('slug', 'administrator')->exists()) {
            return true;
        }

        foreach ($this->roles as $role) {
            if ($role->can($doctype, $action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can read a doctype
     */
    public function canRead(string $doctype): bool
    {
        return $this->canDo($doctype, 'read');
    }

    /**
     * Check if user can write to a doctype
     */
    public function canWrite(string $doctype): bool
    {
        return $this->canDo($doctype, 'write');
    }

    /**
     * Check if user can write to a specific field
     * Checks all user's roles - any permission grants access
     */
    public function canWriteField(string $doctype, string $field): bool
    {
        // Admin always has full access
        if ($this->role === 'admin' || $this->roles()->where('slug', 'administrator')->exists()) {
            return true;
        }

        foreach ($this->roles as $role) {
            if ($role->canWriteField($doctype, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can read a specific field
     */
    public function canReadField(string $doctype, string $field): bool
    {
        // Admin always has full access
        if ($this->role === 'admin' || $this->roles()->where('slug', 'administrator')->exists()) {
            return true;
        }

        foreach ($this->roles as $role) {
            $fieldPerm = $role->fieldPermissions()
                ->where('doctype', $doctype)
                ->where('field', $field)
                ->first();
            
            if ($fieldPerm && $fieldPerm->can_read) {
                return true;
            }
        }

        // Default: if no field permission, check doctype read
        return $this->canRead($doctype);
    }

    /**
     * Assign role to user
     */
    public function assignRole(string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->first();
        if ($role && !$this->roles->contains($role->id)) {
            $this->roles()->attach($role->id);
        }
    }

    /**
     * Remove role from user
     */
    public function removeRole(string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->first();
        if ($role) {
            $this->roles()->detach($role->id);
        }
    }
}
