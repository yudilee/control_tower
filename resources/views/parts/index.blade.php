@extends('layouts.app')

@section('title', 'Part Orders')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-box-seam me-2"></i>Part Orders (RQ Tracking)
            </h1>
            <p class="text-muted mb-0">List of all part orders / requisitions</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('parts.kanban') }}" class="btn btn-primary">
                <i class="bi bi-kanban me-1"></i>Kanban View
            </a>
        </div>
    </div>

    <!-- Workflow Info -->
    <div class="alert alert-info mb-4">
        <h6 class="alert-heading mb-2"><i class="bi bi-info-circle me-1"></i>Part Tracking Workflow</h6>
        <p class="mb-0 small">
            <strong>Pending</strong> (Job needs parts) → 
            <strong>Buka RQ</strong> (RQ opened) → 
            <strong>Ordered</strong> (Order placed) → 
            <strong>Confirmed</strong> → 
            <strong>Shipped</strong> → 
            <strong>Received</strong> (Parts arrived, job work_status updates to "6. Parts Datang")
        </p>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" placeholder="Search RQ, order no, job..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $key => $info)
                            @if($key !== 'pending')
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                                {{ $info['label'] }}
                            </option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="filter" class="form-select">
                        <option value="">All Orders</option>
                        <option value="due_soon" {{ request('filter') === 'due_soon' ? 'selected' : '' }}>Due Soon (7 days)</option>
                        <option value="overdue" {{ request('filter') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                </div>
                @if(request()->hasAny(['search', 'status', 'filter']))
                <div class="col-md-2">
                    <a href="{{ route('part-orders.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </a>
                </div>
                @endif
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Job</th>
                        <th>RQ Number</th>
                        <th>Order Number</th>
                        <th>Order Date</th>
                        <th>Expected Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($partOrders as $order)
                    <tr>
                        <td>
                            <a href="{{ route('jobs.show', $order->job_id) }}" class="text-decoration-none fw-semibold">
                                {{ $order->job->job_number ?? 'N/A' }}
                            </a>
                            <br>
                            <small class="text-muted">{{ $order->job->plate_number ?? '' }}</small>
                        </td>
                        <td>
                            @if($order->rq)
                                <span class="badge bg-info text-dark">{{ $order->rq }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($order->no_order_part)
                                <span class="fw-semibold">{{ $order->no_order_part }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ $order->order_date?->format('d M Y') ?? '-' }}</td>
                        <td>
                            <div>{{ $order->expected_date?->format('d M Y') ?? '-' }}</div>
                            @if($order->is_overdue)
                                <span class="badge bg-danger">{{ abs($order->days_until_expected) }} days overdue</span>
                            @elseif($order->is_due_soon)
                                <span class="badge bg-warning text-dark">{{ $order->days_until_expected }} days left</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge" style="background-color: {{ $order->status_color }}">
                                {{ $order->status_label }}
                            </span>
                        </td>
                        <td>
                            @if($order->notes)
                                <span title="{{ $order->notes }}">{{ Str::limit($order->notes, 30) }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('part-orders.edit', $order) }}" class="btn btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('part-orders.destroy', $order) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this part order?')">
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
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                                No part orders found
                                <p class="small mt-2">Use the <a href="{{ route('parts.kanban') }}">Kanban view</a> to create RQs from jobs</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($partOrders->hasPages())
        <div class="card-footer bg-transparent">
            {{ $partOrders->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
