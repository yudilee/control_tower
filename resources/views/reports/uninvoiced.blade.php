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
    if (request('franchise')) {
        $allUninvoicedJobs->where('franchise', request('franchise'));
    }
    if (request('department')) {
        $allUninvoicedJobs->where('department', request('department'));
    }
    if (request('service_advisor')) {
        $sa = request('service_advisor');
        if (is_array($sa)) {
            $allUninvoicedJobs->whereIn('service_advisor', $sa);
        } else {
            $allUninvoicedJobs->where('service_advisor', $sa);
        }
    }
    if (request('foreman')) {
        $fm = request('foreman');
        if (is_array($fm)) {
            $allUninvoicedJobs->whereIn('foreman', $fm);
        } else {
            $allUninvoicedJobs->where('foreman', $fm);
        }
    }
    if (request('work_status')) {
        $allUninvoicedJobs->where('work_status', request('work_status'));
    }
    if (request('need_part')) {
        $allUninvoicedJobs->where('need_part', request('need_part') == '1');
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
    
    // Work Status breakdown - normalize using Job model definitions
    // Get all defined work statuses from Job model
    $allWorkStatuses = \App\Models\Job::getWorkStatusOptions();
    
    // Create a lookup map: both value and label map to the same option
    $statusLookup = [];
    foreach ($allWorkStatuses as $status) {
        $statusLookup[strtolower(trim($status->value))] = $status;
        $statusLookup[strtolower(trim($status->label))] = $status;
    }
    
    // Get raw counts from database
    $rawCounts = (clone $allUninvoicedJobs)
        ->selectRaw('work_status, COUNT(*) as job_count, SUM(total_sales) as total')
        ->groupBy('work_status')
        ->get();
    
    // Aggregate counts by normalized status
    $aggregatedCounts = [];
    foreach ($rawCounts as $data) {
        $rawStatus = $data->work_status;
        if (empty($rawStatus)) {
            // Jobs with NULL work_status - assign to first status
            $firstStatus = $allWorkStatuses->first();
            if ($firstStatus) {
                $optionId = $firstStatus->id;
                if (!isset($aggregatedCounts[$optionId])) {
                    $aggregatedCounts[$optionId] = [
                        'option' => $firstStatus,
                        'job_count' => 0,
                        'total' => 0,
                    ];
                }
                $aggregatedCounts[$optionId]['job_count'] += $data->job_count;
                $aggregatedCounts[$optionId]['total'] += $data->total ?? 0;
            }
            continue;
        }
        
        // Normalize legacy status values to new format
        $normalizedStatus = \App\Models\Job::normalizeWorkStatus($rawStatus);
        $lookupKey = strtolower(trim($normalizedStatus));
        
        if (isset($statusLookup[$lookupKey])) {
            $option = $statusLookup[$lookupKey];
            $optionId = $option->id;
            
            if (!isset($aggregatedCounts[$optionId])) {
                $aggregatedCounts[$optionId] = [
                    'option' => $option,
                    'job_count' => 0,
                    'total' => 0,
                ];
            }
            $aggregatedCounts[$optionId]['job_count'] += $data->job_count;
            $aggregatedCounts[$optionId]['total'] += $data->total ?? 0;
        }
    }
    
    // Build the final list from defined statuses (maintaining order)
    $workStatusTotals = collect();
    foreach ($allWorkStatuses as $status) {
        $data = $aggregatedCounts[$status->id] ?? null;
        $workStatusTotals->push((object)[
            'work_status' => $status->label,
            'job_count' => $data ? $data['job_count'] : 0,
            'color' => $status->color,
            'icon' => $status->icon,
            'sort_order' => $status->sort_order,
        ]);
    }
    
    // Sort by sort_order first, then by job_count for items with same order
    $workStatusTotals = $workStatusTotals->sortBy('sort_order')->values();
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
                                'work_order_number' => ['label' => 'Work Order', 'default' => false],
                                'job_card' => ['label' => 'Job Card', 'default' => false],
                                'franchise' => ['label' => 'Franchise', 'default' => false],
                                'department' => ['label' => 'Dept', 'default' => false],
                                'plate_number' => ['label' => 'Plate No', 'default' => true],
                                'is_in_workshop' => ['label' => 'In Workshop', 'default' => true],
                                'first_reg' => ['label' => 'First Reg', 'default' => false],
                                'chassis_number' => ['label' => 'Chassis', 'default' => false],
                                'unit_type' => ['label' => 'Unit Type', 'default' => false],
                                'customer_name' => ['label' => 'Customer', 'default' => false],
                                'customer_address' => ['label' => 'Address', 'default' => false],
                                'account_no' => ['label' => 'Account No', 'default' => false],
                                'service_advisor' => ['label' => 'SA', 'default' => true],
                                'technician' => ['label' => 'Technician', 'default' => false],
                                'foreman' => ['label' => 'Foreman', 'default' => false],
                                'block' => ['label' => 'Block', 'default' => false],
                                'job_type' => ['label' => 'Job Type', 'default' => false],
                                'job_date' => ['label' => 'Job Date', 'default' => true],
                                'date_in' => ['label' => 'Date In', 'default' => false],
                                'promise_date' => ['label' => 'Promise Date', 'default' => false],
                                'date_out' => ['label' => 'Date Out', 'default' => false],
                                'total_sales' => ['label' => 'Total Sales', 'default' => true],
                                'estimated_amount' => ['label' => 'Est Amount', 'default' => false],
                                'labour_sales' => ['label' => 'Labour', 'default' => false],
                                'part_sales' => ['label' => 'Parts', 'default' => false],
                                'work_status' => ['label' => 'Work Status', 'default' => true],
                                'need_part' => ['label' => 'Need Part', 'default' => false],
                                'rq' => ['label' => 'RQ No', 'default' => false],
                                'latest_remark' => ['label' => 'Last Remark', 'default' => true],
                                'latest_remark_at' => ['label' => 'Remark Date', 'default' => false],
                                'job_description' => ['label' => 'Description', 'default' => false],
                            ];
                            
                            // Load User Preferences
                            $defaultPrefs = [
                                 'columns' => array_map(fn($col) => $col['default'], $allColumns),
                                 'order' => array_keys($allColumns),
                                 'widths' => [],
                                 'sort' => 'created_at',
                                 'dir' => 'desc'
                            ];
                            
                            $storedPrefs = auth()->user()?->uninvoiced_preferences ?? [];
                            $storedColumns = isset($storedPrefs['columns']) ? $storedPrefs['columns'] : [];
                            $userPrefs = array_merge($defaultPrefs['columns'], $storedColumns);
        
                            $storedOrder = $storedPrefs['order'] ?? [];
                            $allKeys = array_keys($allColumns);
                            $missingKeys = array_diff($allKeys, $storedOrder);
                            $userOrder = !empty($storedOrder) ? array_merge($storedOrder, $missingKeys) : $defaultPrefs['order'];
                            
                            $userWidths = $storedPrefs['widths'] ?? [];
                        @endphp
                        <!-- Toggles injected via JS -->
                        <div id="columnToggles"></div>
                        <div class="dropdown-divider"></div>
                        <li class="px-2 pb-2 d-flex gap-2">
                             <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="saveColumnsBtn">Save</button>
                             <button type="button" class="btn btn-outline-danger btn-sm" id="resetColumnsBtn">Reset</button>
                        </li>
                    </ul>
                </div>
            </div>
            
            <style>
                #dataTable thead th {
                    position: sticky;
                    top: 0;
                    z-index: 10;
                    background: #212529;
                }
                #dataTable th, #dataTable td {
                    overflow: hidden;
                    text-overflow: ellipsis;
                    max-width: 200px;
                    padding: 0.4rem 0.5rem;
                }
                #dataTable th {
                    min-width: 80px;
                }
                .table-responsive::-webkit-scrollbar {
                    height: 10px;
                    width: 10px;
                }
                .table-responsive::-webkit-scrollbar-thumb {
                    background: #888;
                    border-radius: 5px;
                }
                .table-responsive::-webkit-scrollbar-track {
                    background: #f1f1f1;
                }
            </style>
            
            <!-- Additional Filters Row -->
            <div class="col-md-2">
                <select name="franchise" class="form-select">
                    <option value="">All Franchises</option>
                    <option value="PC" {{ request('franchise') == 'PC' ? 'selected' : '' }}>PC</option>
                    <option value="CV" {{ request('franchise') == 'CV' ? 'selected' : '' }}>CV</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="department" class="form-select">
                    <option value="">All Dept</option>
                    @foreach(\App\Models\Job::uninvoiced()->whereNotNull('department')->distinct()->pluck('department')->sort() as $dept)
                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>
                            {{ $dept === 'W' ? 'Workshop' : ($dept === 'B' ? 'Body Paint' : $dept) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="service_advisor[]" class="form-select" multiple size="3" aria-label="Service Advisor">
                    <option value="" {{ empty(request('service_advisor')) ? 'selected' : '' }}>All SA</option>
                    @foreach(\App\Models\Job::whereNotNull('service_advisor')->distinct()->pluck('service_advisor')->sort() as $sa)
                        @php
                            $selectedSAs = request('service_advisor');
                            if (!is_array($selectedSAs)) $selectedSAs = $selectedSAs ? [$selectedSAs] : [];
                            $isSelected = in_array($sa, $selectedSAs);
                        @endphp
                        <option value="{{ $sa }}" {{ $isSelected ? 'selected' : '' }}>{{ $sa }}</option>
                    @endforeach
                </select>
                <div class="form-text small mt-0 text-muted">Hold Ctrl to select multiple</div>
            </div>
            <div class="col-md-2">
                <select name="foreman[]" class="form-select" multiple size="3" aria-label="Foreman">
                    <option value="" {{ empty(request('foreman')) ? 'selected' : '' }}>All Foreman</option>
                    @foreach(\App\Models\Job::whereNotNull('foreman')->distinct()->pluck('foreman')->sort() as $fm)
                        @php
                            $selectedFMs = request('foreman');
                            if (!is_array($selectedFMs)) $selectedFMs = $selectedFMs ? [$selectedFMs] : [];
                            $isSelected = in_array($fm, $selectedFMs);
                        @endphp
                        <option value="{{ $fm }}" {{ $isSelected ? 'selected' : '' }}>{{ $fm }}</option>
                    @endforeach
                </select>
                <div class="form-text small mt-0 text-muted">Hold Ctrl to select multiple</div>
            </div>
            <div class="col-md-2">
                <select name="work_status" class="form-select">
                    <option value="">All Status</option>
                    @foreach(\App\Models\Job::getWorkStatusOptions() as $ws)
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
        <div class="table-responsive" style="max-height: 70vh; overflow: auto;">
            <table class="table table-hover table-sm mb-0" id="dataTable" style="white-space: nowrap;">
                <thead class="table-dark">
                    @php
                        $currentSort = request('sort', 'job_date');
                        $currentDir = request('dir', 'desc');
                        $sortMap = [
                            'job_number' => 'job_number',
                            'franchise' => 'franchise',
                            'department' => 'department',
                            'plate_number' => 'plate_number',
                            'first_reg' => 'date_first_reg',
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
                            'work_order_number' => 'Work Order',
                            'job_card' => 'Job Card',
                            'franchise' => 'Franchise',
                            'department' => 'Dept',
                            'plate_number' => 'Plate',
                            'is_in_workshop' => 'In Workshop',
                            'first_reg' => 'First Reg',
                            'chassis_number' => 'Chassis',
                            'unit_type' => 'Unit Type',
                            'customer_name' => 'Customer',
                            'customer_address' => 'Address',
                            'account_no' => 'Account No',
                            'service_advisor' => 'SA',
                            'technician' => 'Technician',
                            'foreman' => 'Foreman',
                            'block' => 'Block',
                            'job_type' => 'Job Type',
                            'job_date' => 'Date',
                            'date_in' => 'Date In',
                            'promise_date' => 'Promise Date',
                            'date_out' => 'Date Out',
                            'total_sales' => 'Total Sales',
                            'estimated_amount' => 'Est Amount',
                            'labour_sales' => 'Labour',
                            'part_sales' => 'Parts',
                            'work_status' => 'Status',
                            'need_part' => 'Parts',
                            'rq' => 'RQ No',
                            'latest_remark' => 'Last Remark',
                            'latest_remark_at' => 'Updated',
                            'job_description' => 'Description',
                        ] as $col => $label)
                            @php
                                $sortable = isset($sortMap[$col]);
                                $sortField = $sortMap[$col] ?? null;
                                $isActive = $sortable && $currentSort === $sortField;
                                $nextDir = $isActive && $currentDir === 'asc' ? 'desc' : 'asc';
                                $alwaysVisible = ['job_number', 'plate_number', 'service_advisor', 'job_date', 'total_sales', 'work_status', 'latest_remark'];
                                // Hide everything else by default unless it was in the original default list
                                // Actually, better to rely on the 'd-none' class being applied based on a list of visible columns logic
                                // But here we use a static list for 'IsHidden' check to apply d-none initially
                                $defaultVisible = ['job_number', 'plate_number', 'service_advisor', 'job_date', 'total_sales', 'work_status', 'latest_remark'];
                                $isHidden = !in_array($col, $defaultVisible);
                                $isNumeric = in_array($col, ['total_sales', 'labour_sales', 'part_sales', 'estimated_amount']);
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
                        <td class="col-work_order_number d-none">{{ $job->work_order_number ?? '-' }}</td>
                        <td class="col-job_card d-none">{{ $job->job_card ?? '-' }}</td>
                        <td class="col-franchise d-none">{{ $job->franchise ?? '-' }}</td>
                        <td class="col-department d-none">{{ $job->department_label ?? '-' }}</td>
                        <td class="col-plate_number">{{ $job->plate_number }}</td>
                        <td class="col-is_in_workshop">
                            @if($job->is_in_workshop)
                                <span class="badge bg-success"><i class="bi bi-house-door"></i> Yes</span>
                            @else
                                <span class="text-muted">No</span>
                            @endif
                        </td>
                        <td class="col-first_reg d-none">{{ $job->date_first_reg?->format('d/m/Y') ?? '-' }}</td>
                        <td class="col-chassis_number d-none">{{ $job->chassis_number ?? '-' }}</td>
                        <td class="col-unit_type d-none">{{ $job->unit_type ?? '-' }}</td>
                        <td class="col-customer_name d-none">{{ Str::limit($job->customer_name, 20) ?? '-' }}</td>
                        <td class="col-customer_address d-none">{{ Str::limit($job->customer_address, 30) ?? '-' }}</td>
                        <td class="col-account_no d-none">{{ $job->account_no ?? '-' }}</td>
                        <td class="col-service_advisor">{{ $job->service_advisor ?? '-' }}</td>
                        <td class="col-technician d-none">{{ $job->technician ?? '-' }}</td>
                        <td class="col-foreman d-none">{{ $job->foreman ?? '-' }}</td>
                        <td class="col-block d-none">{{ $job->block ?? '-' }}</td>
                        <td class="col-job_type d-none">{{ $job->job_type ?? '-' }}</td>
                        <td class="col-job_date">{{ $job->job_date?->format('d/m/Y') }}</td>
                        <td class="col-date_in d-none">{{ $job->date_in?->format('d/m/Y') }}</td>
                        <td class="col-promise_date d-none">{{ $job->promise_date?->format('d/m/Y') }}</td>
                        <td class="col-date_out d-none">{{ $job->date_out?->format('d/m/Y') }}</td>
                        <td class="col-total_sales text-end">{{ $job->total_sales ? number_format($job->total_sales, 0, ',', '.') : '-' }}</td>
                        <td class="col-estimated_amount text-end d-none">{{ $job->estimated_amount ? number_format($job->estimated_amount, 0, ',', '.') : '-' }}</td>
                        <td class="col-labour_sales text-end d-none">{{ $job->labour_sales ? number_format($job->labour_sales, 0, ',', '.') : '-' }}</td>
                        <td class="col-part_sales text-end d-none">{{ $job->part_sales ? number_format($job->part_sales, 0, ',', '.') : '-' }}</td>
                        <td class="col-work_status"><x-work-status :value="$job->work_status" /></td>
                        <td class="col-need_part d-none">
                            @if($job->need_part)
                                <span class="badge bg-warning text-dark"><i class="bi bi-gear"></i> Yes</span>
                            @else
                                <span class="text-muted">No</span>
                            @endif
                        </td>
                        <td class="col-rq d-none">{{ $job->rq ?? '-' }}</td>
                        <td class="col-latest_remark text-truncate" style="max-width: 200px;">
                            @if($job->latest_remark && stripos($job->latest_remark, 'ORDER') !== false)
                                <span class="badge bg-warning text-dark me-1"><i class="bi bi-gear"></i></span>
                            @endif
                            {{ $job->latest_remark }}
                        </td>
                        <td class="col-latest_remark_at d-none">{{ $job->latest_remark_at?->format('d/m/Y') }}</td>
                        <td class="col-job_description d-none text-truncate" style="max-width: 200px;">{{ $job->job_description ?? '-' }}</td>
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
        const allColumns = @json($allColumns);
        const userPrefs = @json($userPrefs);
        let userOrder = @json($userOrder);
        const userWidths = @json($userWidths);
        
        const table = document.getElementById('dataTable');
        const headerRow = document.getElementById('headerRow');
        const container = document.getElementById('columnToggles');
        
        // 1. Apply Order
        function applyColumnOrder(order) {
            // Reorder Header
            order.forEach((col) => {
                const th = headerRow.querySelector(`th[data-col="${col}"]`);
                if (th) headerRow.appendChild(th);
            });
            
            // Reorder Body
            document.querySelectorAll('#dataTable tbody tr').forEach(row => {
                order.forEach(col => {
                    const td = row.querySelector(`.col-${col}`);
                    if (td) row.appendChild(td);
                });
            });
        }
        if (userOrder.length > 0) applyColumnOrder(userOrder);

        // 2. Apply Widths
        Object.keys(userWidths).forEach(col => {
            const th = table.querySelector(`th[data-col="${col}"]`);
            if(th) th.style.width = userWidths[col];
        });

        // 3. Build Toggles
        function buildToggles() {
            container.innerHTML = '';
            userOrder.forEach(key => {
                if (!allColumns[key]) return;
                const label = allColumns[key].label;
                
                const div = document.createElement('div');
                div.className = 'form-check d-flex align-items-center py-1 dropdown-item';
                div.draggable = true;
                div.dataset.col = key;
                div.onclick = function(e) { e.stopPropagation(); };
                
                div.innerHTML = `
                    <i class="bi bi-grip-vertical text-muted me-2" style="cursor: grab;"></i>
                    <input class="form-check-input col-toggle" type="checkbox" value="${key}" id="col_${key}" ${userPrefs[key] ? 'checked' : ''}>
                    <label class="form-check-label ms-1 small" for="col_${key}">${label}</label>
                `;
                container.appendChild(div);
            });
            setupDragDrop();
        }
        buildToggles();

        // 4. Drag and Drop for Toggles
        function setupDragDrop() {
            let draggedEl = null;
            container.querySelectorAll('[draggable]').forEach(el => {
                el.addEventListener('dragstart', e => { 
                    draggedEl = el; 
                    el.classList.add('opacity-50'); 
                    e.dataTransfer.effectAllowed = 'move'; 
                });
                el.addEventListener('dragend', e => { 
                    el.classList.remove('opacity-50'); 
                    container.querySelectorAll('.drag-over').forEach(x => x.classList.remove('drag-over', 'border-top', 'border-primary')); 
                    draggedEl = null; 
                });
                el.addEventListener('dragover', e => { 
                    e.preventDefault(); 
                    e.dataTransfer.dropEffect = 'move'; 
                    el.classList.add('drag-over', 'border-top', 'border-primary'); 
                });
                el.addEventListener('dragleave', e => { 
                    el.classList.remove('drag-over', 'border-top', 'border-primary'); 
                });
                el.addEventListener('drop', e => { 
                    e.preventDefault(); 
                    el.classList.remove('drag-over', 'border-top', 'border-primary'); 
                    if (draggedEl && draggedEl !== el) { 
                        container.insertBefore(draggedEl, el); 
                        updateOrderFromDOM();
                    } 
                });
            });
        }

        function updateOrderFromDOM() {
            userOrder = [];
            container.querySelectorAll('[data-col]').forEach(el => userOrder.push(el.dataset.col));
            applyColumnOrder(userOrder);
        }

        // 5. Apply Visibility
        function applyVisibility() {
            document.querySelectorAll('.col-toggle').forEach(toggle => {
                const colName = toggle.value;
                const visible = toggle.checked;
                
                const th = table.querySelector(`th[data-col="${colName}"]`);
                if(th) {
                    if (visible) th.classList.remove('d-none');
                    else th.classList.add('d-none');
                }
                
                // Toggle body cells using class selector
                table.querySelectorAll(`.col-${colName}`).forEach(td => {
                    if (visible) td.classList.remove('d-none');
                    else td.classList.add('d-none');
                });
            });
        }
        applyVisibility();
        
        container.addEventListener('change', function(e) {
            if (e.target.classList.contains('col-toggle')) {
                applyVisibility();
            }
        });

        // 6. Resizing Logic
        table.querySelectorAll('th').forEach(th => {
            const resizer = document.createElement('div');
            resizer.className = 'column-resizer';
            resizer.style.cssText = 'width:8px;height:100%;position:absolute;right:0;top:0;cursor:col-resize;user-select:none;touch-action:none;z-index:100;';
            th.appendChild(resizer);
            th.style.position = 'relative';

            let startX, startWidth;
            resizer.addEventListener('mousedown', initResize);
            
            function initResize(e) {
                e.stopPropagation();
                startX = e.clientX;
                startWidth = th.offsetWidth;
                document.documentElement.addEventListener('mousemove', doResize);
                document.documentElement.addEventListener('mouseup', stopResize);
            }
            function doResize(e) {
                th.style.width = (startWidth + e.clientX - startX) + 'px';
            }
            function stopResize() {
                document.documentElement.removeEventListener('mousemove', doResize);
                document.documentElement.removeEventListener('mouseup', stopResize);
            }
        });

        // 7. Save Preferences
        document.getElementById('saveColumnsBtn').addEventListener('click', function() {
            const prefs = {};
            document.querySelectorAll('.col-toggle').forEach(t => prefs[t.value] = t.checked);
            
            const widths = {};
            table.querySelectorAll('th').forEach(th => { 
                if(th.dataset.col && th.style.width) widths[th.dataset.col] = th.style.width; 
            });
            
            const order = [];
            container.querySelectorAll('[data-col]').forEach(el => order.push(el.dataset.col));

            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
            btn.disabled = true;

            fetch('{{ route("preferences.columns") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ 
                    columns: prefs, 
                    widths: widths, 
                    order: order, 
                    table: 'uninvoiced' 
                })
            }).then(res => {
                if (res.status === 419) {
                    alert('Session expired. Please reload.');
                    return { success: false };
                }
                if (!res.ok) throw new Error('Failed to save');
                return res.json();
            }).then(data => {
                if(data.success) {
                    btn.innerHTML = '<i class="bi bi-check"></i> Saved!';
                    btn.className = 'btn btn-success btn-sm w-100';
                    setTimeout(() => { 
                        btn.innerHTML = originalText; 
                        btn.className = 'btn btn-primary btn-sm w-100'; 
                        btn.disabled = false;
                    }, 2000);
                }
            }).catch(err => {
                alert('Error saving preferences');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });

        // 8. Reset Preferences
        const defaultOrder = Object.keys(allColumns);
        const defaultWidths = {};
        
        document.getElementById('resetColumnsBtn').addEventListener('click', function() {
             if(!confirm('Reset table columns to default?')) return;
             
             // Reset variables
             userOrder = [...defaultOrder];
             
             // Reset UI
             applyColumnOrder(userOrder);
             buildToggles();
             
             // Reset Checks
             document.querySelectorAll('.col-toggle').forEach(t => {
                 t.checked = allColumns[t.value].default;
             });
             applyVisibility();
             
             // Reset Widths
             table.querySelectorAll('th').forEach(th => th.style.width = '');
             
             // Save defaults
             document.getElementById('saveColumnsBtn').click();
        });
    });

    // Export function update to use .col-toggle
    function exportReport(format) {
        const params = new URLSearchParams(window.location.search);
        params.set('format', format);
        
        const columns = [];
        document.querySelectorAll('.col-toggle:checked').forEach(cb => {
            columns.push(cb.value); // .value has the column key
        });
        columns.forEach(c => params.append('columns[]', c));
        
        window.location.href = '{{ route("reports.export-uninvoiced") }}?' + params.toString();
    }
</script>
@endpush


