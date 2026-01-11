{{-- Widget: Saved Filters (Quick Access) --}}
@props(['savedFilters' => collect()])

<div class="card h-100">
    <div class="card-header-modern">
        <span class="card-header-title">
            <i class="bi bi-bookmark-fill text-primary"></i>My Saved Filters
        </span>
        <a href="{{ route('reports.builder') }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Build Report</a>
    </div>
    <div class="list-group list-group-flush">
        @forelse($savedFilters as $filter)
        <a href="{{ route('reports.builder', ['load' => $filter->id]) }}" class="list-group-item list-group-item-action py-3">
            <div class="d-flex w-100 justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1 fw-semibold">{{ $filter->name }}</h6>
                    <small class="text-muted">{{ $filter->description ?? 'No description' }}</small>
                </div>
                <i class="bi bi-chevron-right text-muted"></i>
            </div>
        </a>
        @empty
        <div class="text-center py-5 text-muted">
            <i class="bi bi-bookmark display-4 d-block mb-3 opacity-25"></i>
            <p class="mb-2">No saved filters yet</p>
            <a href="{{ route('reports.builder') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus me-1"></i>Create First Filter
            </a>
        </div>
        @endforelse
    </div>
</div>
