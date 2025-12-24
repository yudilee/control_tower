@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-people me-2"></i>User Management</h1>
        <p class="text-muted mb-0">Manage user roles and permissions</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- User List -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list me-2"></i>System Users</span>
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="{{ request('search') }}" style="width: 150px;">
                        <select name="role" class="form-select form-select-sm" style="width: 130px;" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            @foreach($roles as $key => $label)
                                <option value="{{ $key }}" {{ request('role') == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Source</th>
                            <th>Role</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td>
                                <strong>{{ $user->name }}</strong>
                                @if($user->id == auth()->id())
                                    <span class="badge bg-info ms-1">You</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $user->email }}</td>
                            <td>
                                @if(!$user->auth_source || $user->auth_source === 'local')
                                    <span class="badge bg-secondary"><i class="bi bi-database me-1"></i>Internal</span>
                                @else
                                    <span class="badge bg-primary"><i class="bi bi-server me-1"></i>LDAP</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $roleColors = [
                                        'admin' => 'danger',
                                        'manager' => 'warning',
                                        'control_tower' => 'primary',
                                        'sparepart' => 'info',
                                        'sa' => 'success',
                                        'foreman' => 'success',
                                        'audit' => 'secondary',
                                        'user' => 'dark',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $roleColors[$user->role] ?? 'secondary' }}">
                                    {{ $user->getRoleDisplayName() }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if((!$user->auth_source || $user->auth_source === 'local') && $user->id !== auth()->id())
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete user {{ $user->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No users found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
            <div class="card-footer">
                {{ $users->links() }}
            </div>
            @endif
        </div>
    </div>

    <div class="col-md-4">
        <!-- Assign Role to LDAP User -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-plus me-2"></i>Add LDAP User
            </div>
            <div class="card-body">
                <form action="{{ route('admin.users.assign-role') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Search LDAP <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" id="ldapSearch" class="form-control" placeholder="Username or name...">
                            <button type="button" class="btn btn-outline-primary" onclick="searchLdap()">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div id="ldapResults" class="list-group mt-2"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="ldapUsername" class="form-control" readonly required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="ldapName" class="form-control" readonly required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="ldapEmail" class="form-control" readonly required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            @foreach($roles as $key => $label)
                                @if($key !== 'user')
                                <option value="{{ $key }}">{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-check me-1"></i>Assign Role
                    </button>
                </form>
            </div>
        </div>

        <!-- Role Legend -->
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-shield-check me-2"></i>Role Permissions</span>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-gear"></i>
                </a>
            </div>
            <div class="card-body">
                @php
                    $allRoles = \App\Models\Role::withCount('permissions')->orderBy('name')->get();
                    $roleColors = ['administrator' => 'danger', 'manager' => 'warning', 'control_tower' => 'primary', 'sparepart' => 'info', 'sa' => 'success', 'foreman' => 'success', 'viewer' => 'secondary'];
                @endphp
                <ul class="list-unstyled small mb-0">
                    @foreach($allRoles as $role)
                    <li class="mb-2">
                        <span class="badge bg-{{ $roleColors[$role->slug] ?? 'secondary' }}">{{ $role->name }}</span>
                        {{ $role->description ?? 'No description' }}
                    </li>
                    @endforeach
                </ul>
                @if($allRoles->isEmpty())
                <p class="text-muted mb-0">No roles defined. <a href="{{ route('admin.roles.index') }}">Create roles</a></p>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function searchLdap() {
    const search = document.getElementById('ldapSearch').value;
    if (search.length < 2) {
        alert('Please enter at least 2 characters');
        return;
    }

    fetch('{{ route("admin.users.search-ldap") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ search: search })
    })
    .then(r => r.json())
    .then(data => {
        const container = document.getElementById('ldapResults');
        container.innerHTML = '';
        
        if (!data.success) {
            container.innerHTML = '<div class="list-group-item text-danger">' + data.message + '</div>';
            return;
        }

        if (!data.users || data.users.length === 0) {
            container.innerHTML = '<div class="list-group-item text-muted">No users found</div>';
            return;
        }

        data.users.forEach(user => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            item.innerHTML = '<strong>' + user.name + '</strong><br><small class="text-muted">' + user.email + '</small>';
            item.onclick = function() {
                document.getElementById('ldapUsername').value = user.username;
                document.getElementById('ldapName').value = user.name;
                document.getElementById('ldapEmail').value = user.email;
                container.innerHTML = '';
            };
            container.appendChild(item);
        });
    })
    .catch(err => {
        console.error(err);
        alert('LDAP search failed');
    });
}
</script>
@endpush
@endsection
