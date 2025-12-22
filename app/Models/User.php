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
        return $this->hasAnyRole(['admin', 'manager', 'control_tower']);
    }

    /**
     * Check if user can manage master data
     */
    public function canManageMasterData(): bool
    {
        return $this->hasAnyRole(['admin', 'manager', 'control_tower']);
    }

    /**
     * Check if user can mark job as invoiced
     */
    public function canMarkInvoiced(): bool
    {
        return $this->hasAnyRole(['admin', 'control_tower']);
    }

    /**
     * Check if user can manage users
     */
    public function canManageUsers(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Get role display name
     */
    public function getRoleDisplayName(): string
    {
        return match($this->role) {
            'admin' => 'Administrator',
            'manager' => 'Workshop Manager',
            'control_tower' => 'Control Tower',
            'sparepart' => 'Sparepart',
            'sa' => 'Service Advisor',
            'foreman' => 'Foreman',
            'audit' => 'Audit',
            default => 'User',
        };
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
}

