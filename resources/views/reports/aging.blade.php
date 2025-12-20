@extends('layouts.app')

@section('title', 'Aging Report')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-clock-history me-2"></i>Job Aging Report</h1>
        <p class="text-muted">Uninvoiced jobs grouped by age - identify stale work orders</p>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center">
                <h2 class="mb-1">{{ number_format($totalJobs) }}</h2>
                <p class="mb-0 opacity-75">Total Uninvoiced Jobs</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center">
                <h2 class="mb-1">Rp {{ number_format($totalSales, 0, ',', '.') }}</h2>
                <p class="mb-0 opacity-75">Total Pending Sales</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body text-center">
                <h2 class="mb-1">{{ number_format($avgAge, 1) }} days</h2>
                <p class="mb-0 opacity-75">Average Job Age</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center">
                <h2 class="mb-1">{{ $agingGroups['30+']['jobs']->count() }}</h2>
                <p class="mb-0 opacity-75">Critical (30+ Days)</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select name="franchise" class="form-select">
                    <option value="">All Franchises</option>
                    @foreach($filterOptions['franchise'] as $opt)
                        <option value="{{ $opt }}" {{ request('franchise') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="service_advisor" class="form-select">
                    <option value="">All Service Advisors</option>
                    @foreach($filterOptions['service_advisor'] as $opt)
                        <option value="{{ $opt }}" {{ request('service_advisor') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="foreman" class="form-select">
                    <option value="">All Foremen</option>
                    @foreach($filterOptions['foreman'] as $opt)
                        <option value="{{ $opt }}" {{ request('foreman') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-funnel"></i> Filter</button>
                <a href="{{ route('reports.aging') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Aging Groups -->
<div class="row g-4">
    @foreach($agingGroups as $key => $group)
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 border-{{ $group['color'] }}">
            <div class="card-header bg-{{ $group['color'] }} {{ in_array($group['color'], ['warning', 'orange']) ? 'text-dark' : 'text-white' }} d-flex justify-content-between align-items-center">
                <span><i class="bi bi-{{ $group['icon'] }} me-2"></i>{{ $group['label'] }}</span>
                <span class="badge bg-white text-{{ $group['color'] }}">{{ $group['jobs']->count() }}</span>
            </div>
            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                @if($group['jobs']->isEmpty())
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle fs-1 d-block mb-2"></i>
                        No jobs in this range
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($group['jobs']->sortByDesc(fn($j) => $j->job_date ? now()->diffInDays($j->job_date) : 999)->take(20) as $job)
                        @php
                            $daysOld = $job->job_date ? now()->diffInDays($job->job_date) : 0;
                        @endphp
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <a href="{{ route('jobs.show', $job) }}" class="fw-bold text-decoration-none">{{ $job->job_number }}</a>
                                    <div class="small text-muted">{{ $job->plate_number }} • {{ $job->service_advisor ?? 'No SA' }}</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-{{ $group['color'] }} {{ in_array($group['color'], ['warning', 'orange']) ? 'text-dark' : '' }}">{{ $daysOld }}d</span>
                                    @if($job->total_sales)
                                    <div class="small text-muted">Rp {{ number_format($job->total_sales, 0, ',', '.') }}</div>
                                    @endif
                                </div>
                            </div>
                        </li>
                        @endforeach
                        @if($group['jobs']->count() > 20)
                        <li class="list-group-item text-center text-muted small">
                            + {{ $group['jobs']->count() - 20 }} more jobs
                        </li>
                        @endif
                    </ul>
                @endif
            </div>
            <div class="card-footer bg-light text-muted small">
                Total: Rp {{ number_format($group['jobs']->sum('total_sales'), 0, ',', '.') }}
            </div>
        </div>
    </div>
    @endforeach
</div>

<style>
    .bg-orange { background-color: #fd7e14 !important; }
    .text-orange { color: #fd7e14 !important; }
    .border-orange { border-color: #fd7e14 !important; }
</style>
@endsection
