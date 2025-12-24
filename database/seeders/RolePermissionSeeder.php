<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\FieldPermission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $doctypes = ['Job', 'Vehicle', 'Booking', 'PdiRecord', 'TowingRecord', 'Customer', 'Remark', 'Report', 'User', 'Backup', 'Settings'];
        
        // Create default roles
        $roles = [
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'Full system access', 'is_system' => true],
            ['name' => 'Workshop Manager', 'slug' => 'manager', 'description' => 'Full data access, limited admin', 'is_system' => true],
            ['name' => 'Control Tower', 'slug' => 'control_tower', 'description' => 'Manage jobs and vehicles', 'is_system' => true],
            ['name' => 'Service Advisor', 'slug' => 'sa', 'description' => 'View jobs and add remarks', 'is_system' => true],
            ['name' => 'Foreman', 'slug' => 'foreman', 'description' => 'View jobs and add remarks', 'is_system' => true],
            ['name' => 'Sparepart', 'slug' => 'sparepart', 'description' => 'Manage spare parts fields only', 'is_system' => true],
            ['name' => 'Viewer', 'slug' => 'viewer', 'description' => 'Read-only access', 'is_system' => true],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(['slug' => $roleData['slug']], $roleData);
        }

        // Setup permissions for each role
        $this->setupAdminPermissions($doctypes);
        $this->setupManagerPermissions($doctypes);
        $this->setupControlTowerPermissions($doctypes);
        $this->setupSAPermissions($doctypes);
        $this->setupForemanPermissions($doctypes);
        $this->setupSparepartPermissions();
        $this->setupViewerPermissions($doctypes);
    }

    private function setupAdminPermissions(array $doctypes): void
    {
        $role = Role::where('slug', 'administrator')->first();
        foreach ($doctypes as $doctype) {
            Permission::updateOrCreate(
                ['role_id' => $role->id, 'doctype' => $doctype],
                ['can_read' => true, 'can_write' => true, 'can_create' => true, 'can_delete' => true, 'can_export' => true]
            );
        }
    }

    private function setupManagerPermissions(array $doctypes): void
    {
        $role = Role::where('slug', 'manager')->first();
        foreach ($doctypes as $doctype) {
            $canDelete = !in_array($doctype, ['User', 'Backup', 'Settings']);
            $canWrite = !in_array($doctype, ['Backup']);
            Permission::updateOrCreate(
                ['role_id' => $role->id, 'doctype' => $doctype],
                ['can_read' => true, 'can_write' => $canWrite, 'can_create' => $canWrite, 'can_delete' => $canDelete, 'can_export' => true]
            );
        }
    }

    private function setupControlTowerPermissions(array $doctypes): void
    {
        $role = Role::where('slug', 'control_tower')->first();
        $permissions = [
            'Job' => ['read' => true, 'write' => true, 'create' => true, 'delete' => false, 'export' => true],
            'Vehicle' => ['read' => true, 'write' => true, 'create' => true, 'delete' => false, 'export' => true],
            'Booking' => ['read' => true, 'write' => true, 'create' => true, 'delete' => false, 'export' => true],
            'PdiRecord' => ['read' => true, 'write' => true, 'create' => true, 'delete' => false, 'export' => true],
            'TowingRecord' => ['read' => true, 'write' => true, 'create' => true, 'delete' => false, 'export' => true],
            'Customer' => ['read' => true, 'write' => true, 'create' => true, 'delete' => false, 'export' => true],
            'Remark' => ['read' => true, 'write' => true, 'create' => true, 'delete' => false, 'export' => false],
            'Report' => ['read' => true, 'write' => false, 'create' => false, 'delete' => false, 'export' => true],
        ];

        foreach ($permissions as $doctype => $perms) {
            Permission::updateOrCreate(
                ['role_id' => $role->id, 'doctype' => $doctype],
                ['can_read' => $perms['read'], 'can_write' => $perms['write'], 'can_create' => $perms['create'], 'can_delete' => $perms['delete'], 'can_export' => $perms['export']]
            );
        }
    }

    private function setupSAPermissions(array $doctypes): void
    {
        $role = Role::where('slug', 'sa')->first();
        $allowRead = ['Job', 'Vehicle', 'Booking', 'Customer', 'Remark', 'Report'];
        $allowWrite = ['Remark'];

        foreach ($doctypes as $doctype) {
            $canRead = in_array($doctype, $allowRead);
            $canWrite = in_array($doctype, $allowWrite);
            Permission::updateOrCreate(
                ['role_id' => $role->id, 'doctype' => $doctype],
                ['can_read' => $canRead, 'can_write' => $canWrite, 'can_create' => $canWrite, 'can_delete' => false, 'can_export' => $canRead]
            );
        }
    }

    private function setupForemanPermissions(array $doctypes): void
    {
        $role = Role::where('slug', 'foreman')->first();
        $allowRead = ['Job', 'Vehicle', 'Booking', 'Customer', 'Remark', 'Report'];
        $allowWrite = ['Remark'];

        foreach ($doctypes as $doctype) {
            $canRead = in_array($doctype, $allowRead);
            $canWrite = in_array($doctype, $allowWrite);
            Permission::updateOrCreate(
                ['role_id' => $role->id, 'doctype' => $doctype],
                ['can_read' => $canRead, 'can_write' => $canWrite, 'can_create' => $canWrite, 'can_delete' => false, 'can_export' => $canRead]
            );
        }
    }

    private function setupSparepartPermissions(): void
    {
        $role = Role::where('slug', 'sparepart')->first();
        
        // DocType level: read-only for jobs
        Permission::updateOrCreate(
            ['role_id' => $role->id, 'doctype' => 'Job'],
            ['can_read' => true, 'can_write' => false, 'can_create' => false, 'can_delete' => false, 'can_export' => false]
        );

        // Field level: can only edit specific fields
        $writableFields = ['rq_no', 'order_part', 'other_part'];
        $allJobFields = ['wip', 'customer_name', 'vehicle_id', 'service_advisor_id', 'foreman_id', 'unit', 'labour', 'part', 'total', 'rq_no', 'order_part', 'other_part', 'need_part', 'status', 'invoiced', 'invoice_no', 'invoice_date'];

        foreach ($allJobFields as $field) {
            FieldPermission::updateOrCreate(
                ['role_id' => $role->id, 'doctype' => 'Job', 'field' => $field],
                ['can_read' => true, 'can_write' => in_array($field, $writableFields)]
            );
        }
    }

    private function setupViewerPermissions(array $doctypes): void
    {
        $role = Role::where('slug', 'viewer')->first();
        $allowRead = ['Job', 'Vehicle', 'Booking', 'PdiRecord', 'TowingRecord', 'Customer', 'Remark', 'Report'];

        foreach ($doctypes as $doctype) {
            $canRead = in_array($doctype, $allowRead);
            Permission::updateOrCreate(
                ['role_id' => $role->id, 'doctype' => $doctype],
                ['can_read' => $canRead, 'can_write' => false, 'can_create' => false, 'can_delete' => false, 'can_export' => $canRead]
            );
        }
    }
}
