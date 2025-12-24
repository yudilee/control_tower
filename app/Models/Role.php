<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Users with this role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * DocType permissions for this role
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Field permissions for this role
     */
    public function fieldPermissions(): HasMany
    {
        return $this->hasMany(FieldPermission::class);
    }

    /**
     * Get permission for a doctype
     */
    public function getPermission(string $doctype): ?Permission
    {
        return $this->permissions()->where('doctype', $doctype)->first();
    }

    /**
     * Check if role can perform action on doctype
     */
    public function can(string $doctype, string $action): bool
    {
        $permission = $this->getPermission($doctype);
        if (!$permission) {
            return false;
        }
        
        $column = 'can_' . $action;
        return $permission->{$column} ?? false;
    }

    /**
     * Check if role can write to specific field
     */
    public function canWriteField(string $doctype, string $field): bool
    {
        // If no field permissions defined, check doctype write permission
        $fieldPerm = $this->fieldPermissions()
            ->where('doctype', $doctype)
            ->where('field', $field)
            ->first();
        
        if ($fieldPerm) {
            return $fieldPerm->can_write;
        }
        
        // Default to doctype write permission
        return $this->can($doctype, 'write');
    }

    /**
     * Get available doctypes for permissions
     */
    public static function getDocTypes(): array
    {
        return [
            'Job' => 'Workshop Jobs',
            'Vehicle' => 'Vehicles',
            'Booking' => 'Service Bookings',
            'PdiRecord' => 'PDI Records',
            'TowingRecord' => 'Towing Records',
            'Customer' => 'Customers',
            'Remark' => 'Job Remarks',
            'Report' => 'Reports',
            'User' => 'User Management',
            'Backup' => 'Database Backups',
            'Settings' => 'System Settings',
        ];
    }
}
