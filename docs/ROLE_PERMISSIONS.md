# Role Permission System Documentation

This application uses an ERPNext-style dynamic role permission system.

## Overview

- **Roles** - Customizable roles (admin can create new ones)
- **DocType Permissions** - Read/Write/Create/Delete per model
- **Field Permissions** - Control which fields each role can edit

## Adding a New DocType

When you add new features/menus, follow these steps:

### Step 1: Register DocType in Role Model

Edit `app/Models/Role.php`:

```php
public static function getDocTypes(): array
{
    return [
        'Job' => 'Workshop Jobs',
        'Vehicle' => 'Vehicles',
        // ... existing ...
        'NewFeature' => 'New Feature Name',  // ← Add here
    ];
}
```

### Step 2: Add Field Definitions (Optional)

If your DocType has editable fields, edit `app/Models/FieldPermission.php`:

```php
public static function getFieldsForDocType(string $doctype): array
{
    $fields = [
        // ... existing ...
        'NewFeature' => [
            'title' => 'Title',
            'status' => 'Status',
            'description' => 'Description',
        ],
    ];
    return $fields[$doctype] ?? [];
}
```

### Step 3: Use Permissions in Code

**In Controller:**
```php
public function index()
{
    if (!auth()->user()->canRead('NewFeature')) {
        abort(403, 'Access denied');
    }
    // ...
}

public function store(Request $request)
{
    if (!auth()->user()->canDo('NewFeature', 'create')) {
        abort(403);
    }
    // ...
}
```

**In Views:**
```blade
{{-- Show field only if user can read it --}}
@if(auth()->user()->canReadField('NewFeature', 'title'))
    <input name="title" 
           value="{{ $item->title }}"
           @if(!auth()->user()->canWriteField('NewFeature', 'title')) 
               disabled readonly 
           @endif>
@endif
```

### Step 4: Update Default Permissions

Edit `database/seeders/RolePermissionSeeder.php` to set defaults for existing roles.

Then run:
```bash
php artisan db:seed --class=RolePermissionSeeder
```

## Available Permission Methods

| Method | Description |
|--------|-------------|
| `canDo($doctype, $action)` | Check read/write/create/delete/export |
| `canRead($doctype)` | Shortcut for canDo($doctype, 'read') |
| `canWrite($doctype)` | Shortcut for canDo($doctype, 'write') |
| `canReadField($doctype, $field)` | Check field read permission |
| `canWriteField($doctype, $field)` | Check field write permission |

## Admin UI

Access Role Management at: **Administration → Role Permissions**

From there you can:
- Create custom roles
- Configure DocType permissions (matrix view)
- Configure Field permissions per DocType
