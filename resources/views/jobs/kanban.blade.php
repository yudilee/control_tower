@extends('layouts.app')

@section('title', 'Kanban Board')

@section('content')
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
.kanban-card .plate {
    font-weight: 700;
    color: var(--bs-primary);
    font-size: 0.95rem;
}
.kanban-card .wip {
    font-size: 0.75rem;
    color: var(--bs-secondary);
}
.kanban-card .customer {
    font-size: 0.8rem;
    color: var(--bs-body-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kanban-card .meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
    font-size: 0.75rem;
}
.kanban-card .sa-badge {
    background: var(--bs-light);
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
}
.kanban-card .age {
    color: var(--bs-secondary);
}
.kanban-card .age.urgent { color: var(--bs-danger); font-weight: 600; }
.kanban-card .age.warning { color: var(--bs-warning); }

/* Sortable ghost */
.sortable-ghost {
    opacity: 0.4;
    background: var(--bs-primary-bg-subtle);
}
.sortable-chosen {
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

/* Color-coded columns */
.kanban-column[data-color="secondary"] .kanban-header { background: linear-gradient(135deg, #6c757d20, #6c757d40); }
.kanban-column[data-color="primary"] .kanban-header { background: linear-gradient(135deg, #0d6efd20, #0d6efd40); }
.kanban-column[data-color="warning"] .kanban-header { background: linear-gradient(135deg, #ffc10720, #ffc10740); }
.kanban-column[data-color="info"] .kanban-header { background: linear-gradient(135deg, #0dcaf020, #0dcaf040); }
.kanban-column[data-color="success"] .kanban-header { background: linear-gradient(135deg, #19875420, #19875440); }
.kanban-column[data-color="danger"] .kanban-header { background: linear-gradient(135deg, #dc354520, #dc354540); }
</style>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-kanban me-2"></i>Kanban Board</h1>
        <p class="text-muted mb-0">Drag jobs between columns to update work status</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('jobs.index', ['status' => 'uninvoiced']) }}" class="btn btn-outline-secondary">
            <i class="bi bi-list-ul me-1"></i>List View
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <select name="franchise" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Franchises</option>
                    @foreach($filterOptions['franchise'] as $f)
                    <option value="{{ $f }}" {{ request('franchise') == $f ? 'selected' : '' }}>{{ $f }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select name="service_advisor" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Service Advisors</option>
                    @foreach($filterOptions['service_advisor'] as $sa)
                    <option value="{{ $sa }}" {{ request('service_advisor') == $sa ? 'selected' : '' }}>{{ $sa }}</option>
                    @endforeach
                </select>
            </div>
            @if(request()->hasAny(['franchise', 'service_advisor']))
            <div class="col-auto">
                <a href="{{ route('jobs.kanban') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
            @endif
        </form>
    </div>
</div>

<!-- Kanban Board -->
<div class="kanban-container">
    @foreach($workStatuses as $status)
    <div class="kanban-column" data-status="{{ $status->value }}" data-color="{{ $status->color }}">
        <div class="kanban-header">
            @if($status->icon)
            <i class="bi bi-{{ $status->icon }} text-{{ $status->color }}"></i>
            @endif
            <span>{{ $status->label }}</span>
            @php $total = $totalsByStatus[$status->value] ?? 0; @endphp
            <span class="badge bg-{{ $status->color }} ms-auto" title="{{ $total }} jobs total">
                {{ $total }}@if($total > 100)+@endif
            </span>
        </div>
        <div class="kanban-body" id="column-{{ $status->value }}">
            @forelse($jobsByStatus[$status->value] as $job)
            <div class="kanban-card" data-job-id="{{ $job->id }}">
                <div class="d-flex justify-content-between align-items-start">
                    <a href="{{ route('jobs.show', $job) }}" class="plate text-decoration-none">{{ $job->plate_number }}</a>
                    <span class="wip">{{ $job->job_number }}</span>
                </div>
                <div class="customer" title="{{ $job->customer_name }}">{{ Str::limit($job->customer_name, 30) }}</div>
                <div class="meta">
                    <span class="sa-badge">{{ $job->service_advisor ?? 'N/A' }}</span>
                    @php
                        $days = $job->job_date ? now()->diffInDays($job->job_date) : 0;
                        $ageClass = $days > 14 ? 'urgent' : ($days > 7 ? 'warning' : '');
                    @endphp
                    <span class="age {{ $ageClass }}">{{ $days }}d</span>
                </div>
            </div>
            @empty
            <div class="text-center text-muted py-4 small">
                <i class="bi bi-inbox opacity-50 d-block mb-1" style="font-size: 1.5rem;"></i>
                No jobs
            </div>
            @endforelse
        </div>
    </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const columns = document.querySelectorAll('.kanban-body');
    
    columns.forEach(column => {
        new Sortable(column, {
            group: 'kanban',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: function(evt) {
                const jobId = evt.item.dataset.jobId;
                const newStatus = evt.to.id.replace('column-', '');
                
                // AJAX update
                fetch(`/jobs/${jobId}/work-status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ work_status: newStatus })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Update column counts
                        updateColumnCounts();
                        showToast(data.message, 'success');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    showToast('Failed to update status', 'danger');
                });
            }
        });
    });
    
    function updateColumnCounts() {
        document.querySelectorAll('.kanban-column').forEach(col => {
            const count = col.querySelector('.kanban-body').querySelectorAll('.kanban-card').length;
            col.querySelector('.badge').textContent = count;
        });
    }
    
    function showToast(message, type = 'info') {
        const toastHtml = `
            <div class="toast align-items-center text-bg-${type} border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(container);
        }
        container.insertAdjacentHTML('beforeend', toastHtml);
        setTimeout(() => container.querySelector('.toast')?.remove(), 3000);
    }
});
</script>
@endpush
