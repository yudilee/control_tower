@extends('layouts.app')

@section('title', 'All Vehicles')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-car-front me-2"></i>All Vehicles</h1>
        <p class="text-muted">Total: {{ $vehicles->total() }} vehicles</p>
    </div>
    <a href="{{ route('vehicles.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Add Vehicle
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-center" id="searchForm">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search plate, customer, model..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="in_workshop" class="form-select form-select-sm">
                    <option value="">All Vehicles</option>
                    <option value="yes" {{ request('in_workshop') == 'yes' ? 'selected' : '' }}>In Workshop</option>
                    <option value="no" {{ request('in_workshop') == 'no' ? 'selected' : '' }}>Not in Workshop</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
                <a href="{{ route('vehicles.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>

                @auth
                <div class="dropdown">
                    <button class="btn btn-outline-dark btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-layout-three-columns"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 200px;">
                        <h6 class="dropdown-header">Visible Columns</h6>
                        <div id="columnToggles"></div>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="btn btn-primary btn-sm w-100" id="saveColumnsBtn">Save</button>
                    </div>
                </div>
                @endauth
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered table-sm mb-0" id="dataTable">
                <thead class="table-dark">
                    @php
                        $storedPrefs = auth()->user()?->vehicle_preferences ?? [];
                        $userSort = $storedPrefs['sort'] ?? 'created_at';
                        $userDir = $storedPrefs['dir'] ?? 'desc';
                        $currentSort = request('sort', $userSort);
                        $currentDir = request('dir', $userDir);
                        $sortMap = [
                            'plate' => 'plate_number',
                            'vin' => 'vin',
                            'model' => 'model',
                            'year' => 'year',
                            'customer' => 'customer_name',
                            'workshop' => 'is_in_workshop',
                            'jobs' => 'jobs_count',
                        ];
                    @endphp
                    <tr id="headerRow">
                        <th data-col="select" style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="selectAll" title="Select All">
                        </th>
                        <th data-col="no">#</th>
                        @foreach([
                            'plate' => 'Plate Number',
                            'vin' => 'VIN / Chassis',
                            'model' => 'Model',
                            'year' => 'Year',
                            'customer' => 'Customer',
                            'workshop' => 'In Workshop',
                            'jobs' => 'Jobs',
                        ] as $col => $label)
                            @php
                                $sortable = isset($sortMap[$col]);
                                $sortField = $sortMap[$col] ?? null;
                                $isActive = $sortable && $currentSort === $sortField;
                                $nextDir = $isActive && $currentDir === 'asc' ? 'desc' : 'asc';
                            @endphp
                            <th data-col="{{ $col }}" @if($sortable) style="cursor: pointer;" @endif>
                                @if($sortable)
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => $sortField, 'dir' => $nextDir]) }}" class="text-white text-decoration-none d-flex align-items-center justify-content-between">
                                        {{ $label }}
                                        @if($isActive)
                                            <i class="bi bi-arrow-{{ $currentDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                        @else
                                            <i class="bi bi-arrow-down-up ms-1 opacity-25"></i>
                                        @endif
                                    </a>
                                @else
                                    {{ $label }}
                                @endif
                            </th>
                        @endforeach
                        <th data-col="actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    @forelse($vehicles as $index => $vehicle)
                    <tr>
                        <td data-col="select">
                            <input type="checkbox" class="form-check-input vehicle-checkbox" value="{{ $vehicle->id }}">
                        </td>
                        <td data-col="no">{{ $vehicles->firstItem() + $index }}</td>
                        <td data-col="plate"><a href="{{ route('vehicles.show', $vehicle) }}" class="fw-bold text-primary">{{ $vehicle->plate_number }}</a></td>
                        <td data-col="vin">{{ $vehicle->vin ?? '-' }}</td>
                        <td data-col="model">{{ $vehicle->model ?? '-' }}</td>
                        <td data-col="year">{{ $vehicle->year ?? '-' }}</td>
                        <td data-col="customer">{{ $vehicle->customer_name ?? '-' }}</td>
                        <td data-col="workshop">
                            @if($vehicle->is_in_workshop)
                                <span class="badge bg-success">Yes</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </td>
                        <td data-col="jobs"><span class="badge bg-primary">{{ $vehicle->jobs_count }}</span></td>
                        <td data-col="actions" onclick="event.stopPropagation()">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('vehicles.show', $vehicle) }}" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                @if(auth()->user()?->canEdit())
                                <form action="{{ route('vehicles.toggle-workshop', $vehicle) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-{{ $vehicle->is_in_workshop ? 'warning' : 'success' }}" title="Toggle Workshop">
                                        <i class="bi bi-{{ $vehicle->is_in_workshop ? 'box-arrow-right' : 'box-arrow-in-right' }}"></i>
                                    </button>
                                </form>
                                @else
                                <button type="button" class="btn btn-outline-secondary" disabled title="You don't have permission to change workshop status">
                                    <i class="bi bi-{{ $vehicle->is_in_workshop ? 'box-arrow-right' : 'box-arrow-in-right' }}"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No vehicles found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <div class="d-flex align-items-center">
        <label class="me-2 small text-muted">Show</label>
        <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()" form="searchForm">
            <option value="10" {{ request('per_page') == '10' ? 'selected' : '' }}>10</option>
            <option value="20" {{ (request('per_page') == '20' || !request('per_page')) ? 'selected' : '' }}>20</option>
            <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50</option>
        </select>
        <span class="ms-2 small text-muted">entries</span>
    </div>
    {{ $vehicles->withQueryString()->links() }}
</div>

<!-- Bulk Action Bar -->
<div class="card mt-3 d-none" id="bulkActionBar">
    <div class="card-body py-2 d-flex align-items-center justify-content-between bg-light">
        <span>
            <strong id="selectedCount">0</strong> vehicle(s) selected
        </span>
        <div class="btn-group">
            <button type="button" class="btn btn-success btn-sm" id="bulkInWorkshop">
                <i class="bi bi-box-arrow-in-right me-1"></i>Mark In Workshop
            </button>
            <button type="button" class="btn btn-warning btn-sm" id="bulkOutWorkshop">
                <i class="bi bi-box-arrow-right me-1"></i>Mark Out of Workshop
            </button>
        </div>
    </div>
</div>

@push('scripts')
@php
    $defaultPrefs = [
        'columns' => ['no' => true, 'plate' => true, 'vin' => false, 'model' => true, 'year' => false, 'customer' => true, 'workshop' => true, 'jobs' => true, 'actions' => true],
        'order' => ['no', 'plate', 'vin', 'model', 'year', 'customer', 'workshop', 'jobs', 'actions'],
        'widths' => [],
        'sort' => 'created_at',
        'dir' => 'desc'
    ];
    $storedPrefs = auth()->user()?->vehicle_preferences ?? [];
    $userPrefs = array_merge($defaultPrefs['columns'], $storedPrefs['columns'] ?? []);
    $userOrder = $storedPrefs['order'] ?? $defaultPrefs['order'];
    $userWidths = $storedPrefs['widths'] ?? [];
    $userSort = $storedPrefs['sort'] ?? $defaultPrefs['sort'];
    $userDir = $storedPrefs['dir'] ?? $defaultPrefs['dir'];
@endphp
<script>
document.addEventListener('DOMContentLoaded', function() {
    const userPrefs = @json($userPrefs);
    let userOrder = @json($userOrder);
    const userWidths = @json($userWidths);
    const userSort = @json($userSort);
    const userDir = @json($userDir);
    const columnLabels = {
        'no': '#', 'plate': 'Plate Number', 'vin': 'VIN / Chassis', 'model': 'Model', 'year': 'Year',
        'customer': 'Customer', 'workshop': 'In Workshop', 'jobs': 'Jobs', 'actions': 'Actions'
    };
    const container = document.getElementById('columnToggles');
    const table = document.getElementById('dataTable');
    const headerRow = document.getElementById('headerRow');

    Object.keys(userWidths).forEach(col => {
        const th = table.querySelector(`th[data-col="${col}"]`);
        if(th) th.style.width = userWidths[col];
    });

    function applyColumnOrder(order) {
        order.forEach((col) => {
            const th = headerRow.querySelector(`th[data-col="${col}"]`);
            if (th) headerRow.appendChild(th);
        });
        document.querySelectorAll('#tableBody tr').forEach(row => {
            order.forEach(col => {
                const td = row.querySelector(`td[data-col="${col}"]`);
                if (td) row.appendChild(td);
            });
        });
    }
    applyColumnOrder(userOrder);

    function buildToggles() {
        container.innerHTML = '';
        userOrder.forEach(key => {
            if (!columnLabels[key]) return;
            const div = document.createElement('div');
            div.className = 'form-check d-flex align-items-center py-1';
            div.draggable = true;
            div.dataset.col = key;
            div.innerHTML = `
                <i class="bi bi-grip-vertical text-muted me-2" style="cursor: grab;"></i>
                <input class="form-check-input col-toggle" type="checkbox" value="${key}" id="col_${key}" ${userPrefs[key] ? 'checked' : ''}>
                <label class="form-check-label ms-1 small" for="col_${key}">${columnLabels[key]}</label>
            `;
            container.appendChild(div);
        });
        setupDragDrop();
    }
    buildToggles();

    function setupDragDrop() {
        let draggedEl = null;
        container.querySelectorAll('[draggable]').forEach(el => {
            el.addEventListener('dragstart', e => { draggedEl = el; el.classList.add('opacity-50'); e.dataTransfer.effectAllowed = 'move'; });
            el.addEventListener('dragend', e => { el.classList.remove('opacity-50'); container.querySelectorAll('.drag-over').forEach(x => x.classList.remove('drag-over', 'border-top', 'border-primary')); draggedEl = null; });
            el.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; el.classList.add('drag-over', 'border-top', 'border-primary'); });
            el.addEventListener('dragleave', e => { el.classList.remove('drag-over', 'border-top', 'border-primary'); });
            el.addEventListener('drop', e => { e.preventDefault(); el.classList.remove('drag-over', 'border-top', 'border-primary'); if (draggedEl && draggedEl !== el) { container.insertBefore(draggedEl, el); updateOrderFromDOM(); applyColumnOrderFromDOM(); } });
        });
    }

    function updateOrderFromDOM() { userOrder = []; container.querySelectorAll('[data-col]').forEach(el => userOrder.push(el.dataset.col)); }
    function applyColumnOrderFromDOM() { const order = []; container.querySelectorAll('[data-col]').forEach(el => order.push(el.dataset.col)); applyColumnOrder(order); }

    function applyVisibility() {
        document.querySelectorAll('.col-toggle').forEach(toggle => {
            const colName = toggle.value;
            const visible = toggle.checked;
            const th = table.querySelector(`th[data-col="${colName}"]`);
            if(th) th.style.display = visible ? '' : 'none';
            table.querySelectorAll(`td[data-col="${colName}"]`).forEach(td => td.style.display = visible ? '' : 'none');
        });
    }
    applyVisibility();
    container.addEventListener('change', applyVisibility);

    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort') || userSort;
    const currentDir = urlParams.get('dir') || userDir;

    document.getElementById('saveColumnsBtn').addEventListener('click', function() {
        const prefs = {};
        document.querySelectorAll('.col-toggle').forEach(t => prefs[t.value] = t.checked);
        const widths = {};
        table.querySelectorAll('th').forEach(th => { if(th.dataset.col && th.style.width) widths[th.dataset.col] = th.style.width; });
        const order = [];
        container.querySelectorAll('[data-col]').forEach(el => order.push(el.dataset.col));

        fetch('{{ route("preferences.columns") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ columns: prefs, widths: widths, order: order, sort: currentSort, dir: currentDir, table: 'vehicle' })
        }).then(res => res.json()).then(data => {
            if(data.success) {
                const btn = document.getElementById('saveColumnsBtn');
                btn.innerHTML = '<i class="bi bi-check"></i> Saved!';
                btn.classList.replace('btn-primary', 'btn-success');
                setTimeout(() => { btn.innerHTML = 'Save'; btn.classList.replace('btn-success', 'btn-primary'); }, 1500);
            }
        }).catch(err => alert('Error: ' + err.message));
    });

    table.querySelectorAll('th').forEach(th => {
        const resizer = document.createElement('div');
        resizer.style.cssText = 'width:5px;height:100%;position:absolute;right:0;top:0;cursor:col-resize;user-select:none;z-index:10;';
        th.appendChild(resizer);
        th.style.position = 'relative';
        let startX, startWidth;
        resizer.addEventListener('mousedown', e => { e.stopPropagation(); startX = e.pageX; startWidth = th.offsetWidth; document.addEventListener('mousemove', onMove); document.addEventListener('mouseup', onUp); });
        function onMove(e) { th.style.width = (startWidth + e.pageX - startX) + 'px'; }
        function onUp() { document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp); }
    });

    // Multi-select functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    const vehicleCheckboxes = document.querySelectorAll('.vehicle-checkbox');
    const bulkActionBar = document.getElementById('bulkActionBar');
    const selectedCountSpan = document.getElementById('selectedCount');
    
    function updateBulkActionBar() {
        const checkedCount = document.querySelectorAll('.vehicle-checkbox:checked').length;
        selectedCountSpan.textContent = checkedCount;
        if (checkedCount > 0) {
            bulkActionBar.classList.remove('d-none');
        } else {
            bulkActionBar.classList.add('d-none');
        }
    }
    
    // Select all toggle
    selectAllCheckbox.addEventListener('change', function() {
        vehicleCheckboxes.forEach(cb => cb.checked = this.checked);
        updateBulkActionBar();
    });
    
    // Individual checkbox change
    vehicleCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = [...vehicleCheckboxes].every(c => c.checked);
            const someChecked = [...vehicleCheckboxes].some(c => c.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked && !allChecked;
            updateBulkActionBar();
        });
    });
    
    // Bulk actions
    async function bulkUpdateWorkshop(status) {
        const selectedIds = [...document.querySelectorAll('.vehicle-checkbox:checked')].map(cb => cb.value);
        if (selectedIds.length === 0) return;
        
        const statusText = status === 'in' ? 'In Workshop' : 'Out of Workshop';
        if (!confirm(`Mark ${selectedIds.length} vehicle(s) as "${statusText}"?`)) return;
        
        try {
            const response = await fetch('{{ route("vehicles.bulk-workshop") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ vehicle_ids: selectedIds, status: status })
            });
            
            const data = await response.json();
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    
    document.getElementById('bulkInWorkshop').addEventListener('click', () => bulkUpdateWorkshop('in'));
    document.getElementById('bulkOutWorkshop').addEventListener('click', () => bulkUpdateWorkshop('out'));
});
</script>
@endpush
@endsection
