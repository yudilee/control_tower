@extends('layouts.app')

@section('title', 'Invoiced Jobs Report')

@section('content')
<div class="page-header">
    <h1><i class="bi bi-check-circle me-2"></i>Invoiced Jobs</h1>
    <p class="text-muted">Jobs that have been invoiced</p>
</div>

@php
    // Calculate summary stats
    $allInvoicedJobs = \App\Models\Job::invoiced();
    
    // Apply same filters as the main query
    if (request('search')) {
        $search = request('search');
        $allInvoicedJobs->where(function ($q) use ($search) {
            $q->where('job_number', 'like', "%{$search}%")
              ->orWhere('plate_number', 'like', "%{$search}%")
              ->orWhere('invoice_number', 'like', "%{$search}%");
        });
    }
    if (request('date_from')) {
        $allInvoicedJobs->whereDate('invoice_date', '>=', request('date_from'));
    }
    if (request('date_to')) {
        $allInvoicedJobs->whereDate('invoice_date', '<=', request('date_to'));
    }
    
    // Get totals
    $totalAll = (clone $allInvoicedJobs)->sum('inv_ppn_meterai') ?? 0;
    $totalPC = (clone $allInvoicedJobs)->where('franchise', 'PC')->sum('inv_ppn_meterai') ?? 0;
    $totalCV = (clone $allInvoicedJobs)->where('franchise', 'CV')->sum('inv_ppn_meterai') ?? 0;
    
    // PC Department breakdown - include jobs without department as "No Department"
    $deptTotals = (clone $allInvoicedJobs)->where('franchise', 'PC')
        ->selectRaw('COALESCE(department, "No Department") as department, SUM(inv_ppn_meterai) as total, COUNT(*) as job_count')
        ->groupBy('department')
        ->orderByDesc('total')
        ->get();
    
    // Type Sale breakdown by franchise
    $typeSaleTotalsPC = (clone $allInvoicedJobs)->where('franchise', 'PC')
        ->selectRaw('COALESCE(type_sale, "Unknown") as type_sale, SUM(inv_ppn_meterai) as total, COUNT(*) as job_count')
        ->groupBy('type_sale')
        ->orderByDesc('total')
        ->get();
    
    $typeSaleTotalsCV = (clone $allInvoicedJobs)->where('franchise', 'CV')
        ->selectRaw('COALESCE(type_sale, "Unknown") as type_sale, SUM(inv_ppn_meterai) as total, COUNT(*) as job_count')
        ->groupBy('type_sale')
        ->orderByDesc('total')
        ->get();
    
    // Type sale labels
    $typeSaleLabels = [
        'INT' => 'Internal',
        'WAR' => 'Warranty', 
        'CASH' => 'Cash',
        'CREDIT' => 'Credit',
        'Unknown' => 'Unknown',
    ];
    
    // Count jobs summary
    $totalJobCount = (clone $allInvoicedJobs)->count();
    $pcJobCount = (clone $allInvoicedJobs)->where('franchise', 'PC')->count();
    $cvJobCount = (clone $allInvoicedJobs)->where('franchise', 'CV')->count();
@endphp

<!-- Summary Cards - Modern Glassmorphism -->
<style>
.stat-card {
    border-radius: 16px;
    padding: 1.25rem;
    text-align: center;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    overflow: hidden;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
.stat-card .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: -0.5px;
}
.stat-card .stat-label {
    font-size: 0.85rem;
    opacity: 0.85;
}
.stat-card-total {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.15) 0%, rgba(40, 167, 69, 0.25) 100%);
    border: 1px solid rgba(40, 167, 69, 0.4);
}
.stat-card-total .stat-value { color: #28a745; }
[data-theme="dark"] .stat-card-total .stat-value { color: #5dd879; }
.stat-card-pc {
    background: linear-gradient(135deg, rgba(0, 123, 255, 0.15) 0%, rgba(0, 123, 255, 0.25) 100%);
    border: 1px solid rgba(0, 123, 255, 0.4);
}
.stat-card-pc .stat-value { color: #007bff; }
[data-theme="dark"] .stat-card-pc .stat-value { color: #6cb5ff; }
.stat-card-cv {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.15) 0%, rgba(255, 193, 7, 0.25) 100%);
    border: 1px solid rgba(255, 193, 7, 0.4);
}
.stat-card-cv .stat-value { color: #e0a800; }
[data-theme="dark"] .stat-card-cv .stat-value { color: #ffc107; }

.breakdown-card {
    border-radius: 12px;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-body-bg);
}
.breakdown-header {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--bs-border-color);
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.breakdown-header.pc { background: linear-gradient(90deg, rgba(0, 123, 255, 0.1), transparent); color: var(--bs-body-color); }
.breakdown-header.cv { background: linear-gradient(90deg, rgba(255, 193, 7, 0.15), transparent); color: var(--bs-body-color); }
.breakdown-item {
    border-radius: 8px;
    padding: 0.6rem 0.75rem;
    text-align: center;
    border: 1px solid var(--bs-border-color);
    background: var(--bs-tertiary-bg);
    transition: background 0.2s;
}
.breakdown-item:hover {
    background: var(--bs-secondary-bg);
}
.breakdown-item .amount {
    font-weight: 600;
    font-size: 0.95rem;
}
.breakdown-item .label {
    font-size: 0.75rem;
    opacity: 0.7;
}
.breakdown-item.pc .amount { color: #007bff; }
[data-theme="dark"] .breakdown-item.pc .amount { color: #6cb5ff; }
.breakdown-item.cv .amount { color: #e0a800; }
[data-theme="dark"] .breakdown-item.cv .amount { color: #ffc107; }
</style>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-card-total">
            <div class="stat-value">Rp {{ number_format($totalAll, 0, ',', '.') }}</div>
            <div class="stat-label"><i class="bi bi-cash-stack me-1"></i>Total Invoiced ({{ number_format($totalJobCount) }} jobs)</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-card-pc">
            <div class="stat-value">Rp {{ number_format($totalPC, 0, ',', '.') }}</div>
            <div class="stat-label"><i class="bi bi-car-front me-1"></i>PC - Passenger Car ({{ number_format($pcJobCount) }} jobs)</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-card-cv">
            <div class="stat-value">Rp {{ number_format($totalCV, 0, ',', '.') }}</div>
            <div class="stat-label"><i class="bi bi-truck me-1"></i>CV - Commercial Vehicle ({{ number_format($cvJobCount) }} jobs)</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Type Sale Breakdown - PC -->
    <div class="col-md-6">
        <div class="breakdown-card h-100">
            <div class="breakdown-header pc">
                <span><i class="bi bi-car-front me-2"></i>PC Type Sale</span>
                <span class="badge bg-primary">{{ $typeSaleTotalsPC->sum('job_count') }} jobs</span>
            </div>
            <div class="p-3">
                @if($typeSaleTotalsPC->isNotEmpty())
                <div class="row g-2">
                    @foreach($typeSaleTotalsPC as $ts)
                    <div class="col-6">
                        <div class="breakdown-item pc">
                            <div class="amount">Rp {{ number_format($ts->total, 0, ',', '.') }}</div>
                            <div class="label">{{ $typeSaleLabels[$ts->type_sale] ?? $ts->type_sale }} ({{ $ts->job_count }})</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted text-center mb-0">No PC type sale data</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Type Sale Breakdown - CV -->
    <div class="col-md-6">
        <div class="breakdown-card h-100">
            <div class="breakdown-header cv">
                <span><i class="bi bi-truck me-2"></i>CV Type Sale</span>
                <span class="badge bg-warning text-dark">{{ $typeSaleTotalsCV->sum('job_count') }} jobs</span>
            </div>
            <div class="p-3">
                @if($typeSaleTotalsCV->isNotEmpty())
                <div class="row g-2">
                    @foreach($typeSaleTotalsCV as $ts)
                    <div class="col-6">
                        <div class="breakdown-item cv">
                            <div class="amount">Rp {{ number_format($ts->total, 0, ',', '.') }}</div>
                            <div class="label">{{ $typeSaleLabels[$ts->type_sale] ?? $ts->type_sale }} ({{ $ts->job_count }})</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted text-center mb-0">No CV type sale data</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- PC Department Breakdown - Full Width -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="breakdown-card">
            <div class="breakdown-header pc">
                <span><i class="bi bi-building me-2"></i>PC Department Breakdown</span>
                <span class="badge bg-primary">{{ $deptTotals->sum('job_count') }} jobs</span>
            </div>
            <div class="p-3">
                @if($deptTotals->isNotEmpty())
                <div class="row g-2">
                    @foreach($deptTotals as $dept)
                    <div class="col-md-3 col-6">
                        <div class="breakdown-item pc">
                            <div class="amount">Rp {{ number_format($dept->total, 0, ',', '.') }}</div>
                            <div class="label">{{ $dept->department }} ({{ $dept->job_count }})</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted text-center mb-0">No department data</p>
                @endif
            </div>
        </div>
    </div>
</div>


<!-- Filters and Actions -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3" id="filterForm">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search job, plate, invoice, customer..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control" placeholder="From" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control" placeholder="To" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
                <a href="{{ route('reports.invoiced') }}" class="btn btn-outline-secondary">Reset</a>
                
                <!-- Export Dropdown -->
                <div class="btn-group ms-auto">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="exportReport('xlsx')"><i class="bi bi-file-earmark-excel text-success me-2"></i>Excel (.xlsx)</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportReport('csv')"><i class="bi bi-filetype-csv text-primary me-2"></i>CSV</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>PDF (with summary cards)</a></li>
                    </ul>
                </div>
                
                <!-- Column Toggle -->
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <i class="bi bi-layout-three-columns me-1"></i>Columns
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width: 200px;">
                        <li class="dropdown-item-text small text-muted mb-1">Show/Hide Columns</li>
                        @php
                            $allColumns = [
                                'job_number' => ['label' => 'WIP', 'default' => true],
                                'franchise' => ['label' => 'Franchise', 'default' => false],
                                'department' => ['label' => 'Department', 'default' => false],
                                'plate_number' => ['label' => 'Plate No', 'default' => true],
                                'customer_name' => ['label' => 'Customer', 'default' => false],
                                'service_advisor' => ['label' => 'SA', 'default' => true],
                                'foreman' => ['label' => 'Foreman', 'default' => false],
                                'job_date' => ['label' => 'Job Date', 'default' => true],
                                'date_in' => ['label' => 'Date In', 'default' => false],
                                'date_out' => ['label' => 'Date Out', 'default' => false],
                                'invoice_number' => ['label' => 'Invoice #', 'default' => true],
                                'invoice_date' => ['label' => 'Inv Date', 'default' => true],
                                'type_sale' => ['label' => 'Type Sale', 'default' => false],
                                'inv_ppn_meterai' => ['label' => 'Amount', 'default' => true],
                            ];
                        @endphp
                        @foreach($allColumns as $colKey => $col)
                        <li>
                            <label class="dropdown-item py-1">
                                <input type="checkbox" class="column-toggle form-check-input me-2" data-column="{{ $colKey }}" {{ $col['default'] ? 'checked' : '' }}>
                                {{ $col['label'] }}
                            </label>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            
            <!-- Additional Filters Row -->
            <div class="col-md-3">
                <select name="franchise" class="form-select">
                    <option value="">All Franchises</option>
                    @foreach($filterOptions['franchise'] ?? [] as $opt)
                        <option value="{{ $opt }}" {{ request('franchise') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    @foreach($filterOptions['department'] ?? [] as $opt)
                        <option value="{{ $opt }}" {{ request('department') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="type_sale" class="form-select">
                    <option value="">All Type Sale</option>
                    @foreach($filterOptions['type_sale'] ?? [] as $opt)
                        <option value="{{ $opt }}" {{ request('type_sale') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="service_advisor" class="form-select">
                    <option value="">All Service Advisors</option>
                    @foreach($filterOptions['service_advisor'] ?? [] as $opt)
                        <option value="{{ $opt }}" {{ request('service_advisor') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Invoiced Jobs</span>
        <span class="badge bg-primary">{{ $jobs->total() }} records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="dataTable">
                <thead class="table-dark">
                    @php
                        $currentSort = request('sort', 'invoice_date');
                        $currentDir = request('dir', 'desc');
                        $sortMap = [
                            'job_number' => 'job_number',
                            'plate_number' => 'plate_number',
                            'service_advisor' => 'service_advisor',
                            'foreman' => 'foreman',
                            'job_date' => 'job_date',
                            'invoice_number' => 'invoice_number',
                            'invoice_date' => 'invoice_date',
                            'inv_ppn_meterai' => 'inv_ppn_meterai',
                        ];
                    @endphp
                    <tr id="headerRow">
                        @foreach([
                            'job_number' => 'WIP',
                            'franchise' => 'Franchise',
                            'department' => 'Dept',
                            'plate_number' => 'Plate',
                            'customer_name' => 'Customer',
                            'service_advisor' => 'SA',
                            'foreman' => 'Foreman',
                            'job_date' => 'Job Date',
                            'date_in' => 'Date In',
                            'date_out' => 'Date Out',
                            'invoice_number' => 'Invoice #',
                            'invoice_date' => 'Inv Date',
                            'type_sale' => 'Type Sale',
                            'inv_ppn_meterai' => 'Amount',
                        ] as $col => $label)
                            @php
                                $sortable = isset($sortMap[$col]);
                                $sortField = $sortMap[$col] ?? null;
                                $isActive = $sortable && $currentSort === $sortField;
                                $nextDir = $isActive && $currentDir === 'asc' ? 'desc' : 'asc';
                                $isHidden = in_array($col, ['franchise', 'department', 'customer_name', 'foreman', 'date_in', 'date_out', 'type_sale']);
                                $isNumeric = $col === 'inv_ppn_meterai';
                            @endphp
                            <th data-col="{{ $col }}" class="col-{{ $col }} {{ $isHidden ? 'd-none' : '' }} {{ $isNumeric ? 'text-end' : '' }}" @if($sortable) style="cursor: pointer;" @endif>
                                @if($sortable)
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => $sortField, 'dir' => $nextDir]) }}" class="text-white text-decoration-none d-flex align-items-center {{ $isNumeric ? 'justify-content-end' : 'justify-content-between' }}">
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
                        <th class="text-center" style="width: 60px;">Inv</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                    @php $hasMultiple = $job->invoices_count > 1; @endphp
                    <tr class="{{ $hasMultiple ? 'table-info' : '' }}">
                        <td class="col-job_number"><a href="{{ route('jobs.show', $job) }}" class="fw-bold">{{ $job->job_number }}</a></td>
                        <td class="col-franchise d-none">{{ $job->franchise ?? '-' }}</td>
                        <td class="col-department d-none">{{ $job->department ?? '-' }}</td>
                        <td class="col-plate_number">{{ $job->plate_number }}</td>
                        <td class="col-customer_name d-none">{{ Str::limit($job->customer_name, 25) ?? '-' }}</td>
                        <td class="col-service_advisor">{{ $job->service_advisor ?? '-' }}</td>
                        <td class="col-foreman d-none">{{ $job->foreman ?? '-' }}</td>
                        <td class="col-job_date">{{ $job->job_date?->format('d/m/Y') }}</td>
                        <td class="col-date_in d-none">{{ $job->date_in?->format('d/m/Y') ?? '-' }}</td>
                        <td class="col-date_out d-none">{{ $job->date_out?->format('d/m/Y') ?? '-' }}</td>
                        <td class="col-invoice_number"><span class="badge bg-success">{{ $job->invoice_number }}</span></td>
                        <td class="col-invoice_date">{{ $job->invoice_date?->format('d/m/Y') }}</td>
                        <td class="col-type_sale d-none"><span class="badge bg-info">{{ $job->type_sale ?? '-' }}</span></td>
                        <td class="col-inv_ppn_meterai text-end">{{ $job->inv_ppn_meterai ? number_format($job->inv_ppn_meterai, 0, ',', '.') : '-' }}</td>
                        <td class="text-center">
                            @if($hasMultiple)
                                <button class="btn btn-warning btn-sm py-0 px-1" type="button" data-bs-toggle="collapse" data-bs-target="#inv-{{ $job->id }}">
                                    <i class="bi bi-layers"></i> {{ $job->invoices_count }}
                                </button>
                            @else
                                <span class="text-muted">1</span>
                            @endif
                        </td>
                    </tr>
                    @if($hasMultiple)
                    <tr class="collapse" id="inv-{{ $job->id }}">
                        <td colspan="15" class="bg-light p-0">
                            <div class="p-2">
                                <strong class="small"><i class="bi bi-receipt me-1"></i>Invoice History ({{ $job->invoices_count }})</strong>
                                <table class="table table-sm table-bordered mb-0 mt-1 small">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Sale Type</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($job->invoices as $inv)
                                        <tr class="{{ $inv->invoice_type === 'credit_note' ? 'table-danger' : '' }}">
                                            <td>{{ $inv->invoice_number }}</td>
                                            <td>{{ $inv->invoice_date?->format('d/m/Y') }}</td>
                                            <td>
                                                @if($inv->invoice_type === 'credit_note')
                                                    <span class="badge bg-danger">CN</span>
                                                @else
                                                    <span class="badge bg-success">INV</span>
                                                @endif
                                            </td>
                                            <td>{{ $inv->type_sale ?? '-' }}</td>
                                            <td class="text-end">{{ number_format($inv->inv_amount, 0, ',', '.') }}</td>
                                            <td class="text-end fw-bold">{{ number_format($inv->inv_ppn_meterai, 0, ',', '.') }}</td>
                                        </tr>
                                        @endforeach
                                        <tr class="table-dark">
                                            <td colspan="5" class="text-end fw-bold">Net Total:</td>
                                            <td class="text-end fw-bold">{{ number_format($job->total_invoice_amount, 0, ',', '.') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="15" class="text-center text-muted py-4">No invoiced jobs found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $jobs->links() }}
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('dataTable');
    
    // Column toggle functionality
    document.querySelectorAll('.column-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const column = this.dataset.column;
            const cells = document.querySelectorAll('.col-' + column);
            cells.forEach(cell => {
                cell.classList.toggle('d-none', !this.checked);
            });
            localStorage.setItem('invoiced_col_' + column, this.checked ? '1' : '0');
        });
        
        const saved = localStorage.getItem('invoiced_col_' + checkbox.dataset.column);
        if (saved !== null) {
            checkbox.checked = saved === '1';
            const cells = document.querySelectorAll('.col-' + checkbox.dataset.column);
            cells.forEach(cell => {
                cell.classList.toggle('d-none', saved !== '1');
            });
        }
    });

    // Column Resizing
    table.querySelectorAll('th[data-col]').forEach(th => {
        const resizer = document.createElement('div');
        resizer.style.cssText = 'width:5px;height:100%;position:absolute;right:0;top:0;cursor:col-resize;user-select:none;z-index:10;';
        th.appendChild(resizer);
        th.style.position = 'relative';
        let startX, startWidth;
        resizer.addEventListener('mousedown', e => {
            e.stopPropagation();
            startX = e.pageX;
            startWidth = th.offsetWidth;
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
        function onMove(e) { th.style.width = (startWidth + e.pageX - startX) + 'px'; }
        function onUp() { 
            document.removeEventListener('mousemove', onMove); 
            document.removeEventListener('mouseup', onUp);
            const col = th.dataset.col;
            if (col) localStorage.setItem('invoiced_width_' + col, th.style.width);
        }
    });
    
    // Restore saved widths
    table.querySelectorAll('th[data-col]').forEach(th => {
        const savedWidth = localStorage.getItem('invoiced_width_' + th.dataset.col);
        if (savedWidth) th.style.width = savedWidth;
    });
});

// Export function
function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('format', format);
    
    const columns = [];
    document.querySelectorAll('.column-toggle:checked').forEach(cb => {
        columns.push(cb.dataset.column);
    });
    columns.forEach(c => params.append('columns[]', c));
    
    window.location.href = '{{ route("reports.export-invoiced") }}?' + params.toString();
}
</script>
@endpush

