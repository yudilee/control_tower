@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.audit-logs.index') }}">Audit Logs</a></li>
                    <li class="breadcrumb-item active">Archives</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0"><i class="bi bi-archive me-2"></i>Archived Audit Logs</h1>
            <p class="text-muted small">View archived logs that have been moved from the main table.</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('admin.audit-logs.export') }}" class="btn btn-outline-primary me-2">
                <i class="bi bi-download me-1"></i>Export JSON
            </a>
            <form action="{{ route('admin.audit-logs.clear-archives') }}" method="POST" class="d-inline" onsubmit="return confirm('Delete archived logs older than 365 days?');">
                @csrf
                <input type="hidden" name="older_than" value="365">
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-trash me-1"></i>Clear Old (>1yr)
                </button>
            </form>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header">
            <span class="badge bg-secondary me-2">{{ number_format($archives->total()) }} archived logs</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Original Date</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Model</th>
                            <th>ID</th>
                            <th>Archived At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($archives as $log)
                        <tr>
                            <td>
                                <small>{{ $log->original_created_at?->format('d M Y') }}</small><br>
                                <small class="text-muted">{{ $log->original_created_at?->format('H:i:s') }}</small>
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
                                <small class="text-muted">{{ $log->created_at->format('d M Y H:i') }}</small>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-archive fs-1 d-block mb-2"></i>
                                No archived logs yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($archives->hasPages())
        <div class="card-footer">
            {{ $archives->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
