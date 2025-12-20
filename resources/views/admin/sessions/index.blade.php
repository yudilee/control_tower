@extends('layouts.app')

@section('title', 'Session Manager')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-display me-2"></i>Session Manager</h1>
        <p class="text-muted">View and manage all active user sessions</p>
    </div>
    <div>
        <span class="badge bg-success fs-6"><i class="bi bi-circle-fill me-1"></i>{{ $sessions->where('last_active_at', '>=', now()->subMinutes(5))->count() }} Online</span>
        <span class="badge bg-secondary fs-6 ms-2">{{ $sessions->count() }} Total Sessions</span>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<!-- Filter by User -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Filter by User</label>
                <select name="user_id" class="form-select">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                        {{ $user->name }} ({{ $user->email }})
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Device Type</label>
                <select name="device" class="form-select">
                    <option value="">All Devices</option>
                    <option value="desktop" {{ request('device') == 'desktop' ? 'selected' : '' }}>Desktop</option>
                    <option value="mobile" {{ request('device') == 'mobile' ? 'selected' : '' }}>Mobile</option>
                    <option value="tablet" {{ request('device') == 'tablet' ? 'selected' : '' }}>Tablet</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="bi bi-filter me-1"></i>Filter</button>
                <a href="{{ route('admin.sessions.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Sessions Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>User</th>
                        <th>Device</th>
                        <th>Browser / Platform</th>
                        <th>IP Address</th>
                        <th>Last Active</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                    {{ strtoupper(substr($session->user->name ?? 'U', 0, 1)) }}
                                </div>
                                <div>
                                    <strong>{{ $session->user->name ?? 'Unknown' }}</strong>
                                    <br><small class="text-muted">{{ $session->user->email ?? '' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <i class="bi bi-{{ $session->device_icon }} fs-4 text-{{ $session->device_type === 'mobile' ? 'success' : 'primary' }}"></i>
                            <span class="ms-1">{{ ucfirst($session->device_type ?? 'Unknown') }}</span>
                        </td>
                        <td>
                            {{ $session->browser ?? 'Unknown' }} / {{ $session->platform ?? 'Unknown' }}
                        </td>
                        <td><code>{{ $session->ip_address ?? '-' }}</code></td>
                        <td>{{ $session->last_active_at?->diffForHumans() ?? 'Unknown' }}</td>
                        <td>
                            @if($session->last_active_at && $session->last_active_at >= now()->subMinutes(5))
                            <span class="badge bg-success"><i class="bi bi-circle-fill me-1"></i>Online</span>
                            @elseif($session->last_active_at && $session->last_active_at >= now()->subHours(1))
                            <span class="badge bg-warning text-dark">Idle</span>
                            @else
                            <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <form action="{{ route('admin.sessions.terminate', $session) }}" method="POST" 
                                  onsubmit="return confirm('Terminate this session for {{ $session->user->name ?? 'user' }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Terminate Session">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-display display-4 d-block mb-3 opacity-25"></i>
                            No active sessions found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $sessions->links() }}
</div>
@endsection
