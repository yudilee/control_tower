@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<!-- Hero Welcome Section -->
<div class="hero-welcome">
    <div class="row align-items-center position-relative" style="z-index: 1;">
        <div class="col-md-8">
            <h1>Welcome back, {{ Auth::user()->name }}!</h1>
            <p class="mb-0 opacity-75 lead">Here's what's happening in the workshop today.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <div class="badge bg-white text-dark px-3 py-2 rounded-pill shadow-sm">
                <i class="bi bi-calendar3 me-2"></i>{{ now()->format('l, d F Y') }}
            </div>
        </div>
    </div>
</div>

@php
    // All data is now passed from DashboardController with caching
    $uninvoicedCount = $stats['uninvoiced'];
    $invoicedCount = $stats['invoiced'];
    $needsPartsCount = $stats['needs_parts'];
    $vehiclesInWorkshop = $stats['vehicles_in_workshop'];
@endphp

@if($duplicateCustomerCount > 0)
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
    <div class="flex-grow-1">
        <strong>Duplicate Customer Names Detected!</strong>
        Found approximately <strong>{{ $duplicateCustomerCount }}</strong> potential duplicate customer names that may need merging.
        This could indicate data issues in your DMS system.
    </div>
    <a href="{{ route('customers.duplicates') }}" class="btn btn-warning btn-sm ms-3">
        <i class="bi bi-arrow-right-circle me-1"></i>Review & Merge
    </a>
</div>
@endif

<!-- Stat Cards -->
<div class="row g-4 mb-5">
    <div class="col-md-3">
        <a href="{{ route('jobs.index', ['status' => 'uninvoiced']) }}" class="text-decoration-none">
            <div class="stat-card-modern">
                <p class="stat-value" id="stat-uninvoiced">{{ $uninvoicedCount }}</p>
                <p class="stat-label mb-0"><i class="bi bi-clock me-1"></i>Uninvoiced Jobs</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('jobs.index', ['need_part' => 1, 'status' => 'uninvoiced']) }}" class="text-decoration-none">
            <div class="stat-card-modern warning">
                <p class="stat-value" id="stat-needs-parts">{{ $needsPartsCount }}</p>
                <p class="stat-label mb-0"><i class="bi bi-gear me-1"></i>Needs Parts</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('jobs.index', ['status' => 'invoiced']) }}" class="text-decoration-none">
            <div class="stat-card-modern success">
                <p class="stat-value" id="stat-invoiced">{{ $invoicedCount }}</p>
                <p class="stat-label mb-0"><i class="bi bi-check-circle me-1"></i>Invoiced Jobs</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('vehicles.index', ['in_workshop' => 1]) }}" class="text-decoration-none">
            <div class="stat-card-modern info">
                <p class="stat-value" id="stat-in-workshop">{{ $vehiclesInWorkshop }}</p>
                <p class="stat-label mb-0"><i class="bi bi-car-front me-1"></i>In Workshop</p>
            </div>
        </a>
    </div>
</div>

@php
    // Work Status breakdown for uninvoiced jobs - using dynamic options from database
    // Count NULL work_status separately to add to first option
    $workStatusCounts = \App\Models\Job::uninvoiced()
        ->selectRaw('work_status, COUNT(*) as count')
        ->groupBy('work_status')
        ->get()
        ->keyBy('work_status');
    
    // Count of jobs with NULL work_status
    $nullCount = $workStatusCounts->get(null)?->count ?? $workStatusCounts->get('')?->count ?? 0;
    
    // Get configured work statuses from database
    $workStatusOptions = \App\Models\DropdownOption::getOptions('work_status');
    $firstStatusValue = $workStatusOptions->first()?->value;
@endphp

<!-- Work Status Breakdown -->
<div class="card mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bar-chart me-2"></i>Work Status (Uninvoiced Jobs)</span>
        <a href="{{ route('jobs.index', ['status' => 'uninvoiced']) }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @forelse($workStatusOptions as $option)
            @php
                $count = $workStatusCounts->get($option->value)?->count ?? 0;
                // Add NULL count to first option (like Kanban does)
                if ($option->value === $firstStatusValue) {
                    $count += $nullCount;
                }
            @endphp
            <div class="col-md col-6">
                <a href="{{ route('jobs.index', ['status' => 'uninvoiced', 'filter_work_status' => $option->value]) }}" class="text-decoration-none">
                    <div class="card border-0 bg-{{ $option->color }} bg-opacity-10 h-100">
                        <div class="card-body py-3 text-center">
                            @if($option->icon)
                            <i class="bi bi-{{ $option->icon }} fs-3 text-{{ $option->color }} d-block mb-2"></i>
                            @endif
                            <h4 class="mb-0 text-{{ $option->color }}">{{ $count }}</h4>
                            <small class="text-muted">{{ $option->label }}</small>
                        </div>
                    </div>
                </a>
            </div>
            @empty
            <div class="col-12 text-center text-muted py-3">
                <i class="bi bi-gear display-4 opacity-25"></i>
                <p class="mb-0 mt-2">No work statuses configured</p>
                @if(auth()->user()->hasRole('admin'))
                <a href="{{ route('admin.dropdowns.index', ['type' => 'work_status']) }}" class="btn btn-primary btn-sm mt-2">
                    <i class="bi bi-plus-lg me-1"></i>Configure Work Statuses
                </a>
                @endif
            </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Charts Row -->
@php
    // All chart data is now passed from DashboardController with caching
    $last7Days = $chartData['last7Days'];
    $statusCounts = $chartData['statusCounts'];
    $saRevenue = $chartData['saRevenue'];
    $agingData = $chartData['agingData'];
@endphp

<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header bg-light"><i class="bi bi-graph-up me-2"></i>Job Trend (Last 7 Days)</div>
            <div class="card-body">
                <canvas id="jobTrendChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-light"><i class="bi bi-pie-chart me-2"></i>Work Status Distribution</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="statusPieChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Row 2: SA Revenue & Aging -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light"><i class="bi bi-currency-dollar me-2"></i>Top 5 SA Revenue (Uninvoiced)</div>
            <div class="card-body">
                <canvas id="saRevenueChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span><i class="bi bi-hourglass-split me-2"></i>Job Aging (Uninvoiced)</span>
                <a href="{{ route('reports.aging') }}" class="btn btn-sm btn-outline-primary">Full Report</a>
            </div>
            <div class="card-body">
                <canvas id="agingChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Area -->
<div class="row g-4 mb-5">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header-modern">
                <span class="card-header-title">
                    <i class="bi bi-exclamation-triangle text-warning"></i>Recent Open Jobs
                </span>
                <a href="{{ route('jobs.index', ['status' => 'uninvoiced']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-modern mb-0 table-hover">
                    <thead>
                        <tr>
                            <th>Job #</th>
                            <th>Plate No</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentJobs as $job)
                        <tr onclick="window.location='{{ route('jobs.show', $job) }}'" style="cursor: pointer;">
                            <td class="fw-bold text-primary">{{ $job->job_number }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $job->plate_number }}</span></td>
                            <td class="text-truncate" style="max-width: 150px;">{{ $job->customer_name }}</td>
                            <td>{{ $job->job_date?->format('d M') }}</td>
                            <td><span class="badge bg-warning text-dark">{{ $job->work_status ?? 'Pending' }}</span></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-check2-circle display-4 d-block mb-3 opacity-25"></i>
                                No uninvoiced jobs found
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header-modern">
                <span class="card-header-title">
                    <i class="bi bi-tools text-danger"></i>Needs Parts
                </span>
                <a href="{{ route('jobs.index', ['need_part' => 1]) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">View All</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($needsPartsJobs as $job)
                <a href="{{ route('jobs.show', $job) }}" class="list-group-item list-group-item-action py-3">
                    <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                        <h6 class="mb-0 fw-bold">{{ $job->plate_number }}</h6>
                        <small class="text-muted">{{ $job->job_number }}</small>
                    </div>
                    <p class="mb-1 small text-muted text-truncate">{{ $job->latest_remark }}</p>
                    <small class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Parts Required</small>
                </a>
                @empty
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check2-all display-4 d-block mb-3 opacity-25"></i>
                    All clear
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mb-4">
    <h5 class="mb-4 fw-bold text-muted text-uppercase small ls-1">Quick Actions</h5>
    <div class="row g-4">
        <div class="col-md-3">
            <a href="{{ route('jobs.create') }}" class="action-card">
                <div class="action-icon-wrapper">
                    <i class="bi bi-plus-lg"></i>
                </div>
                <div class="action-title">New Job</div>
                <div class="action-desc">Create a new job order</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('imports.upload') }}" class="action-card">
                <div class="action-icon-wrapper">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <div class="action-title">Import Data</div>
                <div class="action-desc">Upload Excel/ODS files</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('reports.export-uninvoiced') }}" class="action-card">
                <div class="action-icon-wrapper">
                    <i class="bi bi-file-earmark-arrow-down"></i>
                </div>
                <div class="action-title">Export Report</div>
                <div class="action-desc">Download uninvoiced jobs</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('reports.needs-parts') }}" class="action-card">
                <div class="action-icon-wrapper">
                    <i class="bi bi-gear-wide-connected"></i>
                </div>
                <div class="action-title">Parts Report</div>
                <div class="action-desc">View parts requirements</div>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Job Trend Chart
    const trendData = @json($last7Days);
    new Chart(document.getElementById('jobTrendChart'), {
        type: 'line',
        data: {
            labels: trendData.map(d => d.date),
            datasets: [
                {
                    label: 'New Jobs',
                    data: trendData.map(d => d.new),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Invoiced',
                    data: trendData.map(d => d.invoiced),
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
    
    // Work Status Pie Chart
    const statusData = @json($statusCounts->values());
    if (statusData.length > 0) {
        new Chart(document.getElementById('statusPieChart'), {
            type: 'doughnut',
            data: {
                labels: statusData.map(s => s.label),
                datasets: [{
                    data: statusData.map(s => s.count),
                    backgroundColor: statusData.map(s => s.color)
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }
    
    // SA Revenue Bar Chart
    const saData = @json($saRevenue);
    if (saData.length > 0) {
        new Chart(document.getElementById('saRevenueChart'), {
            type: 'bar',
            data: {
                labels: saData.map(s => s.service_advisor),
                datasets: [{
                    label: 'Revenue (IDR)',
                    data: saData.map(s => parseFloat(s.revenue)),
                    backgroundColor: ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => 'IDR ' + ctx.raw.toLocaleString('id-ID')
                        }
                    }
                },
                scales: {
                    x: { 
                        beginAtZero: true,
                        ticks: {
                            callback: (v) => 'IDR ' + (v / 1000000).toFixed(1) + 'M'
                        }
                    }
                }
            }
        });
    }
    
    // Job Aging Doughnut Chart
    const agingData = @json($agingData);
    new Chart(document.getElementById('agingChart'), {
        type: 'doughnut',
        data: {
            labels: agingData.map(a => a.label),
            datasets: [{
                data: agingData.map(a => a.count),
                backgroundColor: agingData.map(a => a.color),
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });
    
    // Real-time dashboard updates via WebSocket
    if (window.Echo) {
        window.Echo.channel('dashboard')
            .listen('.stats-updated', (e) => {
                console.log('[Dashboard] Stats updated:', e.stats);
                
                // Update stat cards with animation
                const updates = [
                    { id: 'stat-uninvoiced', value: e.stats.uninvoiced },
                    { id: 'stat-needs-parts', value: e.stats.needs_parts },
                    { id: 'stat-invoiced', value: e.stats.invoiced },
                    { id: 'stat-in-workshop', value: e.stats.in_workshop },
                ];
                
                updates.forEach(({ id, value }) => {
                    const el = document.getElementById(id);
                    if (el && parseInt(el.textContent) !== value) {
                        el.style.transition = 'transform 0.3s, color 0.3s';
                        el.style.transform = 'scale(1.2)';
                        el.style.color = '#0d6efd';
                        el.textContent = value;
                        
                        setTimeout(() => {
                            el.style.transform = 'scale(1)';
                            el.style.color = '';
                        }, 300);
                    }
                });
            });
        console.log('[Dashboard] Subscribed to real-time updates');
    }
});
</script>
@endpush

