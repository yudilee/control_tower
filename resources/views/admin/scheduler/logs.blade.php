@extends('layouts.app')

@section('title', 'Scheduler Logs')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('admin.scheduler.index') }}">Scheduler</a></li>
                <li class="breadcrumb-item active">Logs</li>
            </ol>
        </nav>
        <h1><i class="bi bi-journal-text me-2"></i>Scheduler Logs</h1>
    </div>
    <form action="{{ route('admin.scheduler.clear-logs') }}" method="POST" onsubmit="return confirm('Clear logs older than 30 days?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger">
            <i class="bi bi-trash me-1"></i>Clear Old Logs
        </button>
    </form>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="form-label mb-0 me-2">Filter by task:</label>
            </div>
            <div class="col-auto">
                <select name="command" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Tasks</option>
                    @foreach($commands as $cmd => $name)
                    <option value="{{ $cmd }}" {{ $selectedCommand === $cmd ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Date/Time</th>
                    <th>Task</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Triggered By</th>
                    <th>Output / Error</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>
                        {{ $log->created_at->format('d M Y') }}
                        <br><small class="text-muted">{{ $log->created_at->format('H:i:s') }}</small>
                    </td>
                    <td><code>{{ $log->command }}</code></td>
                    <td>
                        @if($log->status === 'success')
                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Success</span>
                        @elseif($log->status === 'running')
                        <span class="badge bg-warning"><i class="bi bi-hourglass-split me-1"></i>Running</span>
                        @else
                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Failed</span>
                        @endif
                    </td>
                    <td>{{ $log->duration_formatted }}</td>
                    <td>
                        <span class="badge bg-{{ $log->triggered_by === 'manual' ? 'info' : 'secondary' }}">
                            {{ ucfirst($log->triggered_by) }}
                        </span>
                    </td>
                    <td style="max-width: 400px;">
                        @if($log->status === 'success' && $log->output)
                        <details>
                            <summary class="text-success cursor-pointer">View Output</summary>
                            <pre class="mt-2 mb-0 bg-light p-2 rounded small" style="max-height: 200px; overflow: auto;">{{ $log->output }}</pre>
                        </details>
                        @elseif($log->status === 'failed' && $log->error)
                        <details open>
                            <summary class="text-danger cursor-pointer">View Error</summary>
                            <pre class="mt-2 mb-0 bg-danger bg-opacity-10 text-danger p-2 rounded small" style="max-height: 200px; overflow: auto;">{{ $log->error }}</pre>
                        </details>
                        @else
                        <span class="text-muted">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="bi bi-journal-x d-block mb-2" style="font-size: 2rem;"></i>
                        No logs found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="card-footer">
        {{ $logs->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
