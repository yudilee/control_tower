@extends('layouts.app')

@section('title', 'Role Management')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-shield-lock me-2"></i>Role Management</h1>
        <p class="text-muted">Manage roles and permissions</p>
    </div>
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Create Role
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Role Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th>Users</th>
                    <th>Type</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $role)
                <tr>
                    <td><strong>{{ $role->name }}</strong></td>
                    <td><code>{{ $role->slug }}</code></td>
                    <td>{{ $role->description ?? '-' }}</td>
                    <td><span class="badge bg-secondary">{{ $role->users_count }}</span></td>
                    <td>
                        @if($role->is_system)
                        <span class="badge bg-primary">System</span>
                        @else
                        <span class="badge bg-success">Custom</span>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('admin.roles.permissions', $role) }}" class="btn btn-outline-primary" title="Manage Permissions">
                                <i class="bi bi-key"></i>
                            </a>
                            @if(!$role->is_system)
                            <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this role?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
