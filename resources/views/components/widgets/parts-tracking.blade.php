{{-- Widget: Parts Tracking Stats --}}
@props(['partsStats'])

@if($partsStats)
<div class="row g-3 mb-4">
    <div class="col-12">
        <h6 class="text-muted mb-3">
            <i class="bi bi-box-seam me-2"></i>Parts Tracking
        </h6>
    </div>
    <div class="col-md-4">
        <a href="{{ route('parts.kanban') }}" class="text-decoration-none">
            <div class="stat-card-modern primary">
                <p class="stat-value">{{ $partsStats['pending'] }}</p>
                <p class="stat-label mb-0"><i class="bi bi-clock-history me-1"></i>Pending Orders</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('part-orders.index', ['filter' => 'due_soon']) }}" class="text-decoration-none">
            <div class="stat-card-modern warning">
                <p class="stat-value">{{ $partsStats['due_soon'] }}</p>
                <p class="stat-label mb-0"><i class="bi bi-exclamation-circle me-1"></i>Due Soon (7 days)</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('part-orders.index', ['filter' => 'overdue']) }}" class="text-decoration-none">
            <div class="stat-card-modern danger">
                <p class="stat-value">{{ $partsStats['overdue'] }}</p>
                <p class="stat-label mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Overdue</p>
            </div>
        </a>
    </div>
</div>
@endif
