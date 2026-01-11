{{-- Widget: Needs Parts List --}}
@props(['needsPartsJobs' => collect()])

<div class="card h-100">
    <div class="card-header-modern">
        <span class="card-header-title">
            <i class="bi bi-tools text-danger"></i>Needs Parts
        </span>
        <a href="{{ route('jobs.index', ['need_part' => 1]) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">View All</a>
    </div>
    <div class="list-group list-group-flush">
        @forelse($needsPartsJobs as $job)
        <a href="{{ route('jobs.show', $job) }}" class="list-group-item list-group-item-action py-3">
            <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                <h6 class="mb-0 fw-bold">{{ $job->plate_number }}</h6>
                <small class="text-muted">{{ $job->job_number }}</small>
            </div>
            <p class="mb-1 small text-muted text-truncate">{{ $job->latest_remark }}</p>
            <small class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Parts Required</small>
        </a>
        @empty
        <div class="text-center py-5 text-muted">
            <i class="bi bi-check2-all display-4 d-block mb-3 opacity-25"></i>
            All clear
        </div>
        @endforelse
    </div>
</div>
