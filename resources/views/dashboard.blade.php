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
    $uninvoicedCount = \App\Models\Job::uninvoiced()->count();
    $invoicedCount = \App\Models\Job::invoiced()->count();
    $needsPartsCount = \App\Models\Job::uninvoiced()->needsParts()->count();
    $vehiclesInWorkshop = \App\Models\Vehicle::where('is_in_workshop', true)->count();
    
    // Count duplicate customer name groups for alert
    // Uses same logic as CustomerController@duplicates for consistency
    $duplicateCustomerCount = 0;
    try {
        $allNames = \Illuminate\Support\Facades\DB::table(
            \Illuminate\Support\Facades\DB::raw("(
                SELECT DISTINCT customer_name as name FROM vehicles WHERE customer_name IS NOT NULL AND customer_name != ''
                UNION
                SELECT DISTINCT customer_name as name FROM jobs WHERE customer_name IS NOT NULL AND customer_name != ''
            ) as customers")
        )->pluck('name')->toArray();
        
        // Use stricter algorithm (requires BOTH methods >90% AND >85%)
        $processed = [];
        foreach ($allNames as $name1) {
            if (in_array($name1, $processed)) continue;
            $similar = [$name1];
            $normalized1 = strtoupper(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9\s]/i', ' ', trim($name1))));
            
            foreach ($allNames as $name2) {
                if ($name1 === $name2 || in_array($name2, $processed)) continue;
                $normalized2 = strtoupper(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9\s]/i', ' ', trim($name2))));
                
                // Levenshtein distance check
                $levenshtein = levenshtein($normalized1, $normalized2);
                $maxLen = max(strlen($normalized1), strlen($normalized2));
                $similarity = $maxLen > 0 ? (1 - $levenshtein / $maxLen) * 100 : 0;
                
                // Similar text check
                similar_text($normalized1, $normalized2, $percentSimilar);
                
                // Require BOTH methods show high similarity for more accuracy
                if (($similarity > 90 && $percentSimilar > 85) || ($similarity > 85 && $percentSimilar > 90)) {
                    $similar[] = $name2;
                    $processed[] = $name2;
                }
            }
            $processed[] = $name1;
            
            // Only count groups with 2+ names, excluding dismissed groups
            if (count($similar) >= 2) {
                if (!\App\Models\DismissedDuplicateGroup::isDismissed($similar)) {
                    $duplicateCustomerCount++;
                }
            }
        }
    } catch (\Exception $e) {
        $duplicateCustomerCount = 0;
    }
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
        <div class="stat-card-modern">
            <p class="stat-value">{{ $uninvoicedCount }}</p>
            <p class="stat-label mb-0"><i class="bi bi-clock me-1"></i>Uninvoiced Jobs</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-modern warning">
            <p class="stat-value">{{ $needsPartsCount }}</p>
            <p class="stat-label mb-0"><i class="bi bi-gear me-1"></i>Needs Parts</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-modern success">
            <p class="stat-value">{{ $invoicedCount }}</p>
            <p class="stat-label mb-0"><i class="bi bi-check-circle me-1"></i>Invoiced Jobs</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card-modern info">
            <p class="stat-value">{{ $vehiclesInWorkshop }}</p>
            <p class="stat-label mb-0"><i class="bi bi-car-front me-1"></i>In Workshop</p>
        </div>
    </div>
</div>

@php
    // Work Status breakdown for uninvoiced jobs - using dynamic options from database
    $workStatusCounts = \App\Models\Job::uninvoiced()
        ->selectRaw('COALESCE(work_status, "pending") as work_status, COUNT(*) as count')
        ->groupBy('work_status')
        ->get()
        ->keyBy('work_status');
    
    // Get configured work statuses from database
    $workStatusOptions = \App\Models\DropdownOption::getOptions('work_status');
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
            @endphp
            <div class="col-md col-6">
                <a href="{{ route('jobs.index', ['status' => 'uninvoiced', 'work_status' => $option->value]) }}" class="text-decoration-none">
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
    // Get last 7 days job data
    $last7Days = collect();
    for ($i = 6; $i >= 0; $i--) {
        $date = now()->subDays($i);
        $last7Days->push([
            'date' => $date->format('d M'),
            'invoiced' => \App\Models\Job::whereDate('invoiced_at', $date)->count(),
            'new' => \App\Models\Job::whereDate('job_date', $date)->count(),
        ]);
    }
    
    // Work status for pie chart
    $statusCounts = $workStatusOptions->map(fn($opt) => [
        'label' => $opt->label,
        'count' => $workStatusCounts->get($opt->value)?->count ?? 0,
        'color' => match($opt->color) {
            'primary' => '#0d6efd',
            'success' => '#198754',
            'warning' => '#ffc107',
            'danger' => '#dc3545',
            'info' => '#0dcaf0',
            'secondary' => '#6c757d',
            default => '#6c757d'
        }
    ])->filter(fn($s) => $s['count'] > 0);
    
    // SA Revenue (Top 5 for uninvoiced jobs)
    $saRevenue = \App\Models\Job::uninvoiced()
        ->selectRaw('service_advisor, SUM(COALESCE(total_sales, 0)) as revenue, COUNT(*) as job_count')
        ->whereNotNull('service_advisor')
        ->groupBy('service_advisor')
        ->orderByDesc('revenue')
        ->take(5)
        ->get();
    
    // Job Aging breakdown (uninvoiced only)
    $today = now()->startOfDay();
    $agingData = [
        ['label' => '< 3 days', 'count' => \App\Models\Job::uninvoiced()->where('job_date', '>', $today->copy()->subDays(3))->count(), 'color' => '#198754'],
        ['label' => '3-7 days', 'count' => \App\Models\Job::uninvoiced()->whereBetween('job_date', [$today->copy()->subDays(7), $today->copy()->subDays(3)])->count(), 'color' => '#0dcaf0'],
        ['label' => '7-14 days', 'count' => \App\Models\Job::uninvoiced()->whereBetween('job_date', [$today->copy()->subDays(14), $today->copy()->subDays(7)])->count(), 'color' => '#ffc107'],
        ['label' => '14-30 days', 'count' => \App\Models\Job::uninvoiced()->whereBetween('job_date', [$today->copy()->subDays(30), $today->copy()->subDays(14)])->count(), 'color' => '#fd7e14'],
        ['label' => '> 30 days', 'count' => \App\Models\Job::uninvoiced()->where('job_date', '<', $today->copy()->subDays(30))->count(), 'color' => '#dc3545'],
    ];
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
                        @forelse(\App\Models\Job::uninvoiced()->latest()->take(5)->get() as $job)
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
                @forelse(\App\Models\Job::uninvoiced()->needsParts()->latest()->take(5)->get() as $job)
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
});
</script>
@endpush

