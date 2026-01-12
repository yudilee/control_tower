@extends('layouts.app')

@section('title', 'Parts Tracking - Kanban')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-kanban me-2"></i>Parts Tracking
            </h1>
            <p class="text-muted mb-0">Drag and drop to update status (1-step at a time)</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('part-orders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-list me-1"></i>List View
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-primary bg-opacity-10">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-25 p-3 me-3">
                        <i class="bi bi-box-seam fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="h3 mb-0">{{ $summary['pending'] }}</div>
                        <div class="text-muted small">Jobs Need Parts</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-warning bg-opacity-10">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-25 p-3 me-3">
                        <i class="bi bi-clock-history fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="h3 mb-0">{{ $summary['due_soon'] }}</div>
                        <div class="text-muted small">Due Within 7 Days</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-danger bg-opacity-10">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle bg-danger bg-opacity-25 p-3 me-3">
                        <i class="bi bi-exclamation-triangle fs-4 text-danger"></i>
                    </div>
                    <div>
                        <div class="h3 mb-0">{{ $summary['overdue'] }}</div>
                        <div class="text-muted small">Overdue</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search job/RQ/order..." 
                           value="{{ request('search') }}"
                           style="width: 160px;">
                </div>
                <div class="col-auto">
                    <select name="service_advisor" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All SA</option>
                        @foreach($filterOptions['service_advisors'] as $sa)
                        <option value="{{ $sa }}" {{ request('service_advisor') == $sa ? 'selected' : '' }}>{{ $sa }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="foreman" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Foremen</option>
                        @foreach($filterOptions['foremen'] as $fm)
                        <option value="{{ $fm }}" {{ request('foreman') == $fm ? 'selected' : '' }}>{{ $fm }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <input type="date" name="date_from" class="form-control form-control-sm" 
                           value="{{ request('date_from') }}" 
                           title="Order Date From"
                           onchange="this.form.submit()">
                </div>
                <div class="col-auto">
                    <input type="date" name="date_to" class="form-control form-control-sm" 
                           value="{{ request('date_to') }}" 
                           title="Order Date To"
                           onchange="this.form.submit()">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                @if(request()->hasAny(['search', 'service_advisor', 'foreman', 'date_from', 'date_to']))
                <div class="col-auto">
                    <a href="{{ route('part-orders.kanban') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                </div>
                @endif
            </form>
        </div>
    </div>

    <!-- Kanban Board -->
    <div class="kanban-board">
        <div class="row flex-nowrap overflow-auto pb-3" style="min-height: 500px;">
            
            {{-- PENDING COLUMN - Shows JOBS (not PartOrders) --}}
            <div class="col-kanban" style="min-width: 280px; max-width: 320px;">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between py-3">
                        <div class="d-flex align-items-center">
                            <span class="badge rounded-pill me-2" style="background-color: #f59e0b">
                                {{ $pendingJobs->count() }}
                            </span>
                            <span class="fw-semibold">Pending</span>
                        </div>
                        <i class="bi bi-hourglass-split text-muted"></i>
                    </div>
                    <div class="card-body kanban-column p-2" 
                         data-status="pending"
                         data-is-job-column="true"
                         style="min-height: 400px; background: var(--bs-light); border-radius: 0.5rem;">
                        @forelse($pendingJobs as $job)
                            <div class="kanban-card kanban-job-card card border-0 shadow-sm mb-2 cursor-grab" 
                                 data-job-id="{{ $job->id }}"
                                 data-job-number="{{ $job->job_number }}"
                                 draggable="true">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 fw-semibold text-primary">{{ $job->job_number }}</h6>
                                        <span class="badge bg-warning text-dark">Job</span>
                                    </div>
                                    <div class="small text-muted mb-1">
                                        <i class="bi bi-car-front me-1"></i>{{ $job->plate_number ?: 'No Plate' }}
                                    </div>
                                    <div class="small text-muted mb-1">
                                        <i class="bi bi-person me-1"></i>{{ Str::limit($job->customer_name, 20) ?: '-' }}
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                        <small class="text-muted">
                                            <i class="bi bi-headset me-1"></i>{{ $job->service_advisor ?: '-' }}
                                        </small>
                                        <small class="text-muted">
                                            {{ $job->foreman ?: '-' }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 opacity-25"></i>
                                <p class="small mt-2 mb-0">No jobs need parts</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
            
            {{-- OTHER COLUMNS - Show PartOrders --}}
            @php
                $displayStatuses = ['buka_rq', 'ordered', 'confirmed', 'shipped', 'received'];
            @endphp
            @foreach($displayStatuses as $statusKey)
                @php $statusInfo = $statuses[$statusKey]; @endphp
                <div class="col-kanban" style="min-width: 280px; max-width: 320px;">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between py-3">
                            <div class="d-flex align-items-center">
                                <span class="badge rounded-pill me-2" style="background-color: {{ $statusInfo['color'] }}">
                                    {{ count($ordersByStatus[$statusKey] ?? []) }}
                                </span>
                                <span class="fw-semibold">{{ $statusInfo['label'] }}</span>
                            </div>
                            <i class="bi {{ $statusInfo['icon'] }} text-muted"></i>
                        </div>
                        <div class="card-body kanban-column p-2" 
                             data-status="{{ $statusKey }}"
                             style="min-height: 400px; background: var(--bs-light); border-radius: 0.5rem;">
                            @forelse($ordersByStatus[$statusKey] ?? [] as $order)
                                <div class="kanban-card kanban-order-card card border-0 shadow-sm mb-2 cursor-grab" 
                                     data-order-id="{{ $order->id }}"
                                     data-current-status="{{ $order->status }}"
                                     draggable="true">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0 fw-semibold">
                                                <a href="{{ route('jobs.show', $order->job_id) }}" class="text-decoration-none">
                                                    {{ $order->job->job_number ?? 'N/A' }}
                                                </a>
                                            </h6>
                                            @if($order->is_overdue)
                                                <span class="badge bg-danger">Overdue</span>
                                            @elseif($order->is_due_soon)
                                                <span class="badge bg-warning text-dark">Due Soon</span>
                                            @endif
                                        </div>
                                        <div class="small mb-2">
                                            <span class="badge bg-info text-dark">
                                                <i class="bi bi-receipt me-1"></i>RQ: {{ $order->rq ?: '-' }}
                                            </span>
                                        </div>
                                        @if($order->no_order_part)
                                            <div class="small text-muted mb-1">
                                                <i class="bi bi-cart me-1"></i>Order: {{ $order->no_order_part }}
                                            </div>
                                        @endif
                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar me-1"></i>
                                                {{ $order->expected_date?->format('d M') ?: '-' }}
                                            </small>
                                            <small class="text-muted">
                                                {{ $order->job->plate_number ?: '-' }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-1 opacity-25"></i>
                                    <p class="small mt-2 mb-0">No orders</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- RQ Modal (Pending → Buka RQ) -->
<div class="modal fade" id="rqModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-file-plus me-2"></i>Open RQ (Buka RQ)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rqForm">
                    <input type="hidden" name="job_id" id="rq_job_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Job Number</label>
                        <div class="form-control-plaintext fw-semibold text-primary" id="rq_job_display"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">RQ Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="rq" id="rq_number" required placeholder="Enter RQ Number">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info text-white" id="saveRq">
                    <i class="bi bi-check-lg me-1"></i>Create RQ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Order Details Modal (Buka RQ → Ordered) -->
<div class="modal fade" id="orderDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cart me-2"></i>Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="orderDetailForm">
                    <input type="hidden" name="order_id" id="od_order_id">
                    <input type="hidden" name="status" value="ordered">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">RQ Number</label>
                        <div class="form-control-plaintext" id="od_rq_display"></div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">No. Order Part <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="no_order_part" id="od_no_order_part" required placeholder="Enter Order Number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Order Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="order_date" id="od_order_date" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="expected_date" id="od_expected_date" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="od_notes" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remark (for Job Activity)</label>
                            <textarea class="form-control" name="remark" rows="2" placeholder="Add a remark about this order..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveOrderDetails">Confirm & Order</button>
            </div>
        </div>
    </div>
</div>

<!-- Remark Modal (For other status transitions) -->
<div class="modal fade" id="remarkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Status Change</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="remarkForm">
                    <input type="hidden" name="order_id" id="rm_order_id">
                    <input type="hidden" name="status" id="rm_status">
                    
                    <p>Move this order to <strong id="rm_status_display"></strong>?</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Remark (Optional)</label>
                        <textarea class="form-control" name="remark" rows="3" placeholder="Add a remark about this status change..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveRemark">Confirm</button>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.kanban-board {
    overflow-x: auto;
}
.kanban-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.kanban-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
}
.kanban-card.dragging {
    opacity: 0.5;
    transform: rotate(3deg);
}
.kanban-column.drag-over {
    background: rgba(var(--bs-primary-rgb), 0.1) !important;
    border: 2px dashed var(--bs-primary);
}
.kanban-column.drag-invalid {
    background: rgba(var(--bs-danger-rgb), 0.1) !important;
    border: 2px dashed var(--bs-danger);
}
.cursor-grab {
    cursor: grab;
}
.cursor-grab:active {
    cursor: grabbing;
}
.kanban-job-card {
    border-left: 4px solid #f59e0b !important;
}
.kanban-order-card {
    border-left: 4px solid #06b6d4 !important;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const columns = document.querySelectorAll('.kanban-column');
    let draggedCard = null;
    let draggedType = null; // 'job' or 'order'
    let originalStatus = null;

    // Modals
    const rqModal = new bootstrap.Modal(document.getElementById('rqModal'));
    const orderDetailModal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    const remarkModal = new bootstrap.Modal(document.getElementById('remarkModal'));
    
    // Allowed transitions (1-step only)
    const allowedTransitions = {
        'pending': ['buka_rq'],
        'buka_rq': ['ordered'],
        'ordered': ['confirmed'],
        'confirmed': ['shipped'],
        'shipped': ['received']
    };
    
    // Status labels
    const statusLabels = {
        'pending': 'Pending',
        'buka_rq': 'Buka RQ',
        'ordered': 'Ordered',
        'confirmed': 'Confirmed',
        'shipped': 'Shipped',
        'received': 'Received'
    };

    // Setup drag for Job cards
    document.querySelectorAll('.kanban-job-card').forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            draggedType = 'job';
            originalStatus = 'pending';
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.jobId);
        });
        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            columns.forEach(col => col.classList.remove('drag-over', 'drag-invalid'));
        });
    });

    // Setup drag for Order cards
    document.querySelectorAll('.kanban-order-card').forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            draggedType = 'order';
            originalStatus = this.dataset.currentStatus;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.orderId);
        });
        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            columns.forEach(col => col.classList.remove('drag-over', 'drag-invalid'));
        });
    });

    // Setup columns
    columns.forEach(column => {
        column.addEventListener('dragover', function(e) {
            e.preventDefault();
            const targetStatus = this.dataset.status;
            const allowed = allowedTransitions[originalStatus] || [];
            
            if (allowed.includes(targetStatus)) {
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('drag-over');
                this.classList.remove('drag-invalid');
            } else {
                e.dataTransfer.dropEffect = 'none';
                this.classList.add('drag-invalid');
                this.classList.remove('drag-over');
            }
        });
        
        column.addEventListener('dragenter', function(e) {
            // Visual feedback handled by dragover
        });
        
        column.addEventListener('dragleave', function() {
            this.classList.remove('drag-over', 'drag-invalid');
        });
        
        column.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over', 'drag-invalid');
            
            const targetStatus = this.dataset.status;
            const allowed = allowedTransitions[originalStatus] || [];
            
            // Validate transition
            if (!allowed.includes(targetStatus)) {
                alert(`Invalid move!\n\nYou can only move 1 step at a time.\n${statusLabels[originalStatus]} → ${statusLabels[targetStatus]} is not allowed.`);
                return;
            }
            
            // Handle based on card type and target
            if (draggedType === 'job' && targetStatus === 'buka_rq') {
                // Job → Buka RQ: Show RQ modal
                showRqModal(draggedCard.dataset.jobId, draggedCard.dataset.jobNumber);
            } else if (draggedType === 'order' && targetStatus === 'ordered') {
                // Buka RQ → Ordered: Show order details modal
                showOrderModal(draggedCard.dataset.orderId, draggedCard);
            } else if (draggedType === 'order') {
                // Other transitions: Show remark modal
                showRemarkModal(draggedCard.dataset.orderId, targetStatus);
            }
        });
    });

    function showRqModal(jobId, jobNumber) {
        document.getElementById('rq_job_id').value = jobId;
        document.getElementById('rq_job_display').textContent = jobNumber;
        document.getElementById('rq_number').value = '';
        rqModal.show();
    }

    function showOrderModal(orderId, cardElement) {
        document.getElementById('od_order_id').value = orderId;
        const rqBadge = cardElement.querySelector('.badge.bg-info');
        document.getElementById('od_rq_display').textContent = rqBadge ? rqBadge.textContent : '-';
        document.getElementById('od_no_order_part').value = '';
        document.getElementById('od_notes').value = '';
        orderDetailModal.show();
    }

    function showRemarkModal(orderId, status) {
        document.getElementById('rm_order_id').value = orderId;
        document.getElementById('rm_status').value = status;
        document.getElementById('rm_status_display').textContent = statusLabels[status] || status;
        remarkModal.show();
    }

    // Save RQ (Pending → Buka RQ)
    document.getElementById('saveRq').addEventListener('click', function() {
        const form = document.getElementById('rqForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        
        fetch('/part-orders/create-from-job', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                rqModal.hide();
                alert(res.message);
                location.reload();
            } else {
                alert('Error: ' + (res.message || 'Failed to create RQ'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error creating RQ');
        });
    });

    // Save Order Details (Buka RQ → Ordered)
    document.getElementById('saveOrderDetails').addEventListener('click', function() {
        const form = document.getElementById('orderDetailForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const orderId = data.order_id;
        
        submitStatusUpdate(orderId, data, orderDetailModal);
    });

    // Save Remark (Other transitions)
    document.getElementById('saveRemark').addEventListener('click', function() {
        const form = document.getElementById('remarkForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const orderId = data.order_id;
        
        submitStatusUpdate(orderId, data, remarkModal);
    });

    function submitStatusUpdate(orderId, data, modalInstance) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        
        fetch(`/part-orders/${orderId}/status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                modalInstance.hide();
                location.reload();
            } else {
                alert('Error: ' + (res.message || 'Update failed'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error updating status');
        });
    }
});
</script>
@endpush
@endsection
