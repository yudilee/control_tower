@extends('layouts.app')

@section('title', 'Customer Alias Management')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
                <li class="breadcrumb-item active">Alias Management</li>
            </ol>
        </nav>
        <h1><i class="bi bi-link-45deg me-2"></i>Customer Alias Management</h1>
        <p class="text-muted mb-0">Link invoice customer names to DMS customers</p>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row">
    <!-- Unmatched Names -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-question-circle me-2"></i>Unmatched Customer Names</span>
                <span class="badge bg-warning text-dark">{{ $unmatchedNames->total() }} names</span>
            </div>
            <div class="card-body p-0">
                @if($unmatchedNames->count() > 0)
                <form action="{{ route('admin.customer-aliases.bulk-link') }}" method="POST" id="bulkLinkForm">
                    @csrf
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Customer Name (from Invoice)</th>
                                <th class="text-center">Jobs</th>
                                <th>Link to Customer</th>
                                <th class="text-center">Create Alias</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unmatchedNames as $index => $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->customer_name }}</strong>
                                    <input type="hidden" name="mappings[{{ $index }}][customer_name]" value="{{ $item->customer_name }}">
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ $item->job_count }}</span>
                                </td>
                                <td>
                                    <select name="mappings[{{ $index }}][customer_id]" class="form-select form-select-sm customer-select" data-name="{{ $item->customer_name }}">
                                        <option value="">-- Select Customer --</option>
                                        @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }} {{ $customer->dms_magic ? "(#{$customer->dms_magic})" : '' }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="mappings[{{ $index }}][create_alias]" value="1" class="form-check-input" checked>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="p-3 bg-light border-top">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-link me-1"></i>Link Selected
                        </button>
                    </div>
                </form>
                
                <div class="p-3">
                    {{ $unmatchedNames->links() }}
                </div>
                @else
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-check-circle display-4 text-success"></i>
                    <h5 class="mt-3">All customer names are linked!</h5>
                    <p>No unmatched customer names found in jobs.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Existing Aliases & Quick Add -->
    <div class="col-lg-4">
        <!-- Quick Add Alias -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-plus-circle me-2"></i>Quick Add Alias
            </div>
            <div class="card-body">
                <form action="{{ route('admin.customer-aliases.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Alias Name <span class="text-danger">*</span></label>
                        <input type="text" name="alias_name" class="form-control" placeholder="Enter name variant..." required>
                        <div class="form-text">The name variation from invoice/accounting</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-select" required>
                            <option value="">-- Select Customer --</option>
                            @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-plus me-1"></i>Create Alias
                    </button>
                </form>
            </div>
        </div>

        <!-- Existing Aliases -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list me-2"></i>Existing Aliases</span>
                <span class="badge bg-info">{{ $aliases->count() }}</span>
            </div>
            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                @if($aliases->count() > 0)
                <table class="table table-sm mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Alias</th>
                            <th>Customer</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($aliases as $alias)
                        <tr>
                            <td class="small">{{ Str::limit($alias->alias_name, 25) }}</td>
                            <td class="small">{{ Str::limit($alias->customer->name ?? '-', 20) }}</td>
                            <td class="text-end">
                                <form action="{{ route('admin.customer-aliases.destroy', $alias) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this alias?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-inbox display-6"></i>
                    <p class="mt-2 mb-0">No aliases created yet</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-suggest based on similar names
    const selects = document.querySelectorAll('.customer-select');
    selects.forEach(select => {
        const name = select.dataset.name?.toUpperCase() || '';
        if (!name) return;
        
        // Find best matching option
        let bestMatch = null;
        let bestScore = 0;
        
        Array.from(select.options).forEach(option => {
            if (!option.value) return;
            const optionName = option.text.toUpperCase();
            
            // Simple similarity - check if names contain each other
            if (optionName.includes(name) || name.includes(optionName)) {
                const score = Math.min(name.length, optionName.length) / Math.max(name.length, optionName.length);
                if (score > bestScore) {
                    bestScore = score;
                    bestMatch = option;
                }
            }
            
            // Check first few words match
            const nameWords = name.split(/\s+/).slice(0, 2).join(' ');
            const optionWords = optionName.split(/\s+/).slice(0, 2).join(' ');
            if (nameWords === optionWords && nameWords.length > 3) {
                bestScore = 0.9;
                bestMatch = option;
            }
        });
        
        if (bestMatch && bestScore > 0.7) {
            select.value = bestMatch.value;
            select.classList.add('border-success');
        }
    });
});
</script>
@endpush
@endsection
