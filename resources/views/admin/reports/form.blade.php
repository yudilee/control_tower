@extends('layouts.app')

@section('title', $report ? 'Edit Scheduled Report' : 'Create Scheduled Report')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('admin.scheduled-reports.index') }}">Scheduled Reports</a></li>
                <li class="breadcrumb-item active">{{ $report ? 'Edit' : 'Create' }}</li>
            </ol>
        </nav>
        <h1><i class="bi bi-calendar-check me-2"></i>{{ $report ? 'Edit Scheduled Report' : 'Create Scheduled Report' }}</h1>
        <p class="text-muted mb-0">Configure automated email reports with custom filters</p>
    </div>
</div>

<style>
.report-type-card {
    cursor: pointer;
    border-radius: 12px;
    padding: 1rem;
    transition: all 0.2s;
    border: 2px solid var(--bs-border-color);
    background: var(--bs-body-bg);
}
.report-type-card:hover {
    border-color: var(--bs-primary);
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.05), transparent);
}
.report-type-card.selected {
    border-color: var(--bs-primary);
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05));
}
.report-type-card .icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.report-type-card.type-uninvoiced .icon { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
.report-type-card.type-invoiced .icon { background: rgba(40, 167, 69, 0.15); color: #28a745; }
.report-type-card.type-performance .icon { background: rgba(0, 123, 255, 0.15); color: #007bff; }
.report-type-card.type-aging .icon { background: rgba(255, 193, 7, 0.15); color: #e0a800; }
.report-type-card.type-parts_pending .icon { background: rgba(108, 117, 125, 0.15); color: #6c757d; }

.filter-section {
    background: var(--bs-tertiary-bg);
    border-radius: 12px;
    padding: 1.25rem;
    border: 1px solid var(--bs-border-color);
}
.schedule-option {
    cursor: pointer;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    border: 1px solid var(--bs-border-color);
    transition: all 0.2s;
}
.schedule-option:hover {
    border-color: var(--bs-primary);
}
.schedule-option.selected {
    border-color: var(--bs-primary);
    background: rgba(13, 110, 253, 0.1);
}
</style>

<form method="POST" action="{{ $report ? route('admin.scheduled-reports.update', $report) : route('admin.scheduled-reports.store') }}" id="reportForm">
    @csrf
    @if($report)
        @method('PUT')
    @endif

    <div class="row g-4">
        <!-- Left Column: Report Type & Schedule -->
        <div class="col-lg-5">
            <!-- Report Type Selection -->
            <div class="card mb-4">
                <div class="card-header py-3">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i><strong>Report Type</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3" id="reportTypeGrid">
                        @php
                            $typeIcons = [
                                'uninvoiced' => 'bi-exclamation-triangle',
                                'invoiced' => 'bi-check-circle',
                                'performance' => 'bi-graph-up',
                                'aging' => 'bi-clock-history',
                                'parts_pending' => 'bi-gear',
                            ];
                        @endphp
                        @foreach($types as $value => $label)
                        <div class="col-12">
                            <div class="report-type-card type-{{ $value }} {{ old('type', $report?->type) == $value ? 'selected' : '' }}" data-type="{{ $value }}">
                                <div class="d-flex align-items-center">
                                    <div class="icon me-3">
                                        <i class="bi {{ $typeIcons[$value] ?? 'bi-file-text' }}"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">{{ $label }}</div>
                                        <small class="text-muted">{{ $descriptions[$value] ?? '' }}</small>
                                    </div>
                                    <div>
                                        <i class="bi bi-check-circle-fill text-primary {{ old('type', $report?->type) == $value ? '' : 'd-none' }}"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <input type="hidden" name="type" id="selectedType" value="{{ old('type', $report?->type) }}" required>
                    @error('type')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Schedule Configuration -->
            <div class="card">
                <div class="card-header py-3">
                    <i class="bi bi-clock me-2"></i><strong>Schedule</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Frequency</label>
                            <div class="d-flex gap-2 flex-wrap">
                                @foreach($schedules as $value => $label)
                                <div class="schedule-option {{ old('schedule', $report?->schedule ?? 'daily') == $value ? 'selected' : '' }}" data-schedule="{{ $value }}">
                                    <i class="bi bi-{{ $value == 'daily' ? 'calendar-day' : ($value == 'weekly' ? 'calendar-week' : 'calendar-month') }} me-1"></i>
                                    {{ $label }}
                                </div>
                                @endforeach
                            </div>
                            <input type="hidden" name="schedule" id="selectedSchedule" value="{{ old('schedule', $report?->schedule ?? 'daily') }}" required>
                        </div>

                        <div class="col-md-6" id="dayOfWeekContainer" style="{{ old('schedule', $report?->schedule) == 'weekly' ? '' : 'display: none;' }}">
                            <label class="form-label fw-semibold">Day of Week</label>
                            <select name="day_of_week" class="form-select">
                                @foreach($daysOfWeek as $value => $label)
                                    <option value="{{ $value }}" {{ old('day_of_week', $report?->day_of_week ?? 'mon') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6" id="dayOfMonthContainer" style="{{ old('schedule', $report?->schedule) == 'monthly' ? '' : 'display: none;' }}">
                            <label class="form-label fw-semibold">Day of Month</label>
                            <select name="day_of_month" class="form-select">
                                @for($i = 1; $i <= 28; $i++)
                                    <option value="{{ $i }}" {{ old('day_of_month', $report?->day_of_month ?? 1) == $i ? 'selected' : '' }}>
                                        {{ $i }}{{ $i == 1 ? 'st' : ($i == 2 ? 'nd' : ($i == 3 ? 'rd' : 'th')) }}
                                    </option>
                                @endfor
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Time <span class="text-danger">*</span></label>
                            <input type="time" name="time" class="form-control @error('time') is-invalid @enderror" 
                                   value="{{ old('time', $report?->time ?? '08:00') }}" required>
                            @error('time')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Details & Filters -->
        <div class="col-lg-7">
            <!-- Report Details -->
            <div class="card mb-4">
                <div class="card-header py-3">
                    <i class="bi bi-info-circle me-2"></i><strong>Report Details</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Report Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $report?->name) }}" required placeholder="e.g., Daily PC Uninvoiced Summary">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Recipients <span class="text-danger">*</span></label>
                            <input type="text" name="recipients" class="form-control @error('recipients') is-invalid @enderror" 
                                   value="{{ old('recipients', $report ? implode(', ', $report->recipients ?? []) : '') }}" required
                                   placeholder="admin@example.com, manager@example.com">
                            <small class="text-muted">Separate multiple email addresses with commas</small>
                            @error('recipients')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                                       {{ old('is_active', $report?->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">
                                    <strong>Active</strong> - Report will be sent according to schedule
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="config[include_pdf]" value="1" class="form-check-input" id="includePdf"
                                       {{ old('config.include_pdf', $report?->getConfig('include_pdf')) ? 'checked' : '' }}>
                                <label class="form-check-label" for="includePdf">
                                    <strong>Attach PDF</strong> - Include a PDF export with detailed data
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="card mb-4">
                <div class="card-header py-3">
                    <i class="bi bi-funnel me-2"></i><strong>Report Filters</strong>
                    <small class="text-muted ms-2">(optional)</small>
                </div>
                <div class="card-body">
                    <div class="filter-section">
                        <div class="row g-3">
                            <!-- Common Filters -->
                            <div class="col-md-4 filter-field" data-filters="uninvoiced,invoiced,aging,parts_pending">
                                <label class="form-label small fw-semibold">Franchise</label>
                                <select name="config[franchise]" class="form-select form-select-sm">
                                    <option value="">All Franchises</option>
                                    @foreach($filterOptions['franchise'] ?? [] as $opt)
                                        <option value="{{ $opt }}" {{ old('config.franchise', $report?->getConfig('franchise')) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 filter-field" data-filters="uninvoiced,invoiced,aging,parts_pending">
                                <label class="form-label small fw-semibold">Service Advisor</label>
                                <select name="config[service_advisor]" class="form-select form-select-sm">
                                    <option value="">All Service Advisors</option>
                                    @foreach($filterOptions['service_advisor'] ?? [] as $opt)
                                        <option value="{{ $opt }}" {{ old('config.service_advisor', $report?->getConfig('service_advisor')) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 filter-field" data-filters="uninvoiced,invoiced,aging,parts_pending">
                                <label class="form-label small fw-semibold">Foreman</label>
                                <select name="config[foreman]" class="form-select form-select-sm">
                                    <option value="">All Foremen</option>
                                    @foreach($filterOptions['foreman'] ?? [] as $opt)
                                        <option value="{{ $opt }}" {{ old('config.foreman', $report?->getConfig('foreman')) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 filter-field" data-filters="uninvoiced,invoiced">
                                <label class="form-label small fw-semibold">Department</label>
                                <select name="config[department]" class="form-select form-select-sm">
                                    <option value="">All Departments</option>
                                    @foreach($filterOptions['department'] ?? [] as $opt)
                                        <option value="{{ $opt }}" {{ old('config.department', $report?->getConfig('department')) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 filter-field" data-filters="uninvoiced">
                                <label class="form-label small fw-semibold">Work Status</label>
                                <select name="config[work_status]" class="form-select form-select-sm">
                                    <option value="">All Statuses</option>
                                    @foreach($filterOptions['work_status'] ?? [] as $opt)
                                        <option value="{{ $opt }}" {{ old('config.work_status', $report?->getConfig('work_status')) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 filter-field" data-filters="uninvoiced">
                                <label class="form-label small fw-semibold">Parts Status</label>
                                <select name="config[need_part]" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="1" {{ old('config.need_part', $report?->getConfig('need_part')) == '1' ? 'selected' : '' }}>Needs Parts</option>
                                    <option value="0" {{ old('config.need_part', $report?->getConfig('need_part')) == '0' ? 'selected' : '' }}>No Parts Needed</option>
                                </select>
                            </div>

                            <div class="col-md-4 filter-field" data-filters="invoiced">
                                <label class="form-label small fw-semibold">Type Sale</label>
                                <select name="config[type_sale]" class="form-select form-select-sm">
                                    <option value="">All Types</option>
                                    @foreach($filterOptions['type_sale'] ?? [] as $opt)
                                        <option value="{{ $opt }}" {{ old('config.type_sale', $report?->getConfig('type_sale')) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 filter-field" data-filters="aging">
                                <label class="form-label small fw-semibold">Aging Threshold (days)</label>
                                <input type="number" name="config[aging_days]" class="form-control form-control-sm" 
                                       value="{{ old('config.aging_days', $report?->getConfig('aging_days', 14)) }}" min="1" placeholder="14">
                                <small class="text-muted">Jobs older than this</small>
                            </div>

                            <div class="col-md-4 filter-field" data-filters="invoiced,performance">
                                <label class="form-label small fw-semibold">Date Period</label>
                                <select name="config[date_period]" class="form-select form-select-sm">
                                    <option value="last_7_days" {{ old('config.date_period', $report?->getConfig('date_period', 'last_7_days')) == 'last_7_days' ? 'selected' : '' }}>Last 7 Days</option>
                                    <option value="last_30_days" {{ old('config.date_period', $report?->getConfig('date_period')) == 'last_30_days' ? 'selected' : '' }}>Last 30 Days</option>
                                    <option value="this_week" {{ old('config.date_period', $report?->getConfig('date_period')) == 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="this_month" {{ old('config.date_period', $report?->getConfig('date_period')) == 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ old('config.date_period', $report?->getConfig('date_period')) == 'last_month' ? 'selected' : '' }}>Last Month</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save me-1"></i>{{ $report ? 'Update Report' : 'Create Report' }}
                </button>
                <a href="{{ route('admin.scheduled-reports.index') }}" class="btn btn-outline-secondary btn-lg">Cancel</a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeCards = document.querySelectorAll('.report-type-card');
    const typeInput = document.getElementById('selectedType');
    const filterFields = document.querySelectorAll('.filter-field');
    
    const scheduleOptions = document.querySelectorAll('.schedule-option');
    const scheduleInput = document.getElementById('selectedSchedule');
    const dayOfWeekContainer = document.getElementById('dayOfWeekContainer');
    const dayOfMonthContainer = document.getElementById('dayOfMonthContainer');

    // Report type selection
    typeCards.forEach(card => {
        card.addEventListener('click', function() {
            typeCards.forEach(c => {
                c.classList.remove('selected');
                c.querySelector('.bi-check-circle-fill')?.classList.add('d-none');
            });
            this.classList.add('selected');
            this.querySelector('.bi-check-circle-fill')?.classList.remove('d-none');
            typeInput.value = this.dataset.type;
            updateFilterVisibility(this.dataset.type);
        });
    });

    // Schedule selection
    scheduleOptions.forEach(opt => {
        opt.addEventListener('click', function() {
            scheduleOptions.forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            scheduleInput.value = this.dataset.schedule;
            
            dayOfWeekContainer.style.display = this.dataset.schedule === 'weekly' ? '' : 'none';
            dayOfMonthContainer.style.display = this.dataset.schedule === 'monthly' ? '' : 'none';
        });
    });

    // Filter visibility based on report type
    function updateFilterVisibility(type) {
        filterFields.forEach(field => {
            const filters = field.dataset.filters.split(',');
            field.style.display = filters.includes(type) ? '' : 'none';
        });
    }

    // Initialize filter visibility
    if (typeInput.value) {
        updateFilterVisibility(typeInput.value);
    }
});
</script>
@endpush
