{{-- Widget: Stat Cards - Overview Statistics --}}
@props(['stats'])

<div class="row g-4 mb-5">
    <div class="col-md-3">
        <a href="{{ route('jobs.index', ['status' => 'uninvoiced']) }}" class="text-decoration-none">
            <div class="stat-card-modern">
                <p class="stat-value" id="stat-uninvoiced">{{ $stats['uninvoiced'] }}</p>
                <p class="stat-label mb-0"><i class="bi bi-clock me-1"></i>Uninvoiced Jobs</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('jobs.index', ['need_part' => 1, 'status' => 'uninvoiced']) }}" class="text-decoration-none">
            <div class="stat-card-modern warning">
                <p class="stat-value" id="stat-needs-parts">{{ $stats['needs_parts'] }}</p>
                <p class="stat-label mb-0"><i class="bi bi-gear me-1"></i>Needs Parts</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('jobs.index', ['status' => 'invoiced']) }}" class="text-decoration-none">
            <div class="stat-card-modern success">
                <p class="stat-value" id="stat-invoiced">{{ $stats['invoiced'] }}</p>
                <p class="stat-label mb-0"><i class="bi bi-check-circle me-1"></i>Invoiced Jobs</p>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('vehicles.index', ['in_workshop' => 1]) }}" class="text-decoration-none">
            <div class="stat-card-modern info">
                <p class="stat-value" id="stat-in-workshop">{{ $stats['vehicles_in_workshop'] }}</p>
                <p class="stat-label mb-0"><i class="bi bi-car-front me-1"></i>In Workshop</p>
            </div>
        </a>
    </div>
</div>
