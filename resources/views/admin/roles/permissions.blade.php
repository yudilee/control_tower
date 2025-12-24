@extends('layouts.app')

@section('title', 'Permissions: ' . $role->name)

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-key me-2"></i>{{ $role->name }} Permissions</h1>
        <p class="text-muted">Configure DocType and field-level permissions</p>
    </div>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Roles
    </a>
</div>

<form action="{{ route('admin.roles.update-permissions', $role) }}" method="POST">
    @csrf
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>DocType Permissions</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 25%;">DocType</th>
                            <th class="text-center">Read</th>
                            <th class="text-center">Write</th>
                            <th class="text-center">Create</th>
                            <th class="text-center">Delete</th>
                            <th class="text-center">Export</th>
                            <th>Fields</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($doctypes as $doctype => $label)
                        @php $perm = $permissions[$doctype] ?? null; @endphp
                        <tr>
                            <td>
                                <strong>{{ $label }}</strong>
                                <br><small class="text-muted">{{ $doctype }}</small>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="permissions[{{ $doctype }}][read]" value="1" {{ $perm?->can_read ? 'checked' : '' }}>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="permissions[{{ $doctype }}][write]" value="1" {{ $perm?->can_write ? 'checked' : '' }}>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="permissions[{{ $doctype }}][create]" value="1" {{ $perm?->can_create ? 'checked' : '' }}>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="permissions[{{ $doctype }}][delete]" value="1" {{ $perm?->can_delete ? 'checked' : '' }}>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="permissions[{{ $doctype }}][export]" value="1" {{ $perm?->can_export ? 'checked' : '' }}>
                            </td>
                            <td>
                                @if(in_array($doctype, ['Job', 'Vehicle', 'Booking', 'PdiRecord', 'TowingRecord']))
                                <a href="{{ route('admin.roles.field-permissions', [$role, $doctype]) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-list-columns me-1"></i>Fields
                                    @if(isset($fieldPermissions[$doctype]) && $fieldPermissions[$doctype]->count() > 0)
                                    <span class="badge bg-primary">{{ $fieldPermissions[$doctype]->count() }}</span>
                                    @endif
                                </a>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Permissions</button>
        </div>
    </div>
</form>

<div class="card mt-4">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Permission Legend</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <ul class="list-unstyled mb-0">
                    <li><strong>Read</strong> - View records</li>
                    <li><strong>Write</strong> - Edit existing records</li>
                    <li><strong>Create</strong> - Create new records</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="list-unstyled mb-0">
                    <li><strong>Delete</strong> - Delete records</li>
                    <li><strong>Export</strong> - Export data</li>
                    <li><strong>Fields</strong> - Configure field-level permissions</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
