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
            <p class="text-muted mb-0">Drag and drop to update status</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('part-orders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-list me-1"></i>List View
            </a>
            <a href="{{ route('part-orders.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Add Part Order
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
                        <div class="text-muted small">Pending Orders</div>
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

    <!-- Kanban Board -->
    <div class="kanban-board">
        <div class="row flex-nowrap overflow-auto pb-3" style="min-height: 500px;">
            @foreach($statuses as $statusKey => $statusInfo)
                @if($statusKey !== 'cancelled')
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
                                <div class="kanban-card card border-0 shadow-sm mb-2 cursor-grab" 
                                     data-order-id="{{ $order->id }}"
                                     draggable="true">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0 fw-semibold">{{ $order->part_name }}</h6>
                                            @if($order->is_overdue)
                                                <span class="badge bg-danger">Overdue</span>
                                            @elseif($order->is_due_soon)
                                                <span class="badge bg-warning text-dark">Due Soon</span>
                                            @endif
                                        </div>
                                        <div class="small text-muted mb-2">
                                            <i class="bi bi-file-text me-1"></i>
                                            <a href="{{ route('jobs.show', $order->job_id) }}" class="text-decoration-none">
                                                {{ $order->job->job_number ?? 'N/A' }}
                                            </a>
                                        </div>
                                        @if($order->part_number)
                                            <div class="small text-muted mb-2">
                                                <i class="bi bi-upc me-1"></i>{{ $order->part_number }}
                                            </div>
                                        @endif
                                        @if($order->rq)
                                            <div class="small text-muted mb-2">
                                                <i class="bi bi-receipt me-1"></i>RQ: {{ $order->rq }}
                                            </div>
                                        @endif
                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar me-1"></i>
                                                {{ $order->expected_date?->format('d M Y') }}
                                            </small>
                                            <small class="text-muted">
                                                Qty: {{ $order->quantity }}
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
                @endif
            @endforeach
        </div>
    </div>
    </div>

    <!-- Modals -->
    <!-- Order Details Modal (For status 'ordered') -->
    <div class="modal fade" id="orderDetailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details (Buka RQ)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="orderDetailForm">
                        <input type="hidden" name="order_id" id="od_order_id">
                        <input type="hidden" name="status" value="ordered">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Part Name</label>
                                <input type="text" class="form-control" name="part_name" id="od_part_name" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Qty</label>
                                <input type="number" class="form-control" name="quantity" id="od_quantity" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">RQ (Requisition)</label>
                                <input type="text" class="form-control" name="rq" id="od_rq" placeholder="Enter RQ Number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Part Number</label>
                                <input type="text" class="form-control" name="part_number" id="od_part_number" placeholder="Enter Part No">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">No. Order Part</label>
                                <input type="text" class="form-control" name="no_order_part" id="od_no_order_part" placeholder="Enter Order No">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Order Date</label>
                                <input type="date" class="form-control" name="order_date" id="od_order_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expected Date</label>
                                <input type="date" class="form-control" name="expected_date" id="od_expected_date" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" id="od_notes" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Remark (for Job Activity)</label>
                                <textarea class="form-control" name="remark" rows="2" placeholder="Add a remark about this status change..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveOrderDetails">Confirm & Update</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Remark Modal (For other statuses) -->
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
                        
                        <p>Are you sure you want to change status to <strong id="rm_status_display"></strong>?</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Remark (Optional)</label>
                            <textarea class="form-control" name="remark" rows="3" placeholder="Add a remark about this status change..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveRemark">Confirm & Update</button>
                </div>
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
.cursor-grab {
    cursor: grab;
}
.cursor-grab:active {
    cursor: grabbing;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');
    let draggedCard = null;
    let originalParent = null;

    // Modals
    const orderDetailModal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    const remarkModal = new bootstrap.Modal(document.getElementById('remarkModal'));

    cards.forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });

    columns.forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('dragenter', handleDragEnter);
        column.addEventListener('dragleave', handleDragLeave);
        column.addEventListener('drop', handleDrop);
    });

    function handleDragStart(e) {
        draggedCard = this;
        originalParent = this.parentNode;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.orderId);
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');
        columns.forEach(col => col.classList.remove('drag-over'));
    }

    function handleDragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; }
    function handleDragEnter(e) { this.classList.add('drag-over'); }
    function handleDragLeave(e) { this.classList.remove('drag-over'); }

    function handleDrop(e) {
        e.preventDefault();
        this.classList.remove('drag-over');
        
        const orderId = e.dataTransfer.getData('text/plain');
        const newStatus = this.dataset.status;
        
        // Prevent drop in same column
        if (originalParent === this) return;

        // Logic based on status
        if (newStatus === 'ordered') {
            // Show Order Details Modal
            showOrderModal(orderId, draggedCard);
        } else {
            // Show Remark Modal for others
            showRemarkModal(orderId, newStatus, draggedCard);
        }
    }

    function showOrderModal(orderId, cardElement) {
        document.getElementById('od_order_id').value = orderId;
        
        // Pre-fill fields from card data attributes if available, or text content
        const partName = cardElement.querySelector('h6').textContent;
        document.getElementById('od_part_name').value = partName;
        
        // Reset other fields
        document.getElementById('od_quantity').value = ''; 
        
        orderDetailModal.show();
        
        // Clear ID on hidden to detect if cancelled
        const modalEl = document.getElementById('orderDetailModal');
        const onHide = function() {
            if (!document.getElementById('od_order_id').value) { 
                // Currently do nothing on cancel, card remains in original spot visually because we prevented appendChild
            }
            modalEl.removeEventListener('hidden.bs.modal', onHide);
        };
        modalEl.addEventListener('hidden.bs.modal', onHide);
    }

    function showRemarkModal(orderId, status, cardElement) {
        document.getElementById('rm_order_id').value = orderId;
        document.getElementById('rm_status').value = status;
        document.getElementById('rm_status_display').textContent = status.charAt(0).toUpperCase() + status.slice(1);
        remarkModal.show();
    }

    // Handle Order Submit
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

    // Handle Remark Submit
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
                // Clear ID to signal success (before hiding)
                if(modalInstance === orderDetailModal) document.getElementById('od_order_id').value = '';
                
                modalInstance.hide();
                location.reload(); 
            } else {
                alert('Update failed: ' + (res.message || 'Unknown error'));
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
