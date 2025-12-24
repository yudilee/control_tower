<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\FieldPermission;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Display roles list
     */
    public function index()
    {
        $roles = Role::withCount('users')->orderBy('name')->get();
        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show role create form
     */
    public function create()
    {
        $doctypes = Role::getDocTypes();
        return view('admin.roles.create', compact('doctypes'));
    }

    /**
     * Store new role
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string|max:500',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'is_system' => false,
        ]);

        return redirect()->route('admin.roles.permissions', $role)->with('success', 'Role created. Now configure permissions.');
    }

    /**
     * Show permissions matrix for a role
     */
    public function permissions(Role $role)
    {
        $doctypes = Role::getDocTypes();
        $permissions = $role->permissions->keyBy('doctype');
        $fieldPermissions = $role->fieldPermissions->groupBy('doctype');

        return view('admin.roles.permissions', compact('role', 'doctypes', 'permissions', 'fieldPermissions'));
    }

    /**
     * Update doctype permissions for a role
     */
    public function updatePermissions(Request $request, Role $role)
    {
        $doctypes = array_keys(Role::getDocTypes());

        foreach ($doctypes as $doctype) {
            Permission::updateOrCreate(
                ['role_id' => $role->id, 'doctype' => $doctype],
                [
                    'can_read' => $request->boolean("permissions.{$doctype}.read"),
                    'can_write' => $request->boolean("permissions.{$doctype}.write"),
                    'can_create' => $request->boolean("permissions.{$doctype}.create"),
                    'can_delete' => $request->boolean("permissions.{$doctype}.delete"),
                    'can_export' => $request->boolean("permissions.{$doctype}.export"),
                ]
            );
        }

        return back()->with('success', 'Permissions updated.');
    }

    /**
     * Show field permissions for a doctype
     */
    public function fieldPermissions(Role $role, string $doctype)
    {
        $fields = FieldPermission::getFieldsForDocType($doctype);
        $fieldPerms = $role->fieldPermissions()
            ->where('doctype', $doctype)
            ->get()
            ->keyBy('field');

        return view('admin.roles.field-permissions', compact('role', 'doctype', 'fields', 'fieldPerms'));
    }

    /**
     * Update field permissions
     */
    public function updateFieldPermissions(Request $request, Role $role, string $doctype)
    {
        $fields = FieldPermission::getFieldsForDocType($doctype);

        foreach (array_keys($fields) as $field) {
            FieldPermission::updateOrCreate(
                ['role_id' => $role->id, 'doctype' => $doctype, 'field' => $field],
                [
                    'can_read' => $request->boolean("fields.{$field}.read"),
                    'can_write' => $request->boolean("fields.{$field}.write"),
                ]
            );
        }

        return redirect()->route('admin.roles.permissions', $role)->with('success', 'Field permissions updated.');
    }

    /**
     * Edit role
     */
    public function edit(Role $role)
    {
        return view('admin.roles.edit', compact('role'));
    }

    /**
     * Update role
     */
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    /**
     * Delete role
     */
    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        $role->delete();
        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }
}
