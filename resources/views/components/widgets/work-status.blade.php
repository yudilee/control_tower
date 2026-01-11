{{-- Widget: Work Status Breakdown --}}
@props(['workStatusOptions' => collect(), 'workStatusCounts' => collect()])

@php
    // Get first status value to add NULL counts
    $firstStatusValue = $workStatusOptions->first()?->value;
    
    // Calculate NULL/unmapped count
    $nullCount = $workStatusCounts->get('__null__')?->count ?? 0;
@endphp

<div class="card mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bar-chart me-2"></i>Work Status (Uninvoiced Jobs)</span>
        <a href="{{ route('jobs.index', ['status' => 'uninvoiced']) }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @forelse($workStatusOptions as $option)
            @php
                $count = $workStatusCounts->get($option->value)?->count ?? 0;
                // Add NULL/unmapped count to first option
                if ($option->value === $firstStatusValue) {
                    $count += $nullCount;
                }
            @endphp
            <div class="col-md col-6">
                <a href="{{ route('jobs.index', ['status' => 'uninvoiced', 'filter_work_status' => $option->value]) }}" class="text-decoration-none">
                    <div class="card border-0 h-100 work-status-card" style="background-color: var(--ws-bg-{{ $option->color }}, rgba(108, 117, 125, 0.1));">
                        <div class="card-body py-3 text-center">
                            @if($option->icon)
                            <i class="bi bi-{{ $option->icon }} fs-3 text-{{ $option->color }} d-block mb-2"></i>
                            @endif
                            <h4 class="mb-0 text-{{ $option->color }}">{{ $count }}</h4>
                            <small class="text-body-secondary">{{ $option->label }}</small>
                        </div>
                    </div>
                </a>
            </div>
            @empty
            <div class="col-12 text-center text-muted py-3">
                <i class="bi bi-gear display-4 opacity-25"></i>
                <p class="mb-0 mt-2">No work statuses configured</p>
                @if(auth()->user()->hasRole('admin'))
                <a href="{{ route('admin.dropdowns.index', ['type' => 'work_status']) }}" class="btn btn-primary btn-sm mt-2">
                    <i class="bi bi-plus-lg me-1"></i>Configure Work Statuses
                </a>
                @endif
            </div>
            @endforelse
        </div>
    </div>
</div>
