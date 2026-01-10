@extends('layouts.app')

@section('title', 'Finance Process - Kanban')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-cash-coin me-2"></i>Finance Process
            </h1>
            <p class="text-muted mb-0">Manage invoicing and payments (Steps 10-13)</p>
        </div>
        <div>
            <!-- Filters if needed -->
        </div>
    </div>

    <!-- Kanban Board -->
    <div class="kanban-board">
        <div class="row flex-nowrap overflow-auto pb-3" style="min-height: 500px;">
            @foreach($statuses as $status)
                <div class="col-kanban" style="min-width: 300px; max-width: 350px;">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between py-3">
                            <div class="d-flex align-items-center">
                                <span class="badge rounded-pill me-2 bg-primary">
                                    {{ $jobsByStatus[$status]->count() }}
                                </span>
                                <span class="fw-semibold">{{ $status }}</span>
                            </div>
                        </div>
                        <div class="card-body kanban-column p-2 bg-light" 
                             data-status="{{ $status }}"
                             style="min-height: 400px; border-radius: 0.5rem;">
                             
                            @forelse($jobsByStatus[$status] as $job)
                                <div class="kanban-card card border-0 shadow-sm mb-2 cursor-grab" 
                                     data-job-id="{{ $job->id }}"
                                     draggable="true">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0 fw-semibold">
                                                <a href="{{ route('jobs.show', $job->id) }}" class="text-decoration-none text-dark">
                                                    {{ $job->job_number }}
                                                </a>
                                            </h6>
                                            <span class="badge bg-secondary">{{ $job->plate_number }}</span>
                                        </div>
                                        <div class="small text-muted mb-2">
                                            <i class="bi bi-person me-1"></i>{{ $job->customer_name }}
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                            <small class="text-muted">
                                                {{ $job->updated_at->diffForHumans() }}
                                            </small>
                                            <small class="fw-bold">
                                                {{ number_format($job->total_sales, 0, ',', '.') }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-4 opacity-50">
                                    <i class="bi bi-inbox fs-1"></i>
                                    <p class="small mt-2 mb-0">No jobs</p>
                                </div>
                            @endforelse
                            
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    
    <!-- Remark Modal -->
    <div class="modal fade" id="remarkModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Status Change</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="remarkForm">
                        <input type="hidden" name="job_id" id="rm_job_id">
                        <input type="hidden" name="status" id="rm_status">
                        
                        <p>Change status to: <strong id="rm_status_display" class="text-primary"></strong></p>
                        
                        <div class="mb-3">
                            <label class="form-label">Remark (Mandatory)</label>
                            <textarea class="form-control" name="remark" id="rm_remark" rows="3" placeholder="Enter reason or details..." required minlength="3"></textarea>
                            <div class="invalid-feedback">Remark is required (min 3 chars).</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveRemark">Confirm & Move</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.kanban-board { overflow-x: auto; }
.kanban-card { transition: transform 0.2s, box-shadow 0.2s; }
.kanban-card:hover { transform: translateY(-2px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important; }
.kanban-card.dragging { opacity: 0.5; transform: rotate(2deg); }
.kanban-column.drag-over { background: rgba(var(--bs-primary-rgb), 0.1) !important; border: 2px dashed var(--bs-primary); }
.cursor-grab { cursor: grab; }
.cursor-grab:active { cursor: grabbing; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');
    const remarkModal = new bootstrap.Modal(document.getElementById('remarkModal'));
    let draggedCard = null;
    let originalParent = null;

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
        e.dataTransfer.setData('text/plain', this.dataset.jobId);
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
        
        // Prevent drop in same column
        if (originalParent === this) return;
        
        const jobId = e.dataTransfer.getData('text/plain');
        const newStatus = this.dataset.status;

        // Show Modal
        document.getElementById('rm_job_id').value = jobId;
        document.getElementById('rm_status').value = newStatus;
        document.getElementById('rm_status_display').textContent = newStatus;
        document.getElementById('rm_remark').value = ''; // Reset remark
        
        remarkModal.show();
    }
    
    document.getElementById('saveRemark').addEventListener('click', function() {
        const form = document.getElementById('remarkForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const jobId = document.getElementById('rm_job_id').value;
        const status = document.getElementById('rm_status').value;
        const remark = document.getElementById('rm_remark').value;
        
        // AJAX Submit
        fetch(`/finance/jobs/${jobId}/status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ work_status: status, remark: remark })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                remarkModal.hide();
                location.reload();
            } else {
                alert('Update failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error updating status');
        });
    });
});
</script>
@endpush
@endsection
