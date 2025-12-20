@extends('customer.layout')

@section('title', 'Dashboard')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h4><i class="bi bi-speedometer2 me-2"></i>Welcome, {{ $customer->name }}!</h4>
        <p class="text-muted">Track your vehicle service status and history</p>
    </div>
</div>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card text-center p-3">
            <i class="bi bi-car-front display-4"></i>
            <h3 class="mb-0">{{ $vehicles->count() }}</h3>
            <small>Vehicles</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-center p-3">
            <i class="bi bi-tools display-4"></i>
            <h3 class="mb-0">{{ $stats['total_jobs'] }}</h3>
            <small>Total Jobs</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-center p-3">
            <i class="bi bi-hourglass-split display-4"></i>
            <h3 class="mb-0">{{ $stats['active_jobs'] }}</h3>
            <small>In Progress</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card text-center p-3">
            <i class="bi bi-currency-dollar display-4"></i>
            <h3 class="mb-0">Rp {{ number_format($stats['total_spent'] / 1000000, 1) }}M</h3>
            <small>Total Spent</small>
        </div>
    </div>
</div>

<!-- My Vehicles -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card portal-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-car-front me-2"></i>My Vehicles</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @forelse($vehicles as $vehicle)
                    <div class="col-md-4">
                        <div class="card job-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-car-front-fill fs-3 text-primary me-3"></i>
                                    <div>
                                        <h6 class="mb-0">{{ $vehicle->plate_number }}</h6>
                                        <small class="text-muted">{{ $vehicle->unit_type ?? 'Vehicle' }}</small>
                                    </div>
                                </div>
                                <small class="text-muted">{{ $vehicle->chassis_number ?? '-' }}</small>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="col-12 text-center text-muted py-4">
                        <i class="bi bi-car-front display-4 opacity-25"></i>
                        <p class="mb-0">No vehicles linked</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Jobs -->
<div class="row">
    <div class="col-12">
        <div class="card portal-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Jobs</h5>
                <a href="{{ route('customer.jobs') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Job #</th>
                                <th>Date</th>
                                <th>Vehicle</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentJobs as $job)
                            <tr>
                                <td><strong>{{ $job->job_number }}</strong></td>
                                <td>{{ $job->job_date?->format('d M Y') ?? '-' }}</td>
                                <td>{{ $job->plate_number }}</td>
                                <td>
                                    @if($job->status === 'invoiced')
                                    <span class="badge bg-success">Completed</span>
                                    @else
                                    <span class="badge bg-warning text-dark">In Progress</span>
                                    @endif
                                </td>
                                <td>Rp {{ number_format($job->inv_ppn_meterai ?? $job->total_sales ?? 0, 0, ',', '.') }}</td>
                                <td>
                                    <a href="{{ route('customer.jobs.show', $job) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No jobs found</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
