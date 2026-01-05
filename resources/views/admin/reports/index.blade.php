@extends('layouts.app')

@section('title', 'Scheduled Reports')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-calendar-check me-2"></i>Scheduled Reports</h1>
        <p class="text-muted mb-0">Configure automated email reports to keep your team informed</p>
    </div>
    <a href="{{ route('admin.scheduled-reports.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Create Report
    </a>
</div>

<style>
.stat-card {
    border-radius: 12px;
    padding: 1.25rem;
    text-align: center;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
}
.stat-card-total {
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.15), rgba(13, 110, 253, 0.25));
    border: 1px solid rgba(13, 110, 253, 0.4);
}
.stat-card-total .stat-value { color: #0d6efd; }
.stat-card-active {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.15), rgba(40, 167, 69, 0.25));
    border: 1px solid rgba(40, 167, 69, 0.4);
}
.stat-card-active .stat-value { color: #28a745; }
.stat-card-inactive {
    background: linear-gradient(135deg, rgba(108, 117, 125, 0.15), rgba(108, 117, 125, 0.25));
    border: 1px solid rgba(108, 117, 125, 0.4);
}
.stat-card-inactive .stat-value { color: #6c757d; }

.report-row {
    transition: background 0.2s;
}
.report-row:hover {
    background: var(--bs-tertiary-bg);
}
.report-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
}
.report-type-badge.type-uninvoiced { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
.report-type-badge.type-invoiced { background: rgba(40, 167, 69, 0.15); color: #28a745; }
.report-type-badge.type-performance { background: rgba(0, 123, 255, 0.15); color: #007bff; }
.report-type-badge.type-aging { background: rgba(255, 193, 7, 0.15); color: #e0a800; }
.report-type-badge.type-parts_pending { background: rgba(108, 117, 125, 0.15); color: #6c757d; }

.schedule-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.schedule-badge {
    background: var(--bs-tertiary-bg);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}
.status-toggle {
    width: 44px;
    height: 24px;
    cursor: pointer;
}
.filter-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
}
.filter-tag {
    background: var(--bs-info-bg-subtle);
    color: var(--bs-info-text-emphasis);
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    font-size: 0.7rem;
}
</style>

@php
    $activeCount = $reports->where('is_active', true)->count();
    $inactiveCount = $reports->where('is_active', false)->count();
    $typeIcons = [
        'uninvoiced' => 'bi-exclamation-triangle',
        'invoiced' => 'bi-check-circle',
        'performance' => 'bi-graph-up',
        'aging' => 'bi-clock-history',
        'parts_pending' => 'bi-gear',
    ];
@endphp

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-card-total">
            <div class="stat-value">{{ $reports->count() }}</div>
            <div class="text-muted"><i class="bi bi-calendar-check me-1"></i>Total Reports</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-card-active">
            <div class="stat-value">{{ $activeCount }}</div>
            <div class="text-muted"><i class="bi bi-check-circle me-1"></i>Active</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-card-inactive">
            <div class="stat-value">{{ $inactiveCount }}</div>
            <div class="text-muted"><i class="bi bi-pause-circle me-1"></i>Paused</div>
        </div>
    </div>
</div>

<!-- Reports Table -->
<div class="card">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2"></i><strong>Configured Reports</strong></span>
        <span class="badge bg-secondary">{{ $reports->count() }} reports</span>
    </div>
    <div class="card-body p-0">
        @if($reports->isEmpty())
        <div class="text-center py-5">
            <i class="bi bi-calendar-x d-block mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
            <h5 class="text-muted">No Scheduled Reports</h5>
            <p class="text-muted mb-3">Create your first automated report to keep your team informed.</p>
            <a href="{{ route('admin.scheduled-reports.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Create Report
            </a>
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>Report</th>
                        <th>Type</th>
                        <th>Schedule</th>
                        <th>Recipients</th>
                        <th>Filters</th>
                        <th>Last Sent</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reports as $report)
                    <tr class="report-row {{ !$report->is_active ? 'opacity-50' : '' }}">
                        <td class="text-center align-middle">
                            <form action="{{ route('admin.scheduled-reports.toggle', $report) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-link p-0" title="{{ $report->is_active ? 'Click to pause' : 'Click to activate' }}">
                                    @if($report->is_active)
                                        <i class="bi bi-toggle-on text-success" style="font-size: 1.5rem;"></i>
                                    @else
                                        <i class="bi bi-toggle-off text-secondary" style="font-size: 1.5rem;"></i>
                                    @endif
                                </button>
                            </form>
                        </td>
                        <td class="align-middle">
                            <div class="fw-semibold">{{ $report->name }}</div>
                        </td>
                        <td class="align-middle">
                            <span class="report-type-badge type-{{ $report->type }}">
                                <i class="bi {{ $typeIcons[$report->type] ?? 'bi-file-text' }}"></i>
                                {{ \App\Models\ScheduledReport::getTypes()[$report->type] ?? ucfirst($report->type) }}
                            </span>
                        </td>
                        <td class="align-middle">
                            <div class="schedule-info">
                                <i class="bi bi-clock text-muted"></i>
                                <span>{{ ucfirst($report->schedule) }}</span>
                                <span class="schedule-badge">{{ $report->time }}</span>
                                @if($report->schedule === 'weekly' && $report->day_of_week)
                                    <span class="schedule-badge">{{ ucfirst($report->day_of_week) }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="align-middle">
                            <span class="badge bg-secondary">{{ count($report->recipients ?? []) }}</span>
                            <small class="text-muted d-block text-truncate" style="max-width: 150px;">
                                {{ implode(', ', $report->recipients ?? []) }}
                            </small>
                        </td>
                        <td class="align-middle">
                            @php
                                $filters = collect($report->config ?? [])
                                    ->except(['aging_days', 'include_pdf', 'date_period'])
                                    ->filter(fn($v) => !empty($v));
                            @endphp
                            @if($filters->isNotEmpty())
                                <div class="filter-tags">
                                    @foreach($filters->take(3) as $key => $value)
                                        <span class="filter-tag">{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $value }}</span>
                                    @endforeach
                                    @if($filters->count() > 3)
                                        <span class="filter-tag">+{{ $filters->count() - 3 }} more</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted small">No filters</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            @if($report->last_sent_at)
                                <small class="text-muted" title="{{ $report->last_sent_at->format('d M Y H:i') }}">
                                    {{ $report->last_sent_at->diffForHumans() }}
                                </small>
                            @else
                                <small class="text-muted">Never</small>
                            @endif
                        </td>
                        <td class="align-middle">
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
                                <form action="{{ route('admin.scheduled-reports.destroy', $report) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this scheduled report?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

<!-- Info Card -->
<div class="card mt-4">
    <div class="card-header py-3">
        <i class="bi bi-lightbulb me-2"></i><strong>About Scheduled Reports</strong>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <h6><i class="bi bi-clock-history text-primary me-2"></i>How It Works</h6>
                <p class="text-muted small mb-0">
                    Reports are automatically sent via email according to their schedule. Each report pulls live data 
                    at send time and applies any configured filters. The scheduler runs every 5 minutes.
                </p>
            </div>
            <div class="col-md-6">
                <h6><i class="bi bi-funnel text-success me-2"></i>Using Filters</h6>
                <p class="text-muted small mb-0">
                    Filters let you customize what data appears in the report. For example, create a "PC Only" uninvoiced 
                    report by setting the Franchise filter to "PC".
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
