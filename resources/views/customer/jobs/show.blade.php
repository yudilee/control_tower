@extends('customer.layout')

@section('title', 'Job Details')

@section('content')
<div class="mb-4">
    <a href="{{ route('customer.jobs') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Jobs
    </a>
</div>

<div class="row g-4">
    <!-- Job Info -->
    <div class="col-lg-8">
        <div class="card portal-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-file-text me-2"></i>Job #{{ $job->job_number }}
                </h5>
                @if($job->status === 'invoiced')
                <span class="badge bg-success fs-6">Completed</span>
                @else
                <span class="badge bg-warning text-dark fs-6">In Progress</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="text-muted">Vehicle</td>
                                <td><strong>{{ $job->plate_number }}</strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Date In</td>
                                <td>{{ $job->job_date?->format('d M Y') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Service Advisor</td>
                                <td>{{ $job->service_advisor ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Work Status</td>
                                <td><x-work-status :value="$job->work_status" /></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="text-muted">Invoice #</td>
                                <td>{{ $job->invoice_number ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Invoice Date</td>
                                <td>{{ $job->invoice_date?->format('d M Y') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Total Amount</td>
                                <td><strong class="fs-5 text-primary">Rp {{ number_format($job->inv_ppn_meterai ?? $job->total_sales ?? 0, 0, ',', '.') }}</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                @if($job->job_description)
                <div class="mt-3">
                    <h6>Job Description</h6>
                    <p class="text-muted">{{ $job->job_description }}</p>
                </div>
                @endif
                
                @if($job->status === 'invoiced')
                <div class="mt-4">
                    <a href="{{ route('customer.jobs.invoice', $job) }}" class="btn btn-primary">
                        <i class="bi bi-download me-2"></i>Download Invoice
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Cost Breakdown -->
    <div class="col-lg-4">
        <div class="card portal-card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Cost Breakdown</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Labour</span>
                        <span>Rp {{ number_format($job->labour_sales ?? 0, 0, ',', '.') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Parts</span>
                        <span>Rp {{ number_format($job->part_sales ?? 0, 0, ',', '.') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Subtotal</span>
                        <span>Rp {{ number_format($job->total_sales ?? 0, 0, ',', '.') }}</span>
                    </li>
                    @if($job->inv_ppn)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>PPN</span>
                        <span>Rp {{ number_format($job->inv_ppn ?? 0, 0, ',', '.') }}</span>
                    </li>
                    @endif
                    <li class="list-group-item d-flex justify-content-between bg-light">
                        <strong>Total</strong>
                        <strong class="text-primary">Rp {{ number_format($job->inv_ppn_meterai ?? $job->total_sales ?? 0, 0, ',', '.') }}</strong>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Timeline / Updates -->
        @if($job->remarks && $job->remarks->count() > 0)
        <div class="card portal-card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Updates</h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    @foreach($job->remarks->take(5) as $remark)
                    <div class="mb-3 pb-3 border-bottom">
                        <small class="text-muted">{{ $remark->created_at->format('d M H:i') }}</small>
                        <p class="mb-0">{{ $remark->remark_text }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
