@extends('layouts.app')

@section('title', 'Customize Dashboard')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-palette me-2"></i>Customize Dashboard</h2>
        <p class="text-muted mb-0">Choose which widgets to display and arrange their order</p>
    </div>
    <div class="d-flex gap-2">
        <form action="{{ route('dashboard.customize.reset') }}" method="POST" class="d-inline" 
              onsubmit="return confirm('Reset dashboard to default configuration? Your customizations will be lost.')">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Default
            </button>
        </form>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>

<form action="{{ route('dashboard.customize.save') }}" method="POST" id="customizeForm">
    @csrf
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <i class="bi bi-grid-3x3-gap me-2"></i>Available Widgets
                    <small class="text-muted ms-2">(Drag to reorder)</small>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" id="widgetList">
                        @foreach($widgets as $index => $widget)
                        <li class="list-group-item d-flex align-items-center gap-3 py-3" data-widget-id="{{ $widget['id'] }}">
                            <div class="drag-handle text-muted" style="cursor: grab;">
                                <i class="bi bi-grip-vertical fs-4"></i>
                            </div>
                            
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" 
                                       name="widgets[]" 
                                       value="{{ $widget['id'] }}"
                                       id="widget-{{ $widget['id'] }}"
                                       {{ $widget['enabled'] ? 'checked' : '' }}>
                            </div>
                            
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-{{ $widget['icon'] }} text-primary"></i>
                                    <strong>{{ $widget['name'] }}</strong>
                                </div>
                                <small class="text-muted">{{ $widget['description'] }}</small>
                            </div>
                            
                            <input type="hidden" name="positions[{{ $index }}]" value="{{ $widget['id'] }}" class="position-input">
                            
                            <span class="badge bg-light text-dark border">{{ ucfirst($widget['default_size']) }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-check-lg me-2"></i>Save Changes
                </button>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card bg-light border-0">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-info-circle me-2"></i>Tips</h5>
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">Toggle widgets on/off using the switch</li>
                        <li class="mb-2">Drag widgets to reorder them</li>
                        <li class="mb-2">Your preferences are saved per account</li>
                        <li class="mb-2">Click "Reset to Default" to restore role-based defaults</li>
                    </ul>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header bg-white">
                    <i class="bi bi-person-badge me-2"></i>Your Role Default
                </div>
                <div class="card-body">
                    <span class="badge bg-primary fs-6">{{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }}</span>
                    <p class="mt-3 mb-0 small text-muted">
                        Your role has a pre-configured widget layout optimized for your typical workflow. 
                        Click "Reset to Default" to restore it.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const widgetList = document.getElementById('widgetList');
    
    if (widgetList && typeof Sortable !== 'undefined') {
        new Sortable(widgetList, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'bg-primary-subtle',
            onEnd: function(evt) {
                // Update position inputs after drag
                const items = widgetList.querySelectorAll('[data-widget-id]');
                items.forEach((item, index) => {
                    const input = item.querySelector('.position-input');
                    if (input) {
                        input.name = `positions[${index}]`;
                    }
                });
            }
        });
    }
});
</script>
@endpush

@push('styles')
<style>
.drag-handle:hover {
    color: var(--bs-primary) !important;
}
.list-group-item:hover {
    background-color: rgba(0, 161, 170, 0.03);
}
.form-check-input:checked {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
}
</style>
@endpush
