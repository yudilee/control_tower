@extends('layouts.app')

@section('title', 'Field Permissions: ' . $doctype)

@section('content')
<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">Roles</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.roles.permissions', $role) }}">{{ $role->name }}</a></li>
            <li class="breadcrumb-item active">{{ $doctype }} Fields</li>
        </ol>
    </nav>
    <h1><i class="bi bi-list-columns me-2"></i>{{ $doctype }} Field Permissions</h1>
    <p class="text-muted">Configure which fields the "{{ $role->name }}" role can read/write</p>
</div>

<form action="{{ route('admin.roles.update-field-permissions', [$role, $doctype]) }}" method="POST">
    @csrf
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 40%;">Field</th>
                        <th class="text-center" style="width: 30%;">Can Read</th>
                        <th class="text-center" style="width: 30%;">Can Write</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fields as $field => $label)
                    @php $perm = $fieldPerms[$field] ?? null; @endphp
                    <tr>
                        <td>
                            <strong>{{ $label }}</strong>
                            <br><small class="text-muted">{{ $field }}</small>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input" name="fields[{{ $field }}][read]" value="1" {{ ($perm?->can_read ?? true) ? 'checked' : '' }}>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input" name="fields[{{ $field }}][write]" value="1" {{ $perm?->can_write ? 'checked' : '' }}>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Field Permissions</button>
            <a href="{{ route('admin.roles.permissions', $role) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</form>

<div class="alert alert-info mt-4">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Note:</strong> If a field has "Can Write" unchecked, that field will be displayed as read-only for users with this role.
</div>
@endsection
