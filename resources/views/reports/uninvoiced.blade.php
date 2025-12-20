@extends('layouts.app')

@section('title', 'Uninvoiced Jobs Report')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-exclamation-triangle me-2"></i>Uninvoiced Jobs</h1>
        <p class="text-muted">Jobs that haven't been invoiced yet</p>
    </div>
</div>

@php
    // Calculate summary stats for uninvoiced jobs
    $allUninvoicedJobs = \App\Models\Job::uninvoiced();
    
    // Apply same filters as the main query
    if (request('search')) {
        $search = request('search');
        $allUninvoicedJobs->where(function ($q) use ($search) {
            $q->where('job_number', 'like', "%{$search}%")
              ->orWhere('plate_number', 'like', "%{$search}%")
              ->orWhere('latest_remark', 'like', "%{$search}%");
        });
    }
    if (request('date_from')) {
        $allUninvoicedJobs->whereDate('job_date', '>=', request('date_from'));
    }
    if (request('date_to')) {
        $allUninvoicedJobs->whereDate('job_date', '<=', request('date_to'));
    }
    
    // Count totals
    $totalJobCount = (clone $allUninvoicedJobs)->count();
    $pcJobCount = (clone $allUninvoicedJobs)->where('franchise', 'PC')->count();
    $cvJobCount = (clone $allUninvoicedJobs)->where('franchise', 'CV')->count();
    
    // Sales totals
    $totalLabour = (clone $allUninvoicedJobs)->sum('labour_sales') ?? 0;
    $totalParts = (clone $allUninvoicedJobs)->sum('part_sales') ?? 0;
    $totalSales = (clone $allUninvoicedJobs)->sum('total_sales') ?? 0;
    
    // PC totals
    $pcLabour = (clone $allUninvoicedJobs)->where('franchise', 'PC')->sum('labour_sales') ?? 0;
    $pcParts = (clone $allUninvoicedJobs)->where('franchise', 'PC')->sum('part_sales') ?? 0;
    $pcTotal = (clone $allUninvoicedJobs)->where('franchise', 'PC')->sum('total_sales') ?? 0;
    
    // CV totals
    $cvLabour = (clone $allUninvoicedJobs)->where('franchise', 'CV')->sum('labour_sales') ?? 0;
    $cvParts = (clone $allUninvoicedJobs)->where('franchise', 'CV')->sum('part_sales') ?? 0;
    $cvTotal = (clone $allUninvoicedJobs)->where('franchise', 'CV')->sum('total_sales') ?? 0;
    
    // Work Status breakdown - get all defined statuses, merge with actual counts
    $actualStatusCounts = (clone $allUninvoicedJobs)
        ->selectRaw('COALESCE(work_status, "Pending") as work_status, COUNT(*) as job_count, SUM(total_sales) as total')
        ->groupBy('work_status')
        ->pluck('job_count', 'work_status');
    
    // Get all defined work statuses from DropdownOption
    $allWorkStatuses = \App\Models\DropdownOption::getOptions('work_status');
    
    // Build combined list with all statuses
    $workStatusTotals = collect();
    foreach ($allWorkStatuses as $status) {
        $workStatusTotals->push((object)[
            'work_status' => $status->label,
            'job_count' => $actualStatusCounts->get($status->value, 0) ?? $actualStatusCounts->get($status->label, 0),
            'color' => $status->color,
            'icon' => $status->icon,
        ]);
    }
    
    // Add any statuses from data that aren't in dropdown options (e.g., "Pending")
    foreach ($actualStatusCounts as $status => $count) {
        if (!$workStatusTotals->where('work_status', $status)->count()) {
            $workStatusTotals->push((object)[
                'work_status' => $status,
                'job_count' => $count,
                'color' => 'secondary',
                'icon' => null,
            ]);
        }
    }
    
    // Sort by job count descending
    $workStatusTotals = $workStatusTotals->sortByDesc('job_count')->values();
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
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.15) 0%, rgba(220, 53, 69, 0.25) 100%);
    border: 1px solid rgba(220, 53, 69, 0.4);
}
.stat-card-total .stat-value { color: #dc3545; }
[data-theme="dark"] .stat-card-total .stat-value { color: #ff6b7a; }
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
.breakdown-header.danger { background: linear-gradient(90deg, rgba(220, 53, 69, 0.1), transparent); color: var(--bs-body-color); }
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
.breakdown-item.danger .amount { color: #dc3545; }
[data-theme="dark"] .breakdown-item.danger .amount { color: #ff6b7a; }
.breakdown-item.pc .amount { color: #007bff; }
[data-theme="dark"] .breakdown-item.pc .amount { color: #6cb5ff; }
.breakdown-item.cv .amount { color: #e0a800; }
[data-theme="dark"] .breakdown-item.cv .amount { color: #ffc107; }

.sales-row { display: flex; justify-content: space-around; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--bs-border-color); }
.sales-row .sales-item { text-align: center; }
.sales-row .sales-item .val { font-weight: 600; font-size: 0.85rem; }
.sales-row .sales-item .lbl { font-size: 0.7rem; opacity: 0.6; }
</style>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card stat-card-total">
            <div class="stat-value">{{ number_format($totalJobCount) }} Jobs</div>
            <div class="stat-label"><i class="bi bi-exclamation-triangle me-1"></i>Total Uninvoiced</div>
            <div class="sales-row">
                <div class="sales-item"><div class="val">Rp {{ number_format($totalLabour, 0, ',', '.') }}</div><div class="lbl">Labour</div></div>
                <div class="sales-item"><div class="val">Rp {{ number_format($totalParts, 0, ',', '.') }}</div><div class="lbl">Parts</div></div>
                <div class="sales-item"><div class="val">Rp {{ number_format($totalSales, 0, ',', '.') }}</div><div class="lbl">Total</div></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-card-pc">
            <div class="stat-value">{{ number_format($pcJobCount) }} Jobs</div>
            <div class="stat-label"><i class="bi bi-car-front me-1"></i>PC - Passenger Car</div>
            <div class="sales-row">
                <div class="sales-item"><div class="val">Rp {{ number_format($pcLabour, 0, ',', '.') }}</div><div class="lbl">Labour</div></div>
                <div class="sales-item"><div class="val">Rp {{ number_format($pcParts, 0, ',', '.') }}</div><div class="lbl">Parts</div></div>
                <div class="sales-item"><div class="val">Rp {{ number_format($pcTotal, 0, ',', '.') }}</div><div class="lbl">Total</div></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card stat-card-cv">
            <div class="stat-value">{{ number_format($cvJobCount) }} Jobs</div>
            <div class="stat-label"><i class="bi bi-truck me-1"></i>CV - Commercial Vehicle</div>
            <div class="sales-row">
                <div class="sales-item"><div class="val">Rp {{ number_format($cvLabour, 0, ',', '.') }}</div><div class="lbl">Labour</div></div>
                <div class="sales-item"><div class="val">Rp {{ number_format($cvParts, 0, ',', '.') }}</div><div class="lbl">Parts</div></div>
                <div class="sales-item"><div class="val">Rp {{ number_format($cvTotal, 0, ',', '.') }}</div><div class="lbl">Total</div></div>
            </div>
        </div>
    </div>
</div>

<!-- Work Status Breakdown -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="breakdown-card">
            <div class="breakdown-header danger">
                <span><i class="bi bi-gear me-2"></i>Work Status Breakdown</span>
                <span class="badge bg-danger">{{ $workStatusTotals->sum('job_count') }} jobs</span>
            </div>
            <div class="p-3">
                @if($workStatusTotals->isNotEmpty())
                <div class="row g-2">
                    @foreach($workStatusTotals as $ws)
                    <div class="col-md-2 col-4">
                        <div class="breakdown-item" style="border-color: var(--bs-{{ $ws->color ?? 'secondary' }}); {{ $ws->job_count > 0 ? '' : 'opacity: 0.5;' }}">
                            <div class="amount" style="color: var(--bs-{{ $ws->color ?? 'secondary' }});">
                                @if($ws->icon)<i class="bi bi-{{ $ws->icon }} me-1"></i>@endif
                                {{ number_format($ws->job_count) }}
                            </div>
                            <div class="label">{{ $ws->work_status }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted text-center mb-0">No work status data</p>
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
                <input type="text" name="search" class="form-control" placeholder="Search job, plate, remark..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control" placeholder="From" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control" placeholder="To" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
                <a href="{{ route('reports.uninvoiced') }}" class="btn btn-outline-secondary">Reset</a>
                
                <!-- Export Dropdown -->
                <div class="btn-group ms-auto">
                    <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="exportReport('xlsx')"><i class="bi bi-file-earmark-excel text-success me-2"></i>Excel (.xlsx)</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportReport('csv')"><i class="bi bi-filetype-csv text-primary me-2"></i>CSV</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')"><i class="bi bi-file-earmark-pdf text-danger me-2"></i>PDF (with summary)</a></li>
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
                                'plate_number' => ['label' => 'Plate No', 'default' => true],
                                'customer_name' => ['label' => 'Customer', 'default' => false],
                                'service_advisor' => ['label' => 'SA', 'default' => true],
                                'foreman' => ['label' => 'Foreman', 'default' => false],
                                'job_date' => ['label' => 'Job Date', 'default' => true],
                                'total_sales' => ['label' => 'Total Sales', 'default' => true],
                                'labour_sales' => ['label' => 'Labour', 'default' => false],
                                'part_sales' => ['label' => 'Parts', 'default' => false],
                                'work_status' => ['label' => 'Work Status', 'default' => true],
                                'need_part' => ['label' => 'Need Part', 'default' => false],
                                'latest_remark' => ['label' => 'Last Remark', 'default' => true],
                                'latest_remark_at' => ['label' => 'Remark Date', 'default' => false],
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
            <div class="col-md-2">
                <select name="franchise" class="form-select">
                    <option value="">All Franchises</option>
                    <option value="PC" {{ request('franchise') == 'PC' ? 'selected' : '' }}>PC</option>
                    <option value="CV" {{ request('franchise') == 'CV' ? 'selected' : '' }}>CV</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="service_advisor" class="form-select">
                    <option value="">All SA</option>
                    @foreach(\App\Models\Job::whereNotNull('service_advisor')->distinct()->pluck('service_advisor')->sort() as $sa)
                        <option value="{{ $sa }}" {{ request('service_advisor') == $sa ? 'selected' : '' }}>{{ $sa }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="foreman" class="form-select">
                    <option value="">All Foreman</option>
                    @foreach(\App\Models\Job::whereNotNull('foreman')->distinct()->pluck('foreman')->sort() as $fm)
                        <option value="{{ $fm }}" {{ request('foreman') == $fm ? 'selected' : '' }}>{{ $fm }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="work_status" class="form-select">
                    <option value="">All Status</option>
                    @foreach(\App\Models\DropdownOption::getOptions('work_status') as $ws)
                        <option value="{{ $ws->value }}" {{ request('work_status') == $ws->value ? 'selected' : '' }}>{{ $ws->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="need_part" class="form-select">
                    <option value="">All Parts Status</option>
                    <option value="1" {{ request('need_part') == '1' ? 'selected' : '' }}>Needs Parts</option>
                    <option value="0" {{ request('need_part') == '0' ? 'selected' : '' }}>No Parts Needed</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2"></i>Uninvoiced Jobs</span>
        <span class="badge bg-danger">{{ $jobs->total() }} records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="dataTable">
                <thead class="table-dark">
                    @php
                        $currentSort = request('sort', 'job_date');
                        $currentDir = request('dir', 'desc');
                        $sortMap = [
                            'job_number' => 'job_number',
                            'plate_number' => 'plate_number',
                            'service_advisor' => 'service_advisor',
                            'foreman' => 'foreman',
                            'job_date' => 'job_date',
                            'total_sales' => 'total_sales',
                            'labour_sales' => 'labour_sales',
                            'part_sales' => 'part_sales',
                            'work_status' => 'work_status',
                            'latest_remark_at' => 'latest_remark_at',
                        ];
                    @endphp
                    <tr id="headerRow">
                        @foreach([
                            'job_number' => 'WIP',
                            'franchise' => 'Franchise',
                            'plate_number' => 'Plate',
                            'customer_name' => 'Customer',
                            'service_advisor' => 'SA',
                            'foreman' => 'Foreman',
                            'job_date' => 'Date',
                            'total_sales' => 'Total Sales',
                            'labour_sales' => 'Labour',
                            'part_sales' => 'Parts',
                            'work_status' => 'Status',
                            'need_part' => 'Parts',
                            'latest_remark' => 'Last Remark',
                            'latest_remark_at' => 'Updated',
                        ] as $col => $label)
                            @php
                                $sortable = isset($sortMap[$col]);
                                $sortField = $sortMap[$col] ?? null;
                                $isActive = $sortable && $currentSort === $sortField;
                                $nextDir = $isActive && $currentDir === 'asc' ? 'desc' : 'asc';
                                $isHidden = in_array($col, ['franchise', 'customer_name', 'foreman', 'labour_sales', 'part_sales', 'need_part', 'latest_remark_at']);
                                $isNumeric = in_array($col, ['total_sales', 'labour_sales', 'part_sales']);
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
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                    <tr>
                        <td class="col-job_number"><a href="{{ route('jobs.show', $job) }}" class="fw-bold">{{ $job->job_number }}</a></td>
                        <td class="col-franchise d-none">{{ $job->franchise ?? '-' }}</td>
                        <td class="col-plate_number">{{ $job->plate_number }}</td>
                        <td class="col-customer_name d-none">{{ Str::limit($job->customer_name, 20) ?? '-' }}</td>
                        <td class="col-service_advisor">{{ $job->service_advisor ?? '-' }}</td>
                        <td class="col-foreman d-none">{{ $job->foreman ?? '-' }}</td>
                        <td class="col-job_date">{{ $job->job_date?->format('d/m/Y') }}</td>
                        <td class="col-total_sales text-end">{{ $job->total_sales ? number_format($job->total_sales, 0, ',', '.') : '-' }}</td>
                        <td class="col-labour_sales text-end d-none">{{ $job->labour_sales ? number_format($job->labour_sales, 0, ',', '.') : '-' }}</td>
                        <td class="col-part_sales text-end d-none">{{ $job->part_sales ? number_format($job->part_sales, 0, ',', '.') : '-' }}</td>
                        <td class="col-work_status"><span class="badge bg-secondary">{{ $job->work_status ?? 'Pending' }}</span></td>
                        <td class="col-need_part d-none">
                            @if($job->need_part)
                                <span class="badge bg-warning text-dark"><i class="bi bi-gear"></i> Yes</span>
                            @else
                                <span class="text-muted">No</span>
                            @endif
                        </td>
                        <td class="col-latest_remark text-truncate" style="max-width: 200px;">
                            @if($job->latest_remark && stripos($job->latest_remark, 'ORDER') !== false)
                                <span class="badge bg-warning text-dark me-1"><i class="bi bi-gear"></i></span>
                            @endif
                            {{ $job->latest_remark }}
                        </td>
                        <td class="col-latest_remark_at d-none">{{ $job->latest_remark_at?->format('d/m/Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="14" class="text-center text-muted py-4">No uninvoiced jobs found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $jobs->withQueryString()->links() }}
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
            // Save to localStorage
            localStorage.setItem('uninvoiced_col_' + column, this.checked ? '1' : '0');
        });
        
        // Restore from localStorage
        const saved = localStorage.getItem('uninvoiced_col_' + checkbox.dataset.column);
        if (saved !== null) {
            checkbox.checked = saved === '1';
            const cells = document.querySelectorAll('.col-' + checkbox.dataset.column);
            cells.forEach(cell => {
                cell.classList.toggle('d-none', saved !== '1');
            });
        }
    });

    // Column Resizing
    table.querySelectorAll('th').forEach(th => {
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
        function onMove(e) { 
            th.style.width = (startWidth + e.pageX - startX) + 'px'; 
        }
        function onUp() { 
            document.removeEventListener('mousemove', onMove); 
            document.removeEventListener('mouseup', onUp);
            // Save width to localStorage
            const col = th.dataset.col;
            if (col) {
                localStorage.setItem('uninvoiced_width_' + col, th.style.width);
            }
        }
    });
    
    // Restore saved widths
    table.querySelectorAll('th[data-col]').forEach(th => {
        const savedWidth = localStorage.getItem('uninvoiced_width_' + th.dataset.col);
        if (savedWidth) {
            th.style.width = savedWidth;
        }
    });
});

// Export function
function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('format', format);
    
    // Get visible columns
    const columns = [];
    document.querySelectorAll('.column-toggle:checked').forEach(cb => {
        columns.push(cb.dataset.column);
    });
    columns.forEach(c => params.append('columns[]', c));
    
    window.location.href = '{{ route("reports.export-uninvoiced") }}?' + params.toString();
}
</script>
@endpush


