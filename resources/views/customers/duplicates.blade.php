@extends('layouts.app')

@section('title', 'Merge Duplicate Customers')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
                <li class="breadcrumb-item active">Merge Duplicates</li>
            </ol>
        </nav>
        <h1><i class="bi bi-people me-2"></i>Merge Duplicate Customers</h1>
        <p class="text-muted mb-0">Found {{ $totalGroups }} groups of similar names</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Customers
        </a>
    </div>
</div>

@if($totalGroups == 0)
<div class="alert alert-success">
    <i class="bi bi-check-circle me-2"></i>No duplicate customer names detected. Your data looks clean!
</div>
@else

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center border-danger bg-danger bg-opacity-10">
            <div class="card-body py-3">
                <h3 class="mb-0 text-danger">{{ $dmsIssueCount }}</h3>
                <small class="text-muted"><i class="bi bi-database-exclamation me-1"></i>DMS Issues (Fix in Main System)</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-warning bg-warning bg-opacity-10">
            <div class="card-body py-3">
                <h3 class="mb-0 text-warning">{{ $userMistakeCount }}</h3>
                <small class="text-muted"><i class="bi bi-person-exclamation me-1"></i>User Entry Issues</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center border-primary">
            <div class="card-body py-3">
                <h3 class="mb-0 text-primary">{{ $totalGroups }}</h3>
                <small class="text-muted"><i class="bi bi-collection me-1"></i>Total Groups</small>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>How to merge:</strong> Toggle each group you want to merge, select the canonical name, then click "Merge All Selected".
    <br><small class="text-muted">
        <span class="badge bg-danger">DMS Issue</span> = 2+ entries from Invoice/Uninvoiced import (need to fix in main DMS system) 
        | <span class="badge bg-warning text-dark">User Mistake</span> = Entry from Job Progress import or Manual entry
    </small>
</div>

<form action="{{ route('customers.merge-batch') }}" method="POST" id="batchMergeForm">
    @csrf
    
    @foreach($duplicateGroups as $index => $group)
    @php
        $classification = $group['classification'];
        $borderClass = $classification === 'DMS_ISSUE' ? 'border-danger' : 'border-warning';
        $badgeClass = $classification === 'DMS_ISSUE' ? 'bg-danger' : 'bg-warning text-dark';
        $classLabel = $classification === 'DMS_ISSUE' ? 'DMS Issue' : 'User Mistake';
    @endphp
    <div class="card mb-3 merge-group {{ $borderClass }}" data-group="{{ $index }}">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <input type="checkbox" class="form-check-input group-toggle me-2" data-group="{{ $index }}" title="Enable this group for merge">
                <i class="bi bi-exclamation-triangle text-warning me-1"></i>Group #{{ $index + 1 }}
                <span class="badge {{ $badgeClass }} ms-2">{{ $classLabel }}</span>
            </span>
            <span class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary">{{ count($group['entries']) }} variations</span>
                <form action="{{ route('customers.dismiss-group') }}" method="POST" class="d-inline" onsubmit="return confirm('Dismiss this group? These names will not appear as duplicates again.')">
                    @csrf
                    @foreach($group['names'] as $name)
                        <input type="hidden" name="names[]" value="{{ $name }}">
                    @endforeach
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Not duplicates - dismiss this group">
                        <i class="bi bi-x-lg"></i> Dismiss
                    </button>
                </form>
            </span>
        </div>
        <div class="card-body">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" class="form-check-input select-all-group" data-group="{{ $index }}" title="Select all">
                        </th>
                        <th>Customer Name</th>
                        <th class="text-center" style="width: 180px;">Source</th>
                        <th class="text-center" style="width: 80px;">Jobs</th>
                        <th class="text-center" style="width: 80px;">Vehicles</th>
                        <th class="text-center" style="width: 100px;">Keep</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['entries'] as $item)
                    @php
                        $sourceBadge = match($item['source']) {
                            'dms_import' => 'bg-danger',
                            'job_progress_import' => 'bg-warning text-dark',
                            'user_entry' => 'bg-secondary',
                            default => 'bg-secondary',
                        };
                    @endphp
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input name-checkbox" 
                                   data-group="{{ $index }}"
                                   name="groups[{{ $index }}][names][]" value="{{ $item['name'] }}">
                        </td>
                        <td>
                            <a href="{{ route('customers.show', ['name' => $item['name']]) }}" target="_blank" class="text-primary">
                                {{ $item['name'] }}
                            </a>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $sourceBadge }}">{{ $item['source_label'] }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary">{{ $item['job_count'] }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info">{{ $item['vehicle_count'] }}</span>
                        </td>
                        <td class="text-center">
                            <input type="radio" class="form-check-input canonical-radio" 
                                   data-group="{{ $index }}"
                                   name="groups[{{ $index }}][canonical]" value="{{ $item['name'] }}">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach

    <!-- Sticky bottom bar -->
    <div class="card bg-dark text-white position-sticky bottom-0 mt-3" style="z-index: 100;">
        <div class="card-body d-flex justify-content-between align-items-center py-3">
            <div>
                <span id="selectedCount">0</span> groups selected for merge
            </div>
            <button type="submit" class="btn btn-warning btn-lg" id="batchMergeBtn" disabled>
                <i class="bi bi-arrow-right-circle me-2"></i>Merge All Selected Groups
            </button>
        </div>
    </div>
</form>

@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('batchMergeForm');
    if (!form) return;

    // Group toggle checkbox - enables/disables entire group
    document.querySelectorAll('.group-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function() {
            const groupId = this.dataset.group;
            const card = this.closest('.merge-group');
            
            if (this.checked) {
                card.style.boxShadow = '0 0 10px rgba(255, 193, 7, 0.5)';
                // Auto-select all names and first as canonical
                card.querySelectorAll('.name-checkbox').forEach(cb => cb.checked = true);
                const firstRadio = card.querySelector('.canonical-radio');
                if (firstRadio) firstRadio.checked = true;
            } else {
                card.style.boxShadow = 'none';
                card.querySelectorAll('.name-checkbox').forEach(cb => cb.checked = false);
                card.querySelectorAll('.canonical-radio').forEach(r => r.checked = false);
            }
            updateBatchButton();
        });
    });

    // Select all within group
    document.querySelectorAll('.select-all-group').forEach(function(selectAll) {
        selectAll.addEventListener('change', function() {
            const groupId = this.dataset.group;
            document.querySelectorAll(`.name-checkbox[data-group="${groupId}"]`).forEach(cb => {
                cb.checked = this.checked;
            });
            const groupToggle = document.querySelector(`.group-toggle[data-group="${groupId}"]`);
            if (groupToggle) groupToggle.checked = this.checked;
            updateBatchButton();
        });
    });

    // Individual checkbox changes
    document.querySelectorAll('.name-checkbox, .canonical-radio').forEach(function(el) {
        el.addEventListener('change', function() {
            const groupId = this.dataset.group;
            const groupToggle = document.querySelector(`.group-toggle[data-group="${groupId}"]`);
            const card = this.closest('.merge-group');
            const hasChecked = card.querySelectorAll('.name-checkbox:checked').length > 0;
            if (groupToggle) groupToggle.checked = hasChecked;
            card.style.boxShadow = hasChecked ? '0 0 10px rgba(255, 193, 7, 0.5)' : 'none';
            updateBatchButton();
        });
    });

    function updateBatchButton() {
        let validGroups = 0;
        document.querySelectorAll('.group-toggle:checked').forEach(function(toggle) {
            const groupId = toggle.dataset.group;
            const hasNames = document.querySelectorAll(`.name-checkbox[data-group="${groupId}"]:checked`).length >= 1;
            const hasCanonical = document.querySelector(`.canonical-radio[data-group="${groupId}"]:checked`) !== null;
            if (hasNames && hasCanonical) validGroups++;
        });
        
        document.getElementById('selectedCount').textContent = validGroups;
        document.getElementById('batchMergeBtn').disabled = validGroups === 0;
    }

    // Confirm before submit
    form.addEventListener('submit', function(e) {
        const count = parseInt(document.getElementById('selectedCount').textContent);
        if (!confirm(`You are about to merge ${count} group(s) of customer names.\n\nThis action cannot be undone. Continue?`)) {
            e.preventDefault();
        }
    });
});
</script>
@endpush
@endsection
