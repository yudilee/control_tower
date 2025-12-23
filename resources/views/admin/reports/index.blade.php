@extends('layouts.app')

@section('title', 'Scheduled Reports')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-envelope-at me-2"></i>Scheduled Reports</h1>
        <p class="text-muted mb-0">Configure automated email reports</p>
    </div>
    <a href="{{ route('admin.scheduled-reports.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Report
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th style="width: 40px;"></th>
                    <th>Report Name</th>
                    <th>Type</th>
                    <th>Schedule</th>
                    <th>Recipients</th>
                    <th>Last Sent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                <tr class="{{ !$report->is_active ? 'table-secondary' : '' }}">
                    <td class="text-center">
                        <form action="{{ route('admin.scheduled-reports.toggle', $report) }}" method="POST" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-link p-0" title="{{ $report->is_active ? 'Click to disable' : 'Click to enable' }}">
                                <i class="bi bi-{{ $report->is_active ? 'check-circle-fill text-success' : 'circle text-secondary' }}" style="font-size: 1.2rem;"></i>
                            </button>
                        </form>
                    </td>
                    <td>
                        <strong>{{ $report->name }}</strong>
                    </td>
                    <td>
                        <span class="badge bg-primary">{{ \App\Models\ScheduledReport::getTypes()[$report->type] ?? $report->type }}</span>
                    </td>
                    <td>
                        <i class="bi bi-clock me-1"></i>
                        {{ ucfirst($report->schedule) }} at {{ $report->time }}
                        @if($report->schedule === 'weekly' && $report->day_of_week)
                            <span class="text-muted">({{ ucfirst($report->day_of_week) }})</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-secondary">{{ count($report->recipients ?? []) }} recipient(s)</span>
                        <small class="text-muted d-block text-truncate" style="max-width: 200px;">
                            {{ implode(', ', $report->recipients ?? []) }}
                        </small>
                    </td>
                    <td>
                        @if($report->last_sent_at)
                            <small class="text-muted">{{ $report->last_sent_at->diffForHumans() }}</small>
                        @else
                            <small class="text-muted">Never</small>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <form action="{{ route('admin.scheduled-reports.send', $report) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-success" title="Send Now">
                                    <i class="bi bi-send"></i>
                                </button>
                            </form>
                            <a href="{{ route('admin.scheduled-reports.edit', $report) }}" class="btn btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('admin.scheduled-reports.destroy', $report) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this report?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-envelope-x d-block mb-2" style="font-size: 2rem;"></i>
                        No scheduled reports configured.
                        <br>
                        <a href="{{ route('admin.scheduled-reports.create') }}" class="btn btn-primary btn-sm mt-2">
                            <i class="bi bi-plus-circle me-1"></i>Create First Report
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-info-circle me-1"></i>About Scheduled Reports
    </div>
    <div class="card-body">
        <p class="mb-2">Reports are sent automatically based on their schedule. Available report types:</p>
        <ul class="mb-0">
            <li><strong>Daily Uninvoiced Summary</strong> - Lists all uninvoiced jobs with totals</li>
            <li><strong>SA Performance Report</strong> - Metrics by Service Advisor (jobs count, sales)</li>
            <li><strong>Aging Job Alerts</strong> - Jobs older than configured days</li>
            <li><strong>Parts Pending Report</strong> - Jobs waiting for parts</li>
        </ul>
    </div>
</div>
@endsection
