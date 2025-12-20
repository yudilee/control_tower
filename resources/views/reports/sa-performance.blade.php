@extends('layouts.app')

@section('title', 'SA Performance')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-person-badge me-2"></i>Service Advisor Performance</h1>
        <p class="text-muted">Track performance metrics by Service Advisor</p>
    </div>
</div>

<!-- Date Range Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">From Date</label>
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">To Date</label>
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
            </div>
            <div class="col-md-3">
                <select name="franchise" class="form-select">
                    <option value="">All Franchises</option>
                    <option value="PC" {{ request('franchise') == 'PC' ? 'selected' : '' }}>PC</option>
                    <option value="CV" {{ request('franchise') == 'CV' ? 'selected' : '' }}>CV</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Apply</button>
            </div>
        </form>
    </div>
</div>

<!-- Overall Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center">
                <h2 class="mb-1">{{ number_format($overallStats['total_jobs']) }}</h2>
                <p class="mb-0 opacity-75">Total Jobs</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center">
                <h2 class="mb-1">{{ number_format($overallStats['total_invoiced']) }}</h2>
                <p class="mb-0 opacity-75">Invoiced</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center">
                <h2 class="mb-1">Rp {{ number_format($overallStats['total_sales'], 0, ',', '.') }}</h2>
                <p class="mb-0 opacity-75">Total Revenue</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body text-center">
                <h2 class="mb-1">{{ $overallStats['avg_turnaround'] }} days</h2>
                <p class="mb-0 opacity-75">Avg Turnaround</p>
            </div>
        </div>
    </div>
</div>

<!-- Chart -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Top 10 SAs by Revenue</div>
            <div class="card-body">
                <canvas id="salesChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Top 10 SAs by Job Count</div>
            <div class="card-body">
                <canvas id="jobsChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- SA Table -->
<div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Service Advisor Metrics</span>
        <span class="badge bg-primary">{{ $saStats->count() }} Service Advisors</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Service Advisor</th>
                        <th class="text-center">Total Jobs</th>
                        <th class="text-center">Invoiced</th>
                        <th class="text-center">Pending</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Pending Sales</th>
                        <th class="text-center">Completion %</th>
                        <th class="text-center">Avg Turnaround</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($saStats as $index => $sa)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="fw-semibold">{{ $sa['name'] }}</td>
                        <td class="text-center">{{ number_format($sa['total_jobs']) }}</td>
                        <td class="text-center text-success">{{ number_format($sa['invoiced_count']) }}</td>
                        <td class="text-center text-warning">{{ number_format($sa['uninvoiced_count']) }}</td>
                        <td class="text-end">Rp {{ number_format($sa['total_sales'], 0, ',', '.') }}</td>
                        <td class="text-end text-muted">Rp {{ number_format($sa['uninvoiced_sales'], 0, ',', '.') }}</td>
                        <td class="text-center">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-{{ $sa['completion_rate'] >= 80 ? 'success' : ($sa['completion_rate'] >= 50 ? 'warning' : 'danger') }}" 
                                     style="width: {{ $sa['completion_rate'] }}%">
                                    {{ $sa['completion_rate'] }}%
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ $sa['avg_turnaround'] <= 7 ? 'success' : ($sa['avg_turnaround'] <= 14 ? 'warning' : 'danger') }}">
                                {{ $sa['avg_turnaround'] }} days
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No data for selected period</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartData = @json($chartData);
    
    // Sales Chart
    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Revenue',
                data: chartData.sales,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + (value / 1000000).toFixed(0) + 'M';
                        }
                    }
                }
            }
        }
    });
    
    // Jobs Chart
    new Chart(document.getElementById('jobsChart'), {
        type: 'doughnut',
        data: {
            labels: chartData.labels,
            datasets: [{
                data: chartData.jobs,
                backgroundColor: [
                    '#0d6efd', '#198754', '#ffc107', '#dc3545', '#6610f2',
                    '#0dcaf0', '#fd7e14', '#20c997', '#6c757d', '#d63384'
                ]
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
