@extends('layouts.app')

@section('title', 'Job Details - ' . $job->job_number)

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('jobs.index') }}">Jobs</a></li>
                <li class="breadcrumb-item active">{{ $job->job_number }}</li>
            </ol>
        </nav>
        <h1>
            <i class="bi bi-clipboard-check me-2"></i>{{ $job->job_number }}
            <span class="badge {{ $job->franchise == 'CV' ? 'bg-info' : 'bg-secondary' }} fs-6">{{ $job->franchise }}</span>
            @if($job->department)
                @php
                    $deptBadge = match(strtoupper($job->department ?? '')) {
                        'W' => ['bg-primary', 'Workshop'],
                        'B' => ['bg-warning text-dark', 'Body Paint'],
                        default => ['bg-secondary', $job->department],
                    };
                @endphp
                <span class="badge {{ $deptBadge[0] }} fs-6">{{ $deptBadge[1] }}</span>
            @endif
            @if($job->type_sale)
                @php
                    $typeSaleBadge = match(strtoupper($job->type_sale ?? '')) {
                        'INT' => ['bg-info', 'Internal'],
                        'WAR' => ['bg-warning text-dark', 'Warranty'],
                        'CASH' => ['bg-success', 'Cash'],
                        default => ['bg-secondary', $job->type_sale],
                    };
                @endphp
                <span class="badge {{ $typeSaleBadge[0] }} fs-6">{{ $typeSaleBadge[1] }}</span>
            @endif
            @if($job->status == 'invoiced')
                <span class="badge bg-success fs-6">Invoiced</span>
            @else
                <span class="badge bg-warning text-dark fs-6">Uninvoiced</span>
            @endif
        </h1>
    </div>
    <div class="d-flex gap-2 d-print-none">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a href="{{ route('jobs.export-pdf', $job) }}" target="_blank" class="btn btn-outline-secondary">
            <i class="bi bi-file-pdf me-1"></i>Export PDF
        </a>
        @if(auth()->user()->canEdit())
            @if($job->status == 'uninvoiced')
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#markInvoicedModal">
                <i class="bi bi-check-circle me-1"></i>Mark Invoiced
            </button>
            @endif
        @endif
    </div>
</div>

@if(auth()->user()->canEdit() || auth()->user()->hasRole('foreman'))
<form action="{{ route('jobs.update', $job) }}" method="POST" id="jobForm">
    @csrf
    @method('PUT')
@php 
    $readonly = !auth()->user()->canEdit(); 
    $isControlTower = auth()->user()->hasRole('control_tower');
@endphp
@else
<div>
@php 
    $readonly = true;
    $isControlTower = false;
@endphp
@endif
    
    <div class="row g-4">
        <div class="col-md-8">
            <!-- Job Identification -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span><i class="bi bi-tag me-2"></i>Job Identification</span>
                    @if(auth()->user()->canEdit())
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-save me-1"></i>Save
                    </button>
                    @endif
                </div>
                <div class="card-body py-3">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Department</label>
                             <input type="text" name="department" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('department', $job->department) }}" placeholder="GR/BP">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">WIP</label>
                            <input type="text" name="job_number" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('job_number', $job->job_number) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Franchise</label>
                            <select name="franchise" class="form-select form-select-sm" {{ $readonly ? 'disabled' : '' }} required>
                                <option value="PC" {{ old('franchise', $job->franchise) == 'PC' ? 'selected' : '' }}>PC</option>
                                <option value="CV" {{ old('franchise', $job->franchise) == 'CV' ? 'selected' : '' }}>CV</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Created</label>
                            <input type="date" name="job_date" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('job_date', $job->job_date?->format('Y-m-d')) }}">
                        </div>
                        
                        <!-- New Date Fields -->
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Date In / Check-In</label>
                            <div class="input-group input-group-sm">
                                <input type="date" name="date_in" class="form-control" {{ $readonly ? 'disabled' : '' }} value="{{ old('date_in', $job->date_in?->format('Y-m-d')) }}">
                                <input type="time" name="check_in_time" class="form-control" {{ $readonly ? 'disabled' : '' }} value="{{ old('check_in_time', $job->check_in_time ? \Carbon\Carbon::parse($job->check_in_time)->format('H:i') : '') }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Date Out</label>
                            <input type="date" name="date_out" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('date_out', $job->date_out?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Promise Date</label>
                            <input type="date" name="promise_date" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('promise_date', $job->promise_date?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Deadline</label>
                            <input type="date" name="deadline" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('deadline', $job->deadline?->format('Y-m-d')) }}">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vehicle & Customer -->
            <div class="card mb-3">
                <div class="card-header py-2">
                    <i class="bi bi-car-front me-2"></i>Vehicle & Customer
                </div>
                <div class="card-body py-3">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Reg No</label>
                            <input type="text" name="plate_number" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('plate_number', $job->plate_number) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Chassis Number</label>
                            <input type="text" name="chassis_number" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('chassis_number', $job->chassis_number) }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Type Unit</label>
                            <input type="text" name="type_unit" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('type_unit', $job->type_unit) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Account No</label>
                            <input type="text" name="account_no" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('account_no', $job->account_no) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-0">Date First Reg</label>
                            <input type="date" name="date_first_reg" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('date_first_reg', $job->date_first_reg?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted mb-0">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('customer_name', $job->customer_name) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted mb-0">
                                Customer Address
                                @if(!$readonly)
                                <button type="button" class="btn btn-outline-primary btn-sm ms-2 py-0 px-2" id="lookupAddressBtn" title="Lookup address from customer database">
                                    <i class="bi bi-search"></i> Lookup
                                </button>
                                @endif
                            </label>
                            <textarea name="customer_address" id="customer_address" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} rows="3" style="resize: vertical;">{{ old('customer_address', $job->customer_address) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personnel & Job Info -->
            <div class="card mb-3">
                <div class="card-header py-2">
                    <i class="bi bi-people me-2"></i>Personnel & Info
                </div>
                <div class="card-body py-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-0">Service Advisor</label>
                            <select name="service_advisor" class="form-select form-select-sm" {{ $readonly ? 'disabled' : '' }}>
                                <option value="">-- Select SA --</option>
                                @foreach($serviceAdvisors as $sa)
                                    <option value="{{ $sa->name }}" {{ old('service_advisor', $job->service_advisor) == $sa->name ? 'selected' : '' }}>{{ $sa->name }}</option>
                                @endforeach
                                @if($job->service_advisor && !$serviceAdvisors->contains('name', $job->service_advisor))
                                    <option value="{{ $job->service_advisor }}" selected>{{ $job->service_advisor }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-0">Foreman</label>
                            <select name="foreman" class="form-select form-select-sm" {{ $readonly ? 'disabled' : '' }}>
                                <option value="">-- Select Foreman --</option>
                                @foreach($foremen as $fm)
                                    <option value="{{ $fm->name }}" {{ old('foreman', $job->foreman) == $fm->name ? 'selected' : '' }}>{{ $fm->name }}</option>
                                @endforeach
                                @if($job->foreman && !$foremen->contains('name', $job->foreman))
                                    <option value="{{ $job->foreman }}" selected>{{ $job->foreman }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-0">Technician</label>
                            <input type="text" name="technician" class="form-control form-control-sm" {{ ($readonly && !auth()->user()->hasRole('foreman')) ? 'disabled' : '' }} value="{{ old('technician', $job->technician) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-0">Block</label>
                            <input type="text" name="block" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('block', $job->block) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-0">Job Type</label>
                            <select name="job_type" class="form-select form-select-sm" {{ $readonly ? 'disabled' : '' }}>
                                <option value="">-- Select Type --</option>
                                <option value="quick_service" {{ old('job_type', $job->job_type) == 'quick_service' ? 'selected' : '' }}>Quick Service</option>
                                <option value="warranty" {{ old('job_type', $job->job_type) == 'warranty' ? 'selected' : '' }}>Warranty</option>
                                <option value="isp" {{ old('job_type', $job->job_type) == 'isp' ? 'selected' : '' }}>ISP</option>
                                <option value="campaign" {{ old('job_type', $job->job_type) == 'campaign' ? 'selected' : '' }}>Campaign</option>
                                <option value="cash" {{ old('job_type', $job->job_type) == 'cash' ? 'selected' : '' }}>Cash</option>
                                <option value="booking_service" {{ old('job_type', $job->job_type) == 'booking_service' ? 'selected' : '' }}>Booking Service</option>
                                <option value="pdi" {{ old('job_type', $job->job_type) == 'pdi' ? 'selected' : '' }}>Pre Delivery Inspection</option>
                                <option value="internal" {{ old('job_type', $job->job_type) == 'internal' ? 'selected' : '' }}>Internal</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-0">Work Status</label>
                            <select name="work_status" class="form-select form-select-sm" {{ $readonly ? 'disabled' : '' }}>
                                <option value="">-- Select Status --</option>
                                @foreach(\App\Models\Job::getWorkStatusOptions() as $opt)
                                <option value="{{ $opt->value }}" {{ old('work_status', $job->work_status) == $opt->value ? 'selected' : '' }}>{{ $opt->label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small text-muted mb-0">Job Description</label>
                            <input type="text" name="job_description" class="form-control form-control-sm" {{ $readonly ? 'disabled' : '' }} value="{{ old('job_description', $job->job_description) }}">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales -->
            <div class="card mb-3">
                <div class="card-header py-2">
                    <i class="bi bi-currency-dollar me-2"></i>Sales
                </div>
                <div class="card-body py-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-0">Labour Sales (Rp)</label>
                            <input type="number" name="labour_sales" class="form-control form-control-sm bg-light" disabled value="{{ old('labour_sales', $job->labour_sales) }}" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-0">Part Sales (Rp)</label>
                            <input type="number" name="part_sales" class="form-control form-control-sm bg-light" disabled value="{{ old('part_sales', $job->part_sales) }}" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-0">Total Sales (Rp)</label>
                            <input type="number" name="total_sales" class="form-control form-control-sm fw-bold bg-light" disabled value="{{ old('total_sales', $job->total_sales) }}" step="0.01">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoice History -->
            @if($job->invoices->count() > 0)
            <div class="card mb-3">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-receipt me-2"></i>Invoice History</span>
                    <span class="badge bg-success">Total: Rp {{ number_format($job->total_invoice_amount, 0, ',', '.') }}</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Invoice #</th>
                                <th>Type</th>
                                <th>Type Sale</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($job->invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_date?->format('d/m/Y') ?? '-' }}</td>
                                <td><code>{{ $invoice->invoice_number ?? '-' }}</code></td>
                                <td>
                                    @if($invoice->invoice_type == 'credit_note')
                                        <span class="badge bg-danger"><i class="bi bi-dash-circle me-1"></i>Credit Note</span>
                                    @else
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Invoice</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $typeSaleBadge = match(strtoupper($invoice->type_sale ?? '')) {
                                            'INT' => 'bg-info',
                                            'WAR' => 'bg-warning text-dark',
                                            'CASH' => 'bg-primary',
                                            default => 'bg-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $typeSaleBadge }}">{{ $invoice->type_sale_label }}</span>
                                </td>
                                <td class="text-end {{ $invoice->invoice_type == 'credit_note' ? 'text-danger' : '' }}">
                                    {{ $invoice->invoice_type == 'credit_note' ? '-' : '' }}Rp {{ number_format($invoice->inv_ppn_meterai, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Order & Parts -->
            <div class="card mb-3">
                <div class="card-header py-2">
                    <span>
                        <i class="bi bi-gear me-2"></i>Order & Parts
                        @if($job->need_part)
                            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-exclamation-triangle"></i> Needs Parts</span>
                        @endif
                    </span>
                </div>
                <div class="card-body py-3">
                    {{-- Needs Parts Toggle --}}
                    @if(auth()->user()->canEdit() || auth()->user()->hasRole(['foreman', 'sparepart']))
                    <div class="form-check">
                        <input type="hidden" name="need_part" value="0">
                        <input class="form-check-input" type="checkbox" name="need_part" value="1" id="needPart" 
                               {{ old('need_part', $job->need_part) ? 'checked' : '' }}
                               {{ (($readonly && !auth()->user()->hasRole(['foreman', 'sparepart'])) || $isControlTower) ? 'disabled' : '' }}>
                        <label class="form-check-label text-warning fw-bold" for="needPart">
                            <i class="bi bi-exclamation-triangle me-1"></i>This job needs parts
                        </label>
                    </div>
                    @else
                    <div>
                        <span class="text-muted me-2">Needs Parts:</span>
                        @if($job->need_part)
                            <span class="badge bg-warning text-dark"><i class="bi bi-check-lg"></i> Yes</span>
                        @else
                            <span class="text-muted">No</span>
                        @endif
                    </div>
                    @endif

                    {{-- Part Orders Table (inline display when has parts) --}}
                    @if($job->partOrders->count() > 0)
                    <div class="mt-3">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>RQ Number</th>
                                            <th>Status</th>
                                            <th>Order Date</th>
                                            <th>Expected</th>
                                            <th>Received</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($job->partOrders as $partOrder)
                                        <tr>
                                            <td>
                                                <a href="{{ route('part-orders.edit', $partOrder) }}" class="fw-bold text-decoration-none">
                                                    {{ $partOrder->rq ?: '-' }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: {{ $partOrder->status_color }}">
                                                    {{ $partOrder->status_label }}
                                                </span>
                                            </td>
                                            <td><small>{{ $partOrder->order_date?->format('d/m/y') ?: '-' }}</small></td>
                                            <td><small>{{ $partOrder->expected_date?->format('d/m/y') ?: '-' }}</small></td>
                                            <td><small>{{ $partOrder->received_date?->format('d/m/y') ?: '-' }}</small></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            <a href="{{ route('part-orders.create', ['job_id' => $job->id]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-lg me-1"></i>Add Part Order
                            </a>
                        </div>
                    </div>
                    @elseif($job->need_part)
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        No part orders yet. 
                        <a href="{{ route('part-orders.create', ['job_id' => $job->id]) }}" class="alert-link">Add a part order</a> to track parts for this job.
                    </div>
                    @endif
                </div>
            </div>

            {{-- Close main form/div before comments to avoid nested form issue --}}
            @if(auth()->user()->canEdit() || auth()->user()->hasRole('foreman'))
            </form>
            @else
            </div>
            @endif

            <!-- Comments Section -->
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-chat-dots me-2"></i>Comments <span class="badge bg-secondary ms-1">{{ $job->remarks->count() }}</span></span>
                </div>
                <div class="card-body p-0">
                    @php
                        $canComment = false;
                        $commentBlockReason = '';
                        $commentUser = auth()->user();
                        
                        if ($commentUser->hasAnyRole(['admin', 'manager', 'control_tower'])) {
                            $canComment = true;
                        } elseif ($commentUser->hasRole('sa')) {
                            if ($commentUser->serviceAdvisor?->name === $job->service_advisor) {
                                $canComment = true;
                            } else {
                                $commentBlockReason = 'You can only add remarks on jobs assigned to you.';
                            }
                        } elseif ($commentUser->hasRole('foreman')) {
                            if ($commentUser->foreman?->name === $job->foreman) {
                                $canComment = true;
                            } else {
                                $commentBlockReason = 'You can only add remarks on jobs assigned to you.';
                            }
                        } elseif ($commentUser->hasRole('sparepart')) {
                            if ($job->need_part) {
                                $canComment = true;
                            } else {
                                $commentBlockReason = 'You can only add remarks on jobs that need parts.';
                            }
                        }
                    @endphp
                    <!-- Comment List -->
                    <div class="comment-container" id="commentContainer">
                        @forelse($job->remarks->where('parent_id', null) as $remark)
                        <div class="comment-item {{ auth()->id() == $remark->user_id ? 'comment-own' : '' }}" id="comment-{{ $remark->id }}">
                            <div class="comment-avatar" style="background-color: {{ sprintf('#%06X', crc32($remark->commenter_name) & 0xFFFFFF) }}">
                                {{ $remark->commenter_initials }}
                            </div>
                            <div class="comment-content flex-grow-1">
                                <div class="comment-meta d-flex align-items-center gap-2">
                                    <span class="comment-author">{{ $remark->commenter_name }}</span>
                                    @if($remark->user)
                                    <span class="badge bg-{{ $remark->user->role == 'admin' ? 'danger' : ($remark->user->role == 'manager' ? 'primary' : ($remark->user->role == 'control_tower' ? 'info' : ($remark->user->role == 'sparepart' ? 'warning' : 'secondary'))) }} badge-sm">{{ $remark->user->getRoleDisplayName() }}</span>
                                    @endif
                                    <span class="comment-time">{{ $remark->time_ago }}</span>
                                    @if($canComment)
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-auto reply-btn" data-comment-id="{{ $remark->id }}" data-author="{{ $remark->commenter_name }}">
                                        <i class="bi bi-reply"></i> Reply
                                    </button>
                                    @endif
                                    @if(auth()->user()->hasRole('admin'))
                                    <form action="{{ route('remarks.destroy', $remark) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this comment?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-link btn-sm p-0 ms-2 text-danger" title="Delete comment">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                                <div class="comment-text">{!! $remark->formatted_text !!}</div>
                                @if($remark->hasImages())
                                <div class="comment-images mt-2 d-flex flex-wrap gap-2">
                                    @foreach($remark->image_urls as $imageUrl)
                                    <a href="{{ $imageUrl }}" target="_blank" class="comment-image-thumb" data-bs-toggle="tooltip" title="Click to view full size">
                                        <img src="{{ $imageUrl }}" alt="Comment image" class="rounded" style="max-height: 80px; max-width: 120px; object-fit: cover; cursor: pointer; border: 1px solid #dee2e6;">
                                    </a>
                                    @endforeach
                                </div>
                                @endif

                                {{-- Replies --}}
                                @if($remark->replies->count() > 0)
                                <div class="replies-container mt-2 ms-3 ps-3 border-start border-2">
                                    @foreach($remark->replies as $reply)
                                    <div class="comment-item reply-item py-2 {{ auth()->id() == $reply->user_id ? 'comment-own' : '' }}" id="comment-{{ $reply->id }}">
                                        <div class="comment-avatar comment-avatar-sm" style="background-color: {{ sprintf('#%06X', crc32($reply->commenter_name) & 0xFFFFFF) }}">
                                            {{ $reply->commenter_initials }}
                                        </div>
                                        <div class="comment-content flex-grow-1">
                                            <div class="comment-meta d-flex align-items-center gap-2">
                                                <span class="comment-author">{{ $reply->commenter_name }}</span>
                                                @if($reply->user)
                                                <span class="badge bg-{{ $reply->user->role == 'admin' ? 'danger' : ($reply->user->role == 'manager' ? 'primary' : ($reply->user->role == 'control_tower' ? 'info' : ($reply->user->role == 'sparepart' ? 'warning' : 'secondary'))) }} badge-sm" style="font-size: 0.65rem;">{{ $reply->user->getRoleDisplayName() }}</span>
                                                @endif
                                                <span class="comment-time" style="font-size: 0.75rem;">{{ $reply->time_ago }}</span>
                                                @if($canComment)
                                                <button type="button" class="btn btn-link btn-sm p-0 ms-auto reply-btn" data-comment-id="{{ $reply->id }}" data-author="{{ $reply->commenter_name }}">
                                                    <i class="bi bi-reply"></i> Reply
                                                </button>
                                                @endif
                                                @if(auth()->user()->hasRole('admin'))
                                                <form action="{{ route('remarks.destroy', $reply) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this comment?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-link btn-sm p-0 ms-2 text-danger" title="Delete comment">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                                @endif
                                            </div>
                                            <div class="comment-text" style="font-size: 0.9rem;">{!! $reply->formatted_text !!}</div>
                                            @if($reply->hasImages())
                                            <div class="comment-images mt-1 d-flex flex-wrap gap-1">
                                                @foreach($reply->image_urls as $imageUrl)
                                                <a href="{{ $imageUrl }}" target="_blank">
                                                    <img src="{{ $imageUrl }}" alt="Reply image" class="rounded" style="max-height: 60px; max-width: 80px; object-fit: cover;">
                                                </a>
                                                @endforeach
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                        @empty
                        <div class="text-center text-muted py-4" id="noCommentsMessage">
                            <i class="bi bi-chat-left-text fs-1 opacity-50"></i>
                            <p class="mb-0 mt-2">No comments yet. Be the first to add one!</p>
                        </div>
                        @endforelse
                    </div>

                    <!-- Add Comment Form (Inline) -->
                    @if($canComment)
                    <div class="comment-form-container">
                        <form id="inlineCommentForm" class="d-flex gap-2 align-items-start" enctype="multipart/form-data">
                            @csrf
                            <div class="comment-avatar comment-avatar-sm" style="background-color: {{ sprintf('#%06X', crc32(auth()->user()->name) & 0xFFFFFF) }}">
                                {{ auth()->user()->initials }}
                            </div>
                            <div class="flex-grow-1">
                                <textarea name="remark_text" class="form-control form-control-sm" rows="2" placeholder="Write a comment..." required id="remarkTextInput"></textarea>
                                <div class="mt-2 d-flex align-items-center gap-2">
                                    <label class="btn btn-outline-secondary btn-sm mb-0" for="commentImages" data-bs-toggle="tooltip" title="Attach images (max 3)">
                                        <i class="bi bi-image"></i> <span class="d-none d-md-inline">Add Images</span>
                                    </label>
                                    <input type="file" id="commentImages" name="images[]" accept="image/*" multiple class="d-none" max="3">
                                    <small class="text-muted d-none d-md-inline">Max 3 images, 10MB each</small>
                                </div>
                                <div id="imagePreviewContainer" class="mt-2 d-flex flex-wrap gap-2" style="display: none;"></div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" id="submitCommentBtn">
                                <i class="bi bi-send"></i>
                            </button>
                        </form>
                    </div>
                    @elseif($commentBlockReason)
                    <div class="p-3 text-center text-muted small">
                        <i class="bi bi-lock me-1"></i>{{ $commentBlockReason }}
                    </div>
                    @endif
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="card mt-3 activity-timeline-card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history me-2"></i>Activity Timeline <span class="badge bg-secondary ms-1 d-print-none">{{ $job->activities->count() }}</span></span>
                </div>
                <div class="card-body p-0">
                    <div class="timeline-container d-print-block" style="max-height: 300px; overflow-y: auto;">
                        @forelse($job->activities as $activity)
                        <div class="timeline-item d-flex p-2 border-bottom">
                            <div class="timeline-icon me-3 d-print-none">
                                <span class="badge bg-{{ $activity->color }} rounded-circle p-2">
                                    <i class="bi bi-{{ $activity->icon }}"></i>
                                </span>
                            </div>
                            <div class="timeline-content flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="text-{{ $activity->color }}">{{ ucfirst(str_replace('_', ' ', $activity->action)) }}</strong>
                                        <p class="mb-0 small text-muted">{!! $activity->description !!}</p>
                                    </div>
                                    <small class="text-muted text-nowrap">
                                        <span class="d-none d-print-inline">{{ $activity->created_at->format('d/m/Y H:i') }}</span>
                                        <span class="d-print-none">{{ $activity->created_at->diffForHumans() }}</span>
                                    </small>
                                </div>
                                <small class="text-muted">by {{ $activity->user_name }}</small>
                            </div>
                        </div>
                        @empty
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-clock-history fs-1 opacity-50 d-print-none"></i>
                            <p class="mb-0 mt-2">No activity recorded yet</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>

        <div class="col-md-4">
            <!-- Status Card -->
            <div class="card mb-3">
                <div class="card-header py-2">
                    <i class="bi bi-info-circle me-2"></i>Status
                </div>
                <div class="card-body py-3">
                    @if($job->status == 'invoiced')
                        <div class="alert alert-success mb-3 py-2">
                            <strong><i class="bi bi-check-circle me-1"></i>Invoiced</strong><br>
                            <small class="d-block">{{ $job->invoice_number }}</small>
                            <small class="d-block text-muted">{{ $job->invoiced_at?->format('d/m/Y H:i') }}</small>
                        </div>
                        <ul class="list-group list-group-flush mb-0 small">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Amount</span>
                                <span class="fw-bold">{{ number_format($job->inv_amount, 2) }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">PPN</span>
                                <span>{{ number_format($job->inv_ppn, 2) }}</span>
                            </li>
                            @if($job->inv_ppn_meterai > 0)
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Meterai</span>
                                <span>{{ number_format($job->inv_ppn_meterai, 2) }}</span>
                            </li>
                            @endif
                        </ul>
                    @else
                        <div class="alert alert-warning mb-3 py-2">
                            <strong><i class="bi bi-clock me-1"></i>Uninvoiced</strong>
                        </div>
                        @if(auth()->user()->canMarkInvoiced())
                        <button type="button" class="btn btn-success btn-sm w-100" data-bs-toggle="modal" data-bs-target="#markInvoicedModal">
                            <i class="bi bi-check-circle me-1"></i>Mark as Invoiced
                        </button>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card">
                <div class="card-header py-2">
                    <i class="bi bi-gear me-2"></i>Actions
                </div>
                <div class="card-body py-3">
                    @if(auth()->user()->canEdit() || auth()->user()->hasRole('foreman'))
                    <button type="submit" form="jobForm" class="btn btn-primary btn-sm w-100 mb-2">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    @endif
                    <a href="{{ route('jobs.index') }}" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                        <i class="bi bi-arrow-left me-1"></i>Back to List
                    </a>
                    @if(auth()->user()->hasRole('admin'))
                    <hr>
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash me-1"></i>Delete Job
                    </button>
                    @endif
                </div>
            </div>
    </div>

<!-- Mark Invoiced Modal -->
<div class="modal fade" id="markInvoicedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('jobs.mark-invoiced', $job) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Mark as Invoiced</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Invoice Number <span class="text-danger">*</span></label>
                        <input type="text" name="invoice_number" class="form-control" required placeholder="Enter invoice number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remark <span class="text-danger">*</span></label>
                        <textarea name="remark" class="form-control" required placeholder="Enter reason or details for invoicing..." rows="3"></textarea>
                        <div class="form-text">A remark is required when marking a job as invoiced.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Mark as Invoiced</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('jobs.destroy', $job) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete job <strong>{{ $job->job_number }}</strong>?</p>
                    <p class="text-danger mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Comment AJAX Script -->
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Customer Address Lookup
    const lookupBtn = document.getElementById('lookupAddressBtn');
    if (lookupBtn) {
        lookupBtn.addEventListener('click', async function() {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Searching...';
            btn.disabled = true;
            
            try {
                // Get current form values
                const customerName = document.querySelector('input[name="customer_name"]')?.value || '';
                const plateNumber = document.querySelector('input[name="plate_number"]')?.value || '';
                const accountNo = document.querySelector('input[name="account_no"]')?.value || '';
                
                // Build query params
                const params = new URLSearchParams();
                if (accountNo) params.append('customer_id', accountNo);
                if (customerName) params.append('customer_name', customerName);
                if (plateNumber) params.append('plate', plateNumber);
                
                const response = await fetch('/api/customers/lookup-address?' + params.toString());
                const data = await response.json();
                
                if (data.found && data.address) {
                    document.getElementById('customer_address').value = data.address;
                    // Visual feedback
                    btn.innerHTML = '<i class="bi bi-check"></i> Found!';
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-success');
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-primary');
                    }, 2000);
                } else {
                    alert('No address found for this customer. Try filling in Customer Name or Account No first.');
                    btn.innerHTML = originalText;
                }
            } catch (err) {
                console.error('Lookup error:', err);
                alert('Error looking up address: ' + err.message);
                btn.innerHTML = originalText;
            } finally {
                btn.disabled = false;
            }
        });
    }

    const form = document.getElementById('inlineCommentForm');
    if (!form) return;

    const submitBtn = document.getElementById('submitCommentBtn');
    const textarea = document.getElementById('remarkTextInput');
    const container = document.getElementById('commentContainer');
    const noCommentsMsg = document.getElementById('noCommentsMessage');
    const imageInput = document.getElementById('commentImages');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');

    // Image preview handling
    if (imageInput) {
        imageInput.addEventListener('change', function() {
            imagePreviewContainer.innerHTML = '';
            const files = Array.from(this.files).slice(0, 3); // Max 3 images
            
            if (files.length > 0) {
                imagePreviewContainer.style.display = 'flex';
                files.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.createElement('div');
                        preview.className = 'position-relative';
                        preview.innerHTML = `
                            <img src="${e.target.result}" class="rounded" style="height: 60px; width: 60px; object-fit: cover; border: 2px solid #dee2e6;">
                            <button type="button" class="btn btn-danger btn-sm position-absolute" 
                                style="top: -5px; right: -5px; padding: 0 4px; font-size: 10px; line-height: 1.2;"
                                onclick="removeImagePreview(${index})">×</button>
                        `;
                        imagePreviewContainer.appendChild(preview);
                    };
                    reader.readAsDataURL(file);
                });
            } else {
                imagePreviewContainer.style.display = 'none';
            }
        });
    }

    // Function to remove image from preview
    window.removeImagePreview = function(index) {
        const dt = new DataTransfer();
        const files = Array.from(imageInput.files);
        files.forEach((file, i) => {
            if (i !== index) dt.items.add(file);
        });
        imageInput.files = dt.files;
        imageInput.dispatchEvent(new Event('change'));
    };
    // Reply state
    let replyToId = null;
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const remarkText = textarea.value.trim();
        if (!remarkText) return;

        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            // Use FormData for file uploads
            const formData = new FormData();
            formData.append('remark_text', remarkText);
            
            // Add parent_id if replying
            if (replyToId) {
                formData.append('parent_id', replyToId);
            }
            
            // Add images if selected (compress first)
            if (imageInput && imageInput.files.length > 0) {
                const rawFiles = Array.from(imageInput.files).slice(0, 3);
                const compressedFiles = await Promise.all(rawFiles.map(file => compressImage(file)));
                
                compressedFiles.forEach(file => {
                    formData.append('images[]', file);
                });
            }
            
            const response = await fetch('{{ route("jobs.add-remark", $job) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Remove "no comments" message if present
                if (noCommentsMsg) noCommentsMsg.remove();

                // Build images HTML if present
                let imagesHtml = '';
                if (data.remark.images && data.remark.images.length > 0) {
                    imagesHtml = '<div class="comment-images mt-2 d-flex flex-wrap gap-2">';
                    data.remark.images.forEach(url => {
                        imagesHtml += `<a href="${url}" target="_blank" class="comment-image-thumb">
                            <img src="${url}" alt="Comment image" class="rounded" style="max-height: 80px; max-width: 120px; object-fit: cover; border: 1px solid #dee2e6;">
                        </a>`;
                    });
                    imagesHtml += '</div>';
                }

                // Create new comment element
                const commentHtml = `
                    <div class="comment-item comment-own" id="comment-${data.remark.id}">
                        <div class="comment-avatar" style="background-color: ${data.remark.avatar_color}">
                            ${data.remark.initials}
                        </div>
                        <div class="comment-content">
                            <div class="comment-meta">
                                <span class="comment-author">${data.remark.commenter_name}</span>
                                <span class="badge bg-${data.remark.role_color} badge-sm">${data.remark.role_display}</span>
                                <span class="comment-time">just now</span>
                            </div>
                            <div class="comment-text">${data.remark.formatted_text || data.remark.text}</div>
                            ${imagesHtml}
                            <div class="mt-1 d-flex gap-2 align-items-center">
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none reply-btn" 
                                    data-comment-id="${data.remark.id}" data-author="${data.remark.commenter_name}">
                                    <i class="bi bi-reply-fill"></i> Reply
                                </button>
                                
                                ${ data.remark.can_delete ? `
                                <form action="/remarks/${data.remark.id}" method="POST" class="d-inline" onsubmit="return confirm('Delete this comment?');">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-link btn-sm p-0 text-danger" title="Delete comment">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                ` : '' }
                            </div>
                        </div>
                    </div>
                `;

                // If replying, add to parent's replies; otherwise prepend to container
                if (replyToId) {
                    const parentComment = document.getElementById('comment-' + replyToId);
                    if (parentComment) {
                        let repliesContainer = parentComment.querySelector('.replies-container');
                        if (!repliesContainer) {
                            repliesContainer = document.createElement('div');
                            repliesContainer.className = 'replies-container mt-2 ms-3 ps-3 border-start border-2';
                            parentComment.querySelector('.comment-content').appendChild(repliesContainer);
                        }
                        repliesContainer.insertAdjacentHTML('beforeend', commentHtml);
                    }
                } else {
                    container.insertAdjacentHTML('afterbegin', commentHtml);
                }

                // Update comment count badge
                const badge = document.querySelector('.card-header .badge.bg-secondary');
                if (badge) {
                    badge.textContent = parseInt(badge.textContent) + 1;
                }
                
                // Update activity timeline
                const timelineContainer = document.querySelector('.timeline-container');
                if (timelineContainer) {
                    const activityType = replyToId ? 'Reply added' : 'Remark added';
                    const activityHtml = `
                        <div class="timeline-item d-flex p-2 border-bottom" style="background-color: #f8f9fa;">
                            <div class="timeline-icon me-3">
                                <span class="badge bg-secondary rounded-circle p-2">
                                    <i class="bi bi-chat-dots"></i>
                                </span>
                            </div>
                            <div class="timeline-content flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong class="text-secondary">${activityType}</strong>
                                        <p class="mb-0 small text-muted">${activityType}: "${data.remark.text.substring(0, 50)}${data.remark.text.length > 50 ? '...' : ''}"</p>
                                    </div>
                                    <small class="text-muted text-nowrap">just now</small>
                                </div>
                                <small class="text-muted">by ${data.remark.commenter_name}</small>
                            </div>
                        </div>
                    `;
                    timelineContainer.insertAdjacentHTML('afterbegin', activityHtml);
                    
                    // Update activity count badge
                    const activityBadge = document.querySelector('.activity-timeline-card .badge.bg-secondary');
                    if (activityBadge) {
                        activityBadge.textContent = parseInt(activityBadge.textContent) + 1;
                    }
                }

                // Clear textarea and image input
                textarea.value = '';
                if (imageInput) {
                    imageInput.value = '';
                    imagePreviewContainer.innerHTML = '';
                    imagePreviewContainer.style.display = 'none';
                }
                
                // Reset reply state
                replyToId = null;
                const indicator = document.getElementById('replyIndicator');
                if (indicator) indicator.remove();
                
                // Update notification badge if count provided (fallback for websocket issues)
                if (data.unread_count !== undefined) {
                    const badge = document.querySelector('#notificationDropdown .badge');
                    if (badge) {
                        badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                        if (data.unread_count > 0) {
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    } else if (data.unread_count > 0) {
                        const btn = document.getElementById('notificationDropdown');
                        if (btn) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                            newBadge.style.fontSize = '0.6rem';
                            newBadge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                            btn.appendChild(newBadge);
                        }
                    }
                }
            } else {
                alert(data.message || 'Failed to add comment');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-send"></i>';
        }
    });
    
    // Reply button handler
    // Reply button handler (Event Delegation)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.reply-btn');
        if (btn) {
            e.preventDefault();
            replyToId = btn.dataset.commentId;
            const authorName = btn.dataset.author;
            textarea.value = `@${authorName} `;
            textarea.focus();
            textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Show reply indicator
            let indicator = document.getElementById('replyIndicator');
            if (indicator) indicator.remove();
            const ind = document.createElement('div');
            ind.id = 'replyIndicator';
            ind.className = 'alert alert-info py-1 px-2 mb-2 d-flex align-items-center';
            ind.innerHTML = `<small><i class="bi bi-reply me-1"></i>Replying to <strong>${authorName}</strong></small>
                <button type="button" class="btn-close btn-close-sm ms-auto" onclick="cancelReply()"></button>`;
            textarea.parentElement.insertBefore(ind, textarea);
        }
    });
    
    // Cancel reply
    window.cancelReply = function() {
        replyToId = null;
        const indicator = document.getElementById('replyIndicator');
        if (indicator) indicator.remove();
    };
    
    // @Mention autocomplete
    let mentionDropdown = null;
    let mentionSearchTimeout = null;
    
    textarea.addEventListener('input', function(e) {
        const cursorPos = this.selectionStart;
        const textBeforeCursor = this.value.substring(0, cursorPos);
        const mentionMatch = textBeforeCursor.match(/@(\w*)$/);
        
        if (mentionMatch) {
            const query = mentionMatch[1];
            clearTimeout(mentionSearchTimeout);
            mentionSearchTimeout = setTimeout(() => searchMentions(query, cursorPos - mentionMatch[0].length), 200);
        } else {
            closeMentionDropdown();
        }
    });
    
    async function searchMentions(query, startPos) {
        if (query.length < 1) {
            closeMentionDropdown();
            return;
        }
        
        try {
            const response = await fetch(`/api/users/search?q=${encodeURIComponent(query)}`);
            const users = await response.json();
            
            if (users.length > 0) {
                showMentionDropdown(users, startPos);
            } else {
                closeMentionDropdown();
            }
        } catch (err) {
            console.error('Mention search error:', err);
        }
    }
    
    function showMentionDropdown(users, startPos) {
        closeMentionDropdown();
        
        const coords = getCaretCoordinates(textarea);
        mentionDropdown = document.createElement('div');
        mentionDropdown.className = 'mention-dropdown shadow rounded bg-white border';
        mentionDropdown.style.cssText = `position: absolute; z-index: 1050; max-height: 200px; overflow-y: auto; min-width: 200px;`;
        
        users.forEach(user => {
            const item = document.createElement('div');
            item.className = 'mention-item px-3 py-2 cursor-pointer';
            item.style.cursor = 'pointer';
            item.innerHTML = `<strong>@${user.name}</strong> <small class="text-muted">${user.role}</small>`;
            item.addEventListener('click', () => insertMention(user.name, startPos));
            item.addEventListener('mouseenter', () => item.classList.add('bg-light'));
            item.addEventListener('mouseleave', () => item.classList.remove('bg-light'));
            mentionDropdown.appendChild(item);
        });
        
        textarea.parentElement.style.position = 'relative';
        textarea.parentElement.appendChild(mentionDropdown);
    }
    
    function closeMentionDropdown() {
        if (mentionDropdown) {
            mentionDropdown.remove();
            mentionDropdown = null;
        }
    }
    
    function insertMention(name, startPos) {
        const beforeMention = textarea.value.substring(0, startPos);
        const afterMention = textarea.value.substring(textarea.selectionStart);
        const mentionText = name.includes(' ') ? `@"${name}" ` : `@${name} `;
        textarea.value = beforeMention + mentionText + afterMention;
        textarea.focus();
        closeMentionDropdown();
    }
    
    // Image compression helper
    async function compressImage(file) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (e) => {
                const img = new Image();
                img.src = e.target.result;
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // Max dimensions
                    const MAX_WIDTH = 1200;
                    const MAX_HEIGHT = 1200;
                    let width = img.width;
                    let height = img.height;
                    
                    if (width > height) {
                        if (width > MAX_WIDTH) {
                            height *= MAX_WIDTH / width;
                            width = MAX_WIDTH;
                        }
                    } else {
                        if (height > MAX_HEIGHT) {
                            width *= MAX_HEIGHT / height;
                            height = MAX_HEIGHT;
                        }
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    canvas.toBlob((blob) => {
                        // Force .jpg extension
                        const fileName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";
                        resolve(new File([blob], fileName, {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        }));
                    }, 'image/jpeg', 0.8);
                };
            };
        });
    }

    
    function getCaretCoordinates(element) {
        // Simplified - just return offset from element
        return { top: element.offsetTop + element.offsetHeight, left: element.offsetLeft };
    }
    
    // Close dropdown on click outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.mention-dropdown') && e.target !== textarea) {
            closeMentionDropdown();
        }
    });
    
    // Scroll to comment if URL has hash
    if (window.location.hash && window.location.hash.startsWith('#comment-')) {
        const targetComment = document.querySelector(window.location.hash);
        if (targetComment) {
            targetComment.scrollIntoView({ behavior: 'smooth', block: 'center' });
            targetComment.classList.add('highlight-flash');
            setTimeout(() => targetComment.classList.remove('highlight-flash'), 2000);
        }
    }
});

// Need Part Checkbox Handler (in job detail)
const needPartCheckbox = document.getElementById('needPart');
if (needPartCheckbox && !needPartCheckbox.hasAttribute('data-ajax-bound')) {
    needPartCheckbox.setAttribute('data-ajax-bound', 'true');
    
    needPartCheckbox.addEventListener('change', function(e) {
        const jobId = {{ $job->id }};
        const jobWip = '{{ $job->job_number }}';
        const isChecking = this.checked;
        
        // Only intercept when checking (not unchecking) and job doesn't already need parts
        if (isChecking && !{{ $job->need_part ? 'true' : 'false' }}) {
            e.preventDefault();
            
            // Simple confirmation - RQ is entered in Part Tracking Kanban
            if (!confirm(`Mark job ${jobWip} as "Needs Parts"?\n\nThe job will appear in Part Tracking Kanban where you can open the RQ.`)) {
                this.checked = false;
                return;
            }
            
            // Disable checkbox during request
            this.disabled = true;
            
            fetch(`/jobs/${jobId}/need-part`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ need_part: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    this.checked = false;
                    this.disabled = false;
                    alert('Error: ' + (data.message || 'Failed to update'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                this.checked = false;
                this.disabled = false;
                alert('Failed to update. Please try again.');
            });
        }
    });
}
</script>
@endpush
@endsection


