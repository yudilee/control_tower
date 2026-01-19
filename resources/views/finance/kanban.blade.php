@extends('layouts.app')

@section('title', 'Finance Kanban - Invoices')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1>
            <i class="bi bi-kanban me-2"></i>Finance Kanban
        </h1>
        <p class="text-muted mb-0">Track invoice payments (Drag to update status)</p>
    </div>
    <div>
        <a href="{{ route('jobs.kanban') }}" class="btn btn-outline-secondary">
            <i class="bi bi-list-ul me-1"></i>View Job Kanban
        </a>
    </div>
</div>

    <!-- Filter Bar -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('finance.kanban') }}" class="row g-2 align-items-end">
                <!-- Search -->
                <div class="col-md-3">
                    <label class="form-label small mb-1">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" 
                               value="{{ request('search') }}" placeholder="WIP, Invoice#, Plate, Customer...">
                    </div>
                </div>
                
                <!-- Service Advisor -->
                <div class="col-md-2">
                    <label class="form-label small mb-1">Service Advisor</label>
                    <select name="service_advisor" class="form-select form-select-sm">
                        <option value="">All SA</option>
                        @foreach($filterOptions['service_advisors'] ?? [] as $sa)
                            <option value="{{ $sa }}" {{ request('service_advisor') == $sa ? 'selected' : '' }}>{{ $sa }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Foreman -->
                <div class="col-md-2">
                    <label class="form-label small mb-1">Foreman</label>
                    <select name="foreman" class="form-select form-select-sm">
                        <option value="">All Foreman</option>
                        @foreach($filterOptions['foremen'] ?? [] as $fm)
                            <option value="{{ $fm }}" {{ request('foreman') == $fm ? 'selected' : '' }}>{{ $fm }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Date Range -->
                <div class="col-md-2">
                    <label class="form-label small mb-1">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                
                <!-- Buttons -->
                <div class="col-md-1">
                    <div class="btn-group btn-group-sm w-100">
                        <button type="submit" class="btn btn-primary" title="Filter">
                            <i class="bi bi-funnel"></i>
                        </button>
                        <a href="{{ route('finance.kanban') }}" class="btn btn-outline-secondary" title="Reset">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats -->
    @php
        $totalInvoices = collect($invoicesByStatus)->flatten()->count();
        $totalAmount = collect($invoicesByStatus)->flatten()->sum('inv_ppn_meterai');
        $paidAmount = ($invoicesByStatus['paid'] ?? collect())->sum('inv_ppn_meterai');
        $pendingAmount = ($invoicesByStatus['pending'] ?? collect())->sum('inv_ppn_meterai');
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="d-flex align-items-center bg-light rounded p-3">
                <i class="bi bi-receipt fs-4 text-primary me-3"></i>
                <div>
                    <div class="fw-bold">{{ $totalInvoices }}</div>
                    <small class="text-muted">Total Invoices</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center bg-light rounded p-3">
                <i class="bi bi-currency-exchange fs-4 text-secondary me-3"></i>
                <div>
                    <div class="fw-bold">Rp {{ number_format($totalAmount, 0, ',', '.') }}</div>
                    <small class="text-muted">Total Value</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center bg-light rounded p-3">
                <i class="bi bi-hourglass fs-4 text-warning me-3"></i>
                <div>
                    <div class="fw-bold">Rp {{ number_format($pendingAmount, 0, ',', '.') }}</div>
                    <small class="text-muted">Pending Payment</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="d-flex align-items-center bg-light rounded p-3">
                <i class="bi bi-check-circle fs-4 text-success me-3"></i>
                <div>
                    <div class="fw-bold">Rp {{ number_format($paidAmount, 0, ',', '.') }}</div>
                    <small class="text-muted">Paid</small>
                </div>
            </div>
        </div>
    </div>

<!-- Kanban Board -->
@php
    $columns = [
        'draft' => ['label' => 'Draft', 'color' => 'secondary', 'icon' => 'file-earmark'],
        'pending' => ['label' => 'Pending Payment', 'color' => 'warning', 'icon' => 'hourglass'],
        'partially_paid' => ['label' => 'Partially Paid', 'color' => 'info', 'icon' => 'pie-chart'],
        'paid' => ['label' => 'Paid', 'color' => 'success', 'icon' => 'check-circle'],
        'credit_note' => ['label' => 'Credit Notes', 'color' => 'danger', 'icon' => 'dash-circle'],
    ];
@endphp
<div class="kanban-container">
    @foreach($columns as $statusKey => $col)
        <div class="kanban-column" data-color="{{ $col['color'] }}">
            <div class="kanban-header">
                <i class="bi bi-{{ $col['icon'] }} text-{{ $col['color'] }}"></i>
                <span>{{ $col['label'] }}</span>
                <span class="badge bg-{{ $col['color'] }} ms-auto">
                    {{ isset($invoicesByStatus[$statusKey]) ? $invoicesByStatus[$statusKey]->count() : 0 }}
                </span>
            </div>
            <div class="kanban-search px-2 py-1">
                <input type="text" class="form-control form-control-sm column-search" 
                       placeholder="Search..." 
                       data-column="{{ $statusKey }}"
                       onkeyup="filterColumn(this)">
            </div>
            <div class="kanban-body" data-status="{{ $statusKey }}">
                @forelse($invoicesByStatus[$statusKey] ?? [] as $invoice)
                    <div class="kanban-card {{ $statusKey === 'credit_note' ? 'border-start border-danger border-3' : '' }}" 
                         data-invoice-id="{{ $invoice->id }}"
                         data-amount="{{ $invoice->inv_ppn_meterai }}"
                         data-paid="{{ $invoice->paid_amount }}"
                         draggable="{{ $statusKey !== 'credit_note' ? 'true' : 'false' }}">
                        <!-- Invoice Number -->
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0 fw-bold text-primary">
                                {{ $invoice->invoice_number ?: 'No Invoice #' }}
                            </h6>
                            <span class="badge bg-{{ $invoice->isCreditNote() ? 'danger' : 'secondary' }}">
                                {{ $invoice->type_sale_label }}
                            </span>
                        </div>
                        
                        <!-- Job Reference -->
                        @if($invoice->job)
                        <div class="small text-muted mb-2">
                            <a href="{{ route('jobs.show', $invoice->job->id) }}" class="text-decoration-none">
                                <i class="bi bi-briefcase me-1"></i>{{ $invoice->job->job_number }}
                            </a>
                            <span class="ms-1">{{ Str::limit($invoice->job->customer_name, 20) }}</span>
                        </div>
                        @endif
                        
                        <!-- Amount -->
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold {{ $invoice->isCreditNote() ? 'text-danger' : 'text-dark' }}">
                                    {{ $invoice->isCreditNote() ? '-' : '' }}Rp {{ number_format($invoice->inv_ppn_meterai, 0, ',', '.') }}
                                </span>
                                @if($invoice->status === 'partially_paid' && $invoice->paid_amount > 0)
                                    <br>
                                    <small class="text-success">
                                        Paid: Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}
                                    </small>
                                @endif
                            </div>
                            @if($invoice->invoice_date)
                            <small class="text-muted">
                                {{ $invoice->invoice_date->format('d M') }}
                            </small>
                            @endif
                        </div>
                        
                        <!-- Payment Progress for partially paid -->
                        @if($invoice->status === 'partially_paid' && $invoice->inv_ppn_meterai > 0)
                            @php $pct = min(100, ($invoice->paid_amount / $invoice->inv_ppn_meterai) * 100); @endphp
                            <div class="progress mt-2" style="height: 4px;">
                                <div class="progress-bar bg-success" style="width: {{ $pct }}%"></div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-muted py-4 opacity-50">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="small mt-2 mb-0">No invoices</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
    
    <!-- Status Change Modal (with Remark) -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Invoice Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="statusForm">
                        <input type="hidden" name="invoice_id" id="sm_invoice_id">
                        <input type="hidden" name="status" id="sm_status">
                        
                        <p>Move invoice to: <strong id="sm_status_display" class="text-primary"></strong></p>
                        
                        <div class="mb-3">
                            <label class="form-label">Comment (Required)</label>
                            <textarea class="form-control" name="remark" id="sm_remark" rows="3" 
                                      placeholder="Enter reason or notes for this change..." required minlength="3"></textarea>
                            <div class="form-text">This will be logged in the Job's activity timeline</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveStatus">
                        <i class="bi bi-check me-1"></i> Confirm & Update
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success bg-opacity-10">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <input type="hidden" name="invoice_id" id="pm_invoice_id">
                        
                        <div class="alert alert-info">
                            <strong>Invoice Total:</strong> Rp <span id="pm_total">0</span><br>
                            <strong>Already Paid:</strong> Rp <span id="pm_paid">0</span><br>
                            <strong>Remaining:</strong> Rp <span id="pm_remaining">0</span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="amount" id="pm_amount" 
                                       required min="1" step="1" placeholder="Enter amount">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Required)</label>
                            <textarea class="form-control" name="remark" id="pm_remark" rows="2" 
                                      placeholder="Payment reference, bank transfer info, etc." required minlength="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="savePayment">
                        <i class="bi bi-check me-1"></i> Record Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.kanban-container {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    padding-bottom: 1rem;
    min-height: 70vh;
}
.kanban-column {
    flex: 0 0 280px;
    background: var(--bs-tertiary-bg);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 250px);
}
.kanban-header {
    padding: 0.75rem 1rem;
    border-radius: 12px 12px 0 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: sticky;
    top: 0;
    z-index: 10;
}
.kanban-header .badge {
    font-size: 0.75rem;
}
.kanban-body {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem;
    min-height: 200px;
}
.kanban-card {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    cursor: grab;
    transition: transform 0.15s, box-shadow 0.15s;
}
.kanban-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.kanban-card:active {
    cursor: grabbing;
}
.kanban-card.dragging {
    opacity: 0.5;
    transform: rotate(2deg);
}
.kanban-column.drag-over {
    background: rgba(var(--bs-primary-rgb), 0.1) !important;
    border: 2px dashed var(--bs-primary);
}

/* Sortable ghost */
.sortable-ghost {
    opacity: 0.4;
    background: var(--bs-primary-bg-subtle);
}
.sortable-chosen {
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

/* Color-coded column backgrounds */
.kanban-column[data-color="secondary"] .kanban-header { background: linear-gradient(135deg, #6c757d20, #6c757d40); }
.kanban-column[data-color="warning"] .kanban-header { background: linear-gradient(135deg, #ffc10720, #ffc10740); }
.kanban-column[data-color="info"] .kanban-header { background: linear-gradient(135deg, #0dcaf020, #0dcaf040); }
.kanban-column[data-color="success"] .kanban-header { background: linear-gradient(135deg, #19875420, #19875440); }
.kanban-column[data-color="danger"] .kanban-header { background: linear-gradient(135deg, #dc354520, #dc354540); }

/* Search input in column */
.kanban-search {
    background: var(--bs-tertiary-bg);
    border-bottom: 1px solid var(--bs-border-color);
}
.kanban-search input {
    font-size: 0.8rem;
    background: var(--bs-body-bg);
}
.kanban-card.hidden {
    display: none !important;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.kanban-card[draggable="true"]');
    const columns = document.querySelectorAll('.kanban-column');
    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    let draggedCard = null;
    let originalParent = null;

    // Status labels
    const statusLabels = {
        'draft': 'Draft',
        'pending': 'Pending Payment',
        'partially_paid': 'Partially Paid',
        'paid': 'Paid'
    };

    cards.forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
        
        // Double-click to record payment
        card.addEventListener('dblclick', function() {
            const invoiceId = this.dataset.invoiceId;
            const total = parseFloat(this.dataset.amount) || 0;
            const paid = parseFloat(this.dataset.paid) || 0;
            showPaymentModal(invoiceId, total, paid);
        });
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
        e.dataTransfer.setData('text/plain', this.dataset.invoiceId);
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
        
        // Prevent drop in same column or credit_note column
        if (originalParent === this || this.dataset.status === 'credit_note') return;
        
        const invoiceId = e.dataTransfer.getData('text/plain');
        const newStatus = this.dataset.status;

        // Show status modal
        document.getElementById('sm_invoice_id').value = invoiceId;
        document.getElementById('sm_status').value = newStatus;
        document.getElementById('sm_status_display').textContent = statusLabels[newStatus] || newStatus;
        document.getElementById('sm_remark').value = '';
        
        statusModal.show();
    }

    function showPaymentModal(invoiceId, total, paid) {
        document.getElementById('pm_invoice_id').value = invoiceId;
        document.getElementById('pm_total').textContent = formatNumber(total);
        document.getElementById('pm_paid').textContent = formatNumber(paid);
        document.getElementById('pm_remaining').textContent = formatNumber(total - paid);
        document.getElementById('pm_amount').value = '';
        document.getElementById('pm_amount').max = total - paid;
        document.getElementById('pm_remark').value = '';
        
        paymentModal.show();
    }

    function formatNumber(num) {
        return new Intl.NumberFormat('id-ID').format(num);
    }

    // Save status change
    document.getElementById('saveStatus').addEventListener('click', function() {
        const form = document.getElementById('statusForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const invoiceId = document.getElementById('sm_invoice_id').value;
        const status = document.getElementById('sm_status').value;
        const remark = document.getElementById('sm_remark').value;
        
        fetch(`/finance/invoices/${invoiceId}/status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ status, remark })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusModal.hide();
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error updating status');
        });
    });

    // Save payment
    document.getElementById('savePayment').addEventListener('click', function() {
        const form = document.getElementById('paymentForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const invoiceId = document.getElementById('pm_invoice_id').value;
        const amount = document.getElementById('pm_amount').value;
        const remark = document.getElementById('pm_remark').value;
        
        fetch(`/finance/invoices/${invoiceId}/payment`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ amount, remark })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                paymentModal.hide();
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error recording payment');
        });
    });
});

// Filter cards in a column by search text
function filterColumn(input) {
    const search = input.value.toLowerCase().trim();
    const columnId = input.dataset.column;
    const column = document.querySelector(`.kanban-body[data-status="${columnId}"]`);
    
    if (!column) return;
    
    const cards = column.querySelectorAll(".kanban-card");
    let visibleCount = 0;
    
    cards.forEach(card => {
        const invoiceNumber = card.querySelector("h6")?.textContent?.toLowerCase() || "";
        const jobNumber = card.textContent?.toLowerCase() || "";
        
        const matches = invoiceNumber.includes(search) || jobNumber.includes(search);
        
        if (matches || search === "") {
            card.classList.remove("hidden");
            visibleCount++;
        } else {
            card.classList.add("hidden");
        }
    });
    
    // Update visible count in badge
    const badge = column.closest(".kanban-column").querySelector(".badge");
    if (badge && search !== "") {
        badge.textContent = visibleCount;
    } else if (badge) {
        // Reset to original count when search is cleared
        const originalCount = column.querySelectorAll(".kanban-card").length;
        badge.textContent = originalCount;
    }
}
</script>
@endpush
@endsection
