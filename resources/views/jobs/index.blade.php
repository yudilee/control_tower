@extends('layouts.app')

@section('title', 'All Jobs')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-clipboard-check me-2"></i>All Jobs</h1>
        <p class="text-muted">Total: {{ $jobs->total() }} jobs</p>
    </div>
    <a href="{{ route('jobs.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Add Job
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-center" id="searchForm">
            <div class="col-md-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search WIP, Plate..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="franchise" class="form-select form-select-sm">
                    <option value="">All Franchise</option>
                    <option value="PC" {{ request('franchise') == 'PC' ? 'selected' : '' }}>PC</option>
                    <option value="CV" {{ request('franchise') == 'CV' ? 'selected' : '' }}>CV</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="uninvoiced" {{ request('status') == 'uninvoiced' ? 'selected' : '' }}>Uninvoiced</option>
                    <option value="invoiced" {{ request('status') == 'invoiced' ? 'selected' : '' }}>Invoiced</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
                <a href="{{ route('jobs.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                
                @auth
                <div class="dropdown">
                    <button class="btn btn-outline-dark btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-layout-three-columns"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 240px; max-height: 500px; overflow-y: auto;">
                        <h6 class="dropdown-header">Visible Columns</h6>
                        <div id="columnToggles"></div>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="btn btn-primary btn-sm w-100" id="saveColumnsBtn">Save</button>
                    </div>
                </div>
                @endauth
            </div>
        </form>
        
        <!-- Additional Filters Row -->
        <form method="GET" class="row g-2 align-items-center mt-2" id="advancedFilters">
            <!-- Preserve existing params -->
            <input type="hidden" name="search" value="{{ request('search') }}">
            <input type="hidden" name="franchise" value="{{ request('franchise') }}">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <input type="hidden" name="date_from" value="{{ request('date_from') }}">
            <input type="hidden" name="date_to" value="{{ request('date_to') }}">
            
            <div class="col-md-2">
                <select name="filter_work_status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Work Status</option>
                    @foreach(\App\Models\Job::getWorkStatusOptions() as $opt)
                    <option value="{{ $opt->value }}" {{ request('filter_work_status') == $opt->value ? 'selected' : '' }}>{{ $opt->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="filter_service_advisor" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Service Advisors</option>
                    @foreach($filterOptions['service_advisor'] ?? [] as $sa)
                    <option value="{{ $sa }}" {{ request('filter_service_advisor') == $sa ? 'selected' : '' }}>{{ $sa }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="filter_foreman" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Foreman</option>
                    @foreach($filterOptions['foreman'] ?? [] as $fm)
                    <option value="{{ $fm }}" {{ request('filter_foreman') == $fm ? 'selected' : '' }}>{{ $fm }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="need_part" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Parts Status</option>
                    <option value="1" {{ request('need_part') == '1' ? 'selected' : '' }}>Needs Parts</option>
                    <option value="0" {{ request('need_part') == '0' ? 'selected' : '' }}>No Parts Needed</option>
                </select>
            </div>
            @if(request()->hasAny(['filter_work_status', 'filter_service_advisor', 'filter_foreman', 'need_part']))
            <div class="col-auto">
                <a href="{{ route('jobs.index', request()->only(['search', 'franchise', 'status', 'date_from', 'date_to'])) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-circle"></i> Clear Filters
                </a>
            </div>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 70vh; overflow: auto;">
            <table class="table table-hover table-bordered table-sm mb-0" id="dataTable" style="white-space: nowrap;">
                <thead class="table-dark" style="position: sticky; top: 0; z-index: 10;">
                    @php
                        $storedPrefs = auth()->user()?->column_preferences ?? [];
                        $userSort = $storedPrefs['sort'] ?? 'created_at';
                        $userDir = $storedPrefs['dir'] ?? 'desc';
                        $currentSort = request('sort', $userSort);
                        $currentDir = request('dir', $userDir);
                        $sortMap = [
                            'wip' => 'job_number',
                            'job_card' => 'job_card',
                            'job_date' => 'job_date',
                            'date_in' => 'date_in',
                            'date_out' => 'date_out',
                            'reg_no' => 'plate_number',
                            'customer' => 'customer_name',
                            'sa' => 'service_advisor',
                            'foreman' => 'foreman',
                            'labour' => 'labour_sales',
                            'part' => 'part_sales',
                            'total' => 'total_sales',
                            'inv_date' => 'invoice_date',
                            'inv_amt' => 'inv_amount',
                            'dept' => 'department',
                            'check_in' => 'check_in_time',
                            'deadline' => 'deadline',
                            'promise' => 'promise_date',
                            'chassis' => 'chassis_number',
                            'unit' => 'unit_type',
                            'account' => 'account_no',
                            'first_reg' => 'date_first_reg',
                            'technician' => 'technician',
                            'block' => 'block',
                            'job_type' => 'job_type',
                            'pay_type' => 'payment_type',
                            'type_sale' => 'type_sale',
                            'job_desc' => 'job_description',
                            'work_status' => 'work_status',
                            'estimated' => 'estimated_amount',
                            'rq' => 'rq',
                            'order_part' => 'no_order_part_mbina',
                            'lain_lain' => 'lain_lain',
                            'need_part' => 'need_part',
                            'inv_no' => 'invoice_number',
                            'inv_ppn' => 'inv_ppn',
                            'inv_total' => 'inv_ppn_meterai',
                            'last_updated' => 'latest_remark_at',
                            'status' => 'status',
                        ];
                        // All columns - grouped logically
                        $allColumns = [
                            // Core identifiers
                            'wip' => 'WIP',
                            'job_card' => 'Job Card',
                            'dept' => 'Dept',
                            // Dates
                            'job_date' => 'Job Date',
                            'date_in' => 'Date In',
                            'date_out' => 'Date Out',
                            'check_in' => 'Check In',
                            'deadline' => 'Deadline',
                            'promise' => 'Promise Date',
                            // Vehicle
                            'reg_no' => 'Reg No',
                            'chassis' => 'Chassis',
                            'unit' => 'Unit Type',
                            'account' => 'Account No',
                            'first_reg' => 'First Reg',
                            // Customer
                            'customer' => 'Customer',
                            'address' => 'Address',
                            // Personnel
                            'sa' => 'SA',
                            'foreman' => 'Foreman',
                            'technician' => 'Technician',
                            'block' => 'Block',
                            // Job info
                            'job_type' => 'Job Type',
                            'pay_type' => 'Payment Type',
                            'type_sale' => 'Type Sale',
                            'job_desc' => 'Job Desc',
                            'work_status' => 'Work Status',
                            // Sales
                            'labour' => 'Labour',
                            'part' => 'Part',
                            'total' => 'Total',
                            'estimated' => 'Estimated',
                            // Parts/Orders
                            'rq' => 'RQ',
                            'order_part' => 'Order Part',
                            'lain_lain' => 'Lain-lain',
                            'need_part' => 'Needs Parts',
                            // Invoice
                            'inv_no' => 'Invoice #',
                            'inv_date' => 'Inv Date',
                            'inv_amt' => 'Inv Amt',
                            'inv_ppn' => 'Inv+PPN',
                            'inv_total' => 'Inv+Meterai',
                            // Remarks & Status
                            'first_remark' => 'First Remark',
                            'update_remark' => 'Latest Remark',
                            'last_updated' => 'Remark Updated',
                            'status' => 'Status',
                            'action' => 'Action',
                        ];
                        // Filterable columns map (col alias => filter param name)
                        $filterMap = [
                            'sa' => 'service_advisor',
                            'foreman' => 'foreman',
                            'dept' => 'department',
                            'work_status' => 'work_status',
                            'block' => 'block',
                            'technician' => 'technician',
                            'job_type' => 'job_type',
                            'pay_type' => 'payment_type',
                            'need_part' => 'need_part',
                        ];
                    @endphp
                    <tr id="headerRow">
                        <th data-col="no">#</th>
                        @foreach($allColumns as $col => $label)
                            @php
                                $sortable = isset($sortMap[$col]);
                                $sortField = $sortMap[$col] ?? null;
                                $isActive = $sortable && $currentSort === $sortField;
                                $nextDir = $isActive && $currentDir === 'asc' ? 'desc' : 'asc';
                                $filterable = isset($filterMap[$col]);
                                $filterParam = $filterMap[$col] ?? null;
                                $activeFilter = $filterable ? request("filter_{$filterParam}") : null;
                            @endphp
                            <th data-col="{{ $col }}" @if($sortable) style="cursor: pointer;" @endif>
                                <div class="d-flex align-items-center gap-1">
                                    @if($sortable)
                                        <a href="{{ request()->fullUrlWithQuery(['sort' => $sortField, 'dir' => $nextDir]) }}" class="text-white text-decoration-none flex-grow-1 d-flex align-items-center">
                                            {{ $label }}
                                            @if($isActive)
                                                <i class="bi bi-arrow-{{ $currentDir === 'asc' ? 'up' : 'down' }} ms-1"></i>
                                            @else
                                                <i class="bi bi-arrow-down-up ms-1 opacity-25"></i>
                                            @endif
                                        </a>
                                    @else
                                        <span class="flex-grow-1">{{ $label }}</span>
                                    @endif
                                    @if($filterable && isset($filterOptions[$filterParam]))
                                        <div class="dropdown">
                                            <button class="btn btn-link btn-sm p-0 text-white {{ $activeFilter ? 'text-warning' : 'opacity-50' }}" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" onclick="event.stopPropagation()">
                                                <i class="bi bi-funnel{{ $activeFilter ? '-fill' : '' }}"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 150px; max-height: 250px; overflow-y: auto;">
                                                <a href="{{ request()->fullUrlWithQuery(["filter_{$filterParam}" => null]) }}" class="dropdown-item small {{ !$activeFilter ? 'active' : '' }}">All</a>
                                                <div class="dropdown-divider"></div>
                                                @foreach($filterOptions[$filterParam] as $option)
                                                    <a href="{{ request()->fullUrlWithQuery(["filter_{$filterParam}" => $option]) }}" class="dropdown-item small {{ $activeFilter == $option ? 'active' : '' }}">{{ $option ?: '(empty)' }}</a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @elseif($col === 'need_part')
                                        <div class="dropdown">
                                            <button class="btn btn-link btn-sm p-0 text-white {{ request('filter_need_part') ? 'text-warning' : 'opacity-50' }}" type="button" data-bs-toggle="dropdown" onclick="event.stopPropagation()">
                                                <i class="bi bi-funnel{{ request('filter_need_part') ? '-fill' : '' }}"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end p-2">
                                                <a href="{{ request()->fullUrlWithQuery(['filter_need_part' => null]) }}" class="dropdown-item small {{ !request('filter_need_part') ? 'active' : '' }}">All</a>
                                                <a href="{{ request()->fullUrlWithQuery(['filter_need_part' => '1']) }}" class="dropdown-item small {{ request('filter_need_part') === '1' ? 'active' : '' }}">Yes</a>
                                                <a href="{{ request()->fullUrlWithQuery(['filter_need_part' => '0']) }}" class="dropdown-item small {{ request('filter_need_part') === '0' ? 'active' : '' }}">No</a>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody id="tableBody">
                    @forelse($jobs as $index => $job)
                    <tr onclick="window.location='{{ route('jobs.show', $job) }}'" style="cursor: pointer;">
                        <td data-col="no">{{ $jobs->firstItem() + $index }}</td>
                        {{-- Core identifiers --}}
                        <td data-col="wip">
                            <span class="fw-bold text-primary">{{ $job->job_number }}</span>
                            <span class="badge {{ $job->franchise == 'CV' ? 'bg-info' : 'bg-secondary' }} ms-1">{{ $job->franchise }}</span>
                        </td>
                        <td data-col="job_card">{{ $job->job_card }}</td>
                        <td data-col="dept">{{ $job->department }}</td>
                        {{-- Dates --}}
                        <td data-col="job_date"><small>{{ $job->job_date?->format('d/m/y') }}</small></td>
                        <td data-col="date_in"><small>{{ $job->date_in?->format('d/m/y') }}</small></td>
                        <td data-col="date_out"><small>{{ $job->date_out?->format('d/m/y') }}</small></td>
                        <td data-col="check_in"><small>{{ $job->check_in_time }}</small></td>
                        <td data-col="deadline"><small>{{ $job->deadline?->format('d/m/y') }}</small></td>
                        <td data-col="promise"><small>{{ $job->promise_date?->format('d/m/y') }}</small></td>
                        {{-- Vehicle --}}
                        <td data-col="reg_no">{{ $job->plate_number }}</td>
                        <td data-col="chassis"><small>{{ $job->chassis_number }}</small></td>
                        <td data-col="unit">{{ $job->unit_type }}</td>
                        <td data-col="account">{{ $job->account_no }}</td>
                        <td data-col="first_reg"><small>{{ $job->date_first_reg?->format('d/m/y') }}</small></td>
                        {{-- Customer --}}
                        <td data-col="customer" class="text-truncate" style="max-width: 120px;">{{ $job->customer_name }}</td>
                        <td data-col="address" class="text-truncate" style="max-width: 150px;"><small>{{ Str::limit($job->customer_address, 30) }}</small></td>
                        {{-- Personnel --}}
                        <td data-col="sa">{{ $job->service_advisor }}</td>
                        <td data-col="foreman">{{ $job->foreman }}</td>
                        <td data-col="technician">{{ $job->technician }}</td>
                        <td data-col="block">{{ $job->block }}</td>
                        {{-- Job info --}}
                        <td data-col="job_type">{{ $job->job_type }}</td>
                        <td data-col="pay_type">{{ $job->payment_type }}</td>
                        <td data-col="type_sale">{{ $job->type_sale }}</td>
                        <td data-col="job_desc" class="text-truncate" style="max-width: 100px;"><small>{{ Str::limit($job->job_description, 20) }}</small></td>
                        <td data-col="work_status"><x-work-status :value="$job->work_status" /></td>
                        {{-- Sales --}}
                        <td data-col="labour" class="text-end"><small>{{ $job->labour_sales ? number_format($job->labour_sales, 0, ',', '.') : '-' }}</small></td>
                        <td data-col="part" class="text-end"><small>{{ $job->part_sales ? number_format($job->part_sales, 0, ',', '.') : '-' }}</small></td>
                        <td data-col="total" class="text-end fw-bold">{{ $job->total_sales ? number_format($job->total_sales, 0, ',', '.') : '-' }}</td>
                        <td data-col="estimated" class="text-end"><small>{{ $job->estimated_amount ? number_format($job->estimated_amount, 0, ',', '.') : '-' }}</small></td>
                        {{-- Parts/Orders --}}
                        <td data-col="rq">{{ $job->rq }}</td>
                        <td data-col="order_part">{{ $job->no_order_part_mbina }}</td>
                        <td data-col="lain_lain" class="text-truncate" style="max-width: 80px;"><small>{{ Str::limit($job->lain_lain, 15) }}</small></td>
                        <td data-col="need_part" class="text-center">
                            @if($job->need_part)
                                <span class="badge bg-warning text-dark" title="Needs Parts"><i class="bi bi-exclamation-triangle"></i></span>
                            @else
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted opacity-50 need-part-toggle" 
                                        data-job-id="{{ $job->id }}"
                                        data-job-wip="{{ $job->job_number }}"
                                        title="Click to mark as Needs Parts">
                                    <i class="bi bi-plus-circle"></i>
                                </button>
                            @endif
                        </td>
                        {{-- Invoice --}}
                        <td data-col="inv_no">{{ $job->invoice_number }}</td>
                        <td data-col="inv_date"><small>{{ $job->invoice_date?->format('d/m/y') }}</small></td>
                        <td data-col="inv_amt" class="text-end"><small>{{ $job->inv_amount ? number_format($job->inv_amount, 0, ',', '.') : '-' }}</small></td>
                        <td data-col="inv_ppn" class="text-end"><small>{{ $job->inv_ppn ? number_format($job->inv_ppn, 0, ',', '.') : '-' }}</small></td>
                        <td data-col="inv_total" class="text-end"><small>{{ $job->inv_ppn_meterai ? number_format($job->inv_ppn_meterai, 0, ',', '.') : '-' }}</small></td>
                        {{-- Remarks & Status --}}
                        <td data-col="first_remark" class="text-truncate" style="max-width: 120px;"><small>{{ Str::limit($job->first_remark_text, 25) }}</small></td>
                        <td data-col="update_remark" class="text-truncate" style="max-width: 120px;"><small>{{ Str::limit($job->update_remark_text, 25) }}</small></td>
                        <td data-col="last_updated"><small>{{ $job->last_remark_updated?->format('d/m/y H:i') }}</small></td>
                        <td data-col="status" onclick="event.stopPropagation()">
                            @if($job->status == 'uninvoiced')
                                <span class="badge bg-warning text-dark">Open</span>
                            @else
                                <span class="badge bg-success">Inv</span>
                            @endif
                        </td>
                        <td data-col="action" onclick="event.stopPropagation()" class="text-center">
                            @php
                                $canAddRemark = false;
                                $user = auth()->user();
                                if ($user->hasAnyRole(['admin', 'manager', 'control_tower'])) {
                                    $canAddRemark = true;
                                } elseif ($user->hasRole('sa')) {
                                    $canAddRemark = $user->serviceAdvisor?->name === $job->service_advisor;
                                } elseif ($user->hasRole('foreman')) {
                                    $canAddRemark = $user->foreman?->name === $job->foreman;
                                } elseif ($user->hasRole('sparepart')) {
                                    $canAddRemark = $job->need_part;
                                }
                                $canAddPartOrder = $job->need_part && in_array($user->role, ['sparepart', 'admin']);
                            @endphp
                            <div class="btn-group btn-group-sm">
                                @if($canAddRemark)
                                <button type="button" class="btn btn-outline-primary btn-add-remark" 
                                        data-job-id="{{ $job->id }}" 
                                        data-job-number="{{ $job->job_number }}"
                                        title="Add Remark">
                                    <i class="bi bi-chat-text"></i>
                                </button>
                                @endif
                                @if($canAddPartOrder)
                                <a href="{{ route('part-orders.create', ['job_id' => $job->id]) }}" 
                                   class="btn btn-outline-warning" 
                                   title="Add Part Order">
                                    <i class="bi bi-box-seam"></i>
                                </a>
                                @endif
                            </div>
                            @if(!$canAddRemark && !$canAddPartOrder)
                            <span class="text-muted" title="Not authorized"><i class="bi bi-chat-text opacity-25"></i></span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="40" class="text-center text-muted py-4">No jobs found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <div class="d-flex align-items-center me-3">
        <label class="me-2 small text-muted">Show</label>
        <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()" form="searchForm">
            <option value="10" {{ request('per_page') == '10' ? 'selected' : '' }}>10</option>
            <option value="20" {{ (request('per_page') == '20' || !request('per_page')) ? 'selected' : '' }}>20</option>
            <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50</option>
            <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100</option>
        </select>
        <span class="ms-2 small text-muted">entries</span>
    </div>
    {{ $jobs->withQueryString()->links() }}
</div>

@push('scripts')
@php
    $defaultPrefs = [
        'columns' => [
            'no' => true, 'wip' => true, 'job_card' => false, 'dept' => false,
            'job_date' => true, 'date_in' => false, 'date_out' => false, 'check_in' => false, 'deadline' => false, 'promise' => false,
            'reg_no' => true, 'chassis' => false, 'unit' => false, 'account' => false, 'first_reg' => false,
            'customer' => true, 'address' => false,
            'sa' => true, 'foreman' => false, 'technician' => false, 'block' => false,
            'job_type' => false, 'pay_type' => false, 'type_sale' => false, 'job_desc' => false, 'work_status' => false,
            'labour' => false, 'part' => false, 'total' => true, 'estimated' => false,
            'rq' => false, 'order_part' => false, 'lain_lain' => false, 'need_part' => true,
            'inv_no' => false, 'inv_date' => false, 'inv_amt' => false, 'inv_ppn' => false, 'inv_total' => false,
            'first_remark' => false, 'update_remark' => true, 'last_updated' => true, 'status' => true, 'action' => true
        ],
        'order' => ['no', 'wip', 'job_card', 'dept', 'job_date', 'date_in', 'date_out', 'check_in', 'deadline', 'promise', 'reg_no', 'chassis', 'unit', 'account', 'first_reg', 'customer', 'address', 'sa', 'foreman', 'technician', 'block', 'job_type', 'pay_type', 'type_sale', 'job_desc', 'work_status', 'labour', 'part', 'total', 'estimated', 'rq', 'order_part', 'lain_lain', 'need_part', 'inv_no', 'inv_date', 'inv_amt', 'inv_ppn', 'inv_total', 'first_remark', 'update_remark', 'last_updated', 'status', 'action'],
        'widths' => [],
        'sort' => 'created_at',
        'dir' => 'desc'
    ];
    $storedPrefs = auth()->user()?->column_preferences ?? [];
    $storedColumns = isset($storedPrefs['columns']) ? $storedPrefs['columns'] : [];
    $userPrefs = array_merge($defaultPrefs['columns'], $storedColumns);
    
    // Merge order - keep stored order but append any new columns not in it
    $storedOrder = $storedPrefs['order'] ?? [];
    $allKeys = array_keys($defaultPrefs['columns']);
    $missingKeys = array_diff($allKeys, $storedOrder);
    $userOrder = !empty($storedOrder) ? array_merge($storedOrder, $missingKeys) : $defaultPrefs['order'];
    
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
        'no': '#', 'wip': 'WIP', 'job_card': 'Job Card', 'dept': 'Dept',
        'job_date': 'Job Date', 'date_in': 'Date In', 'date_out': 'Date Out', 'check_in': 'Check In', 'deadline': 'Deadline', 'promise': 'Promise Date',
        'reg_no': 'Reg No', 'chassis': 'Chassis', 'unit': 'Unit Type', 'account': 'Account No', 'first_reg': 'First Reg',
        'customer': 'Customer', 'address': 'Address',
        'sa': 'SA', 'foreman': 'Foreman', 'technician': 'Technician', 'block': 'Block',
        'job_type': 'Job Type', 'pay_type': 'Payment Type', 'type_sale': 'Type Sale', 'job_desc': 'Job Desc', 'work_status': 'Work Status',
        'labour': 'Labour', 'part': 'Part', 'total': 'Total', 'estimated': 'Estimated',
        'rq': 'RQ', 'order_part': 'Order Part', 'lain_lain': 'Lain-lain', 'need_part': 'Needs Parts',
        'inv_no': 'Invoice #', 'inv_date': 'Inv Date', 'inv_amt': 'Inv Amt', 'inv_ppn': 'Inv+PPN', 'inv_total': 'Inv+Meterai',
        'first_remark': 'First Remark', 'update_remark': 'Latest Remark', 'last_updated': 'Remark Updated', 'status': 'Status', 'action': 'Action'
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
            body: JSON.stringify({ columns: prefs, widths: widths, order: order, sort: currentSort, dir: currentDir, table: 'jobs' })
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

    // Add Remark button handler
    document.querySelectorAll('.btn-add-remark').forEach(btn => {
        btn.addEventListener('click', function() {
            const jobId = this.dataset.jobId;
            const jobNumber = this.dataset.jobNumber;
            document.getElementById('remarkJobId').value = jobId;
            document.getElementById('remarkJobNumber').textContent = jobNumber;
            document.getElementById('remarkText').value = '';
            const modal = new bootstrap.Modal(document.getElementById('addRemarkModal'));
            modal.show();
        });
    });
});
</script>
@endpush

<!-- Add Comment Modal -->
<div class="modal fade" id="addRemarkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addRemarkForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-chat-plus me-2"></i>Add Comment for WIP <span id="remarkJobNumber" class="fw-bold"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="job_id" id="remarkJobId">
                    <div class="mb-3">
                        <label class="form-label">Comment <span class="text-danger">*</span></label>
                        <textarea name="remark_text" id="remarkText" class="form-control" rows="3" required placeholder="Enter your comment..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="btn btn-outline-secondary btn-sm" for="modalCommentImages">
                            <i class="bi bi-image me-1"></i> Add Images
                        </label>
                        <input type="file" id="modalCommentImages" name="images[]" accept="image/*" multiple class="d-none">
                        <small class="text-muted ms-2">Max 3 images, 10MB each</small>
                        <div id="modalImagePreviewContainer" class="mt-2 d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitRemarkBtn"><i class="bi bi-plus me-1"></i>Add Comment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Image preview for modal
const modalImageInput = document.getElementById('modalCommentImages');
const modalPreviewContainer = document.getElementById('modalImagePreviewContainer');

if (modalImageInput) {
    modalImageInput.addEventListener('change', function() {
        modalPreviewContainer.innerHTML = '';
        const files = Array.from(this.files).slice(0, 3);
        
        files.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('div');
                preview.className = 'position-relative';
                preview.innerHTML = `
                    <img src="${e.target.result}" class="rounded" style="height: 50px; width: 50px; object-fit: cover; border: 2px solid #dee2e6;">
                `;
                modalPreviewContainer.appendChild(preview);
            };
            reader.readAsDataURL(file);
        });
    });
}

// Form submission with FormData
document.getElementById('addRemarkForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const jobId = document.getElementById('remarkJobId').value;
    const submitBtn = document.getElementById('submitRemarkBtn');
    const remarkText = document.getElementById('remarkText').value;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';
    
    const formData = new FormData();
    formData.append('remark_text', remarkText);
    
    // Add images (compressed)
    if (modalImageInput && modalImageInput.files.length > 0) {
        const rawFiles = Array.from(modalImageInput.files).slice(0, 3);
        const compressedFiles = await Promise.all(rawFiles.map(file => compressImage(file)));
        
        compressedFiles.forEach(file => {
            formData.append('images[]', file);
        });
    }
    
    try {
        const response = await fetch('/jobs/' + jobId + '/remark', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Close modal and reload page
            bootstrap.Modal.getInstance(document.getElementById('addRemarkModal')).hide();
            location.reload();
        } else {
            alert(data.message || 'Failed to add comment');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-plus me-1"></i>Add Comment';
    }
});

// Reset form when modal is hidden
document.getElementById('addRemarkModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('remarkText').value = '';
    if (modalImageInput) modalImageInput.value = '';
    if (modalPreviewContainer) modalPreviewContainer.innerHTML = '';
    // Close mention dropdown if open
    const dropdown = document.querySelector('.modal-mention-dropdown');
    if (dropdown) dropdown.remove();
});

// @Mention autocomplete for popup
const modalTextarea = document.getElementById('remarkText');
let modalMentionDropdown = null;
let modalMentionTimeout = null;

if (modalTextarea) {
    modalTextarea.addEventListener('input', function(e) {
        const cursorPos = this.selectionStart;
        const textBeforeCursor = this.value.substring(0, cursorPos);
        const mentionMatch = textBeforeCursor.match(/@(\w*)$/);
        
        if (mentionMatch) {
            const query = mentionMatch[1];
            clearTimeout(modalMentionTimeout);
            modalMentionTimeout = setTimeout(() => searchModalMentions(query, cursorPos - mentionMatch[0].length), 200);
        } else {
            closeModalMentionDropdown();
        }
    });
}

async function searchModalMentions(query, startPos) {
    if (query.length < 1) {
        closeModalMentionDropdown();
        return;
    }
    
    try {
        const response = await fetch(`/api/users/search?q=${encodeURIComponent(query)}`);
        const users = await response.json();
        
        if (users.length > 0) {
            showModalMentionDropdown(users, startPos);
        } else {
            closeModalMentionDropdown();
        }
    } catch (err) {
        console.error('Mention search error:', err);
    }
}

function showModalMentionDropdown(users, startPos) {
    closeModalMentionDropdown();
    
    modalMentionDropdown = document.createElement('div');
    modalMentionDropdown.className = 'modal-mention-dropdown shadow rounded bg-white border';
    modalMentionDropdown.style.cssText = 'position: absolute; z-index: 1060; max-height: 200px; overflow-y: auto; min-width: 200px;';
    
    users.forEach(user => {
        const item = document.createElement('div');
        item.className = 'mention-item px-3 py-2';
        item.style.cursor = 'pointer';
        item.innerHTML = `<strong>@${user.name}</strong> <small class="text-muted">${user.role}</small>`;
        item.addEventListener('click', () => insertModalMention(user.name, startPos));
        item.addEventListener('mouseenter', () => item.classList.add('bg-light'));
        item.addEventListener('mouseleave', () => item.classList.remove('bg-light'));
        modalMentionDropdown.appendChild(item);
    });
    
    modalTextarea.parentElement.style.position = 'relative';
    modalTextarea.parentElement.appendChild(modalMentionDropdown);
}

function closeModalMentionDropdown() {
    if (modalMentionDropdown) {
        modalMentionDropdown.remove();
        modalMentionDropdown = null;
    }
}

function insertModalMention(name, startPos) {
    const beforeMention = modalTextarea.value.substring(0, startPos);
    const afterMention = modalTextarea.value.substring(modalTextarea.selectionStart);
    const mentionText = name.includes(' ') ? `@"${name}" ` : `@${name} `;
    modalTextarea.value = beforeMention + mentionText + afterMention;
    modalTextarea.focus();
    closeModalMentionDropdown();
}

// Close dropdown on click outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.modal-mention-dropdown') && e.target !== modalTextarea) {
        closeModalMentionDropdown();
    }
});

// Image compression helper
async function compressImage(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (e) => {
            const img = new Image();
            img.src = e.target.result;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // Max dimensions
                const MAX_WIDTH = 1200;
                const MAX_HEIGHT = 1200;
                let width = img.width;
                let height = img.height;
                
                if (width > height) {
                    if (width > MAX_WIDTH) {
                        height *= MAX_WIDTH / width;
                        width = MAX_WIDTH;
                    }
                } else {
                    if (height > MAX_HEIGHT) {
                        width *= MAX_HEIGHT / height;
                        height = MAX_HEIGHT;
                    }
                }
                
                canvas.width = width;
                canvas.height = height;
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob((blob) => {
                    // Force .jpg extension
                    const fileName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";
                    resolve(new File([blob], fileName, {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    }));
                }, 'image/jpeg', 0.8);
            };
        };
    });
}
</script>

<script>
// Need Part Toggle Handler
document.querySelectorAll('.need-part-toggle').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const jobId = this.dataset.jobId;
        const jobWip = this.dataset.jobWip;
        
        // Simple confirmation - RQ is entered in Part Tracking Kanban
        if (confirm(`Mark job ${jobWip} as "Needs Parts"?\n\nThe job will appear in Part Tracking Kanban where you can open the RQ.`)) {
            fetch(`/jobs/${jobId}/need-part`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ need_part: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Failed to update. Please try again.');
            });
        }
    });
});
</script>

@endsection

