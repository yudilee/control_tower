@extends('layouts.app')

@section('title', 'Vehicle - ' . $vehicle->plate_number)

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('vehicles.index') }}">Vehicles</a></li>
                <li class="breadcrumb-item active">{{ $vehicle->plate_number }}</li>
            </ol>
        </nav>
        <h1><i class="bi bi-car-front me-2"></i>{{ $vehicle->plate_number }}</h1>
    </div>
    <div>
        @if(auth()->user()?->hasRole('control_tower') || auth()->user()?->hasRole('admin'))
        <form action="{{ route('vehicles.toggle-workshop', $vehicle) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-{{ $vehicle->is_in_workshop ? 'warning' : 'success' }} me-2">
                <i class="bi bi-{{ $vehicle->is_in_workshop ? 'box-arrow-right' : 'box-arrow-in-right' }} me-1"></i>
                {{ $vehicle->is_in_workshop ? 'Mark Not in Workshop' : 'Mark In Workshop' }}
            </button>
        </form>
        @else
        <button type="button" class="btn btn-secondary me-2" disabled title="Only Control Tower and Admin can change workshop status">
            <i class="bi bi-{{ $vehicle->is_in_workshop ? 'box-arrow-right' : 'box-arrow-in-right' }} me-1"></i>
            {{ $vehicle->is_in_workshop ? 'Mark Not in Workshop' : 'Mark In Workshop' }}
        </button>
        @endif
        @if(auth()->user()?->canEdit())
        <a href="{{ route('vehicles.edit', $vehicle) }}" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        @endif
    </div>
</div>

<!-- Stats Cards -->
@php
    $vehicleJobs = $vehicle->jobs;
    $uninvoicedJobs = $vehicleJobs->where('status', 'uninvoiced');
    $invoicedJobs = $vehicleJobs->where('status', 'invoiced');
    $estimatedSales = $uninvoicedJobs->sum('total_sales') ?? 0;
    $invoicedSales = $invoicedJobs->sum('inv_ppn_meterai') ?? 0;
@endphp
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body py-2">
                <h4 class="mb-0 text-primary">{{ $vehicleJobs->count() }}</h4>
                <small class="text-muted">Total Jobs</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body py-2">
                <h4 class="mb-0 text-warning">{{ $uninvoicedJobs->count() }}</h4>
                <small class="text-muted">Uninvoiced</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning bg-warning bg-opacity-10">
            <div class="card-body py-2">
                <h5 class="mb-0 text-warning">Rp {{ number_format($estimatedSales, 0, ',', '.') }}</h5>
                <small class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Projected</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-success bg-success bg-opacity-10">
            <div class="card-body py-2">
                <h5 class="mb-0 text-success">Rp {{ number_format($invoicedSales, 0, ',', '.') }}</h5>
                <small class="text-muted"><i class="bi bi-check-circle me-1"></i>Invoiced</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Vehicle Info</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <th class="text-muted">Plate Number</th>
                        <td class="fw-bold">{{ $vehicle->plate_number }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Model</th>
                        <td>{{ $vehicle->model ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Year</th>
                        <td>{{ $vehicle->year ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">VIN</th>
                        <td>{{ $vehicle->vin ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">Customer</th>
                        <td>
                            @if($vehicle->customer_name)
                                <a href="{{ route('customers.show', ['name' => $vehicle->customer_name]) }}" class="text-decoration-none">
                                    <i class="bi bi-person me-1"></i>{{ $vehicle->customer_name }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Phone</th>
                        <td>{{ $vehicle->customer_phone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th class="text-muted">In Workshop</th>
                        <td>
                            @if($vehicle->is_in_workshop)
                                <span class="badge bg-success">Yes</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="text-muted">Import Source</th>
                        <td>
                            @if($vehicle->import)
                                <small class="text-muted">
                                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>{{ $vehicle->import->file_name }}
                                    <br><span class="text-secondary">{{ $vehicle->import->import_type }} | {{ $vehicle->import->created_at->format('d/m/Y H:i') }}</span>
                                </small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clipboard-check me-2"></i>Job History</span>
                <span class="badge bg-primary">{{ $vehicle->jobs->count() }} job(s)</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>WIP</th>
                            <th>Date</th>
                            <th>SA</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vehicle->jobs->sortByDesc('job_date') as $job)
                        <tr>
                            <td><a href="{{ route('jobs.show', $job) }}" class="fw-bold">{{ $job->job_number }}</a></td>
                            <td>{{ $job->job_date?->format('d/m/Y') ?? '-' }}</td>
                            <td>{{ $job->service_advisor ?? '-' }}</td>
                            <td class="text-end">{{ $job->total_sales ? number_format($job->total_sales, 0, ',', '.') : '-' }}</td>
                            <td>
                                @if($job->status == 'invoiced')
                                    <span class="badge bg-success">Invoiced</span>
                                @else
                                    <span class="badge bg-warning text-dark">Uninvoiced</span>
                                @endif
                            </td>
                            <td class="text-truncate" style="max-width: 150px;">{{ $job->latest_remark ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">No jobs for this vehicle</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
