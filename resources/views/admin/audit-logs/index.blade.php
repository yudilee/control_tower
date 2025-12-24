@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0"><i class="bi bi-journal-text me-2"></i>Audit Logs</h1>
            <p class="text-muted small">View all database changes and manage log archiving.</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('admin.audit-logs.archives') }}" class="btn btn-outline-secondary me-2">
                <i class="bi bi-archive me-1"></i>View Archives ({{ number_format($stats['archived']) }})
            </a>
            <form action="{{ route('admin.audit-logs.archive') }}" method="POST" class="d-inline" onsubmit="return confirm('Archive logs older than {{ $schedule->audit_archive_days ?? 90 }} days?');">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-down me-1"></i>Archive Now
                </button>
            </form>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-0">Total Logs</h6>
                            <h3 class="mb-0">{{ number_format($stats['total']) }}</h3>
                        </div>
                        <i class="bi bi-journal-text fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-0">Today</h6>
                            <h3 class="mb-0">{{ number_format($stats['today']) }}</h3>
                        </div>
                        <i class="bi bi-calendar-day fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-0">This Week</h6>
                            <h3 class="mb-0">{{ number_format($stats['this_week']) }}</h3>
                        </div>
                        <i class="bi bi-calendar-week fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="mb-0">Archived</h6>
                            <h3 class="mb-0">{{ number_format($stats['archived']) }}</h3>
                        </div>
                        <i class="bi bi-archive fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Settings Card -->
    <div class="card shadow mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Archive Settings</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.audit-logs.settings') }}" method="POST" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-2">
                    <label class="form-label">Auto Archive</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="audit_archive_enabled" value="1" id="archiveEnabled" {{ ($schedule->audit_archive_enabled ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="archiveEnabled">Enable</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="audit_archive_days" class="form-label">Archive logs older than (days)</label>
                    <input type="number" class="form-control" name="audit_archive_days" id="audit_archive_days" min="7" max="365" value="{{ $schedule->audit_archive_days ?? 90 }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save
                    </button>
                </div>
                <div class="col-md-5">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Archived logs are moved to a separate table to keep the main audit_logs table fast.
                        Scheduled archiving runs daily with backups.
                    </small>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" class="form-control form-control-sm" name="search" placeholder="Search..." value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="model_type" class="form-select form-select-sm">
                        <option value="">All Models</option>
                        @foreach($modelTypes as $type)
                        <option value="{{ $type }}" {{ request('model_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <option value="created" {{ request('action') == 'created' ? 'selected' : '' }}>Created</option>
                        <option value="updated" {{ request('action') == 'updated' ? 'selected' : '' }}>Updated</option>
                        <option value="deleted" {{ request('action') == 'deleted' ? 'selected' : '' }}>Deleted</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Model</th>
                            <th>ID</th>
                            <th>Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td>
                                <small>{{ $log->created_at->format('d M Y') }}</small><br>
                                <small class="text-muted">{{ $log->created_at->format('H:i:s') }}</small>
                            </td>
                            <td>
                                @if($log->user)
                                    <span class="badge bg-secondary">{{ $log->user->name }}</span>
                                @else
                                    <span class="text-muted">System</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $log->action_color }}">{{ ucfirst($log->action) }}</span>
                            </td>
                            <td>{{ $log->model_name }}</td>
                            <td><code>{{ $log->auditable_id }}</code></td>
                            <td>
                                @if($log->action === 'updated' && $log->old_values)
                                <details>
                                    <summary class="text-primary cursor-pointer small">{{ count($log->new_values ?? []) }} field(s)</summary>
                                    <div class="small mt-1">
                                        @foreach($log->new_values ?? [] as $field => $newVal)
                                        <div class="mb-1">
                                            <strong>{{ $field }}:</strong>
                                            <span class="text-danger">{{ Str::limit(json_encode($log->old_values[$field] ?? null), 30) }}</span>
                                            → <span class="text-success">{{ Str::limit(json_encode($newVal), 30) }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </details>
                                @elseif($log->action === 'created')
                                    <small class="text-muted">New record</small>
                                @elseif($log->action === 'deleted')
                                    <small class="text-muted">Record deleted</small>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                                No audit logs found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
