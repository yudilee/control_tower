@extends('layouts.app')

@section('title', $report ? 'Edit Report' : 'Create Report')

@section('content')
<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="{{ route('admin.scheduled-reports.index') }}">Scheduled Reports</a></li>
            <li class="breadcrumb-item active">{{ $report ? 'Edit' : 'Create' }}</li>
        </ol>
    </nav>
    <h1><i class="bi bi-envelope-at me-2"></i>{{ $report ? 'Edit Report' : 'Create Report' }}</h1>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $report ? route('admin.scheduled-reports.update', $report) : route('admin.scheduled-reports.store') }}">
            @csrf
            @if($report)
                @method('PUT')
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Report Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                           value="{{ old('name', $report?->name) }}" required placeholder="e.g., Daily Uninvoiced Summary">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Report Type <span class="text-danger">*</span></label>
                    <select name="type" class="form-select @error('type') is-invalid @enderror" required id="reportType">
                        <option value="">Select Type...</option>
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}" {{ old('type', $report?->type) == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Schedule <span class="text-danger">*</span></label>
                    <select name="schedule" class="form-select @error('schedule') is-invalid @enderror" required id="scheduleType">
                        @foreach($schedules as $value => $label)
                            <option value="{{ $value }}" {{ old('schedule', $report?->schedule ?? 'daily') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('schedule')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4" id="dayOfWeekContainer" style="{{ old('schedule', $report?->schedule) == 'weekly' ? '' : 'display: none;' }}">
                    <label class="form-label">Day of Week</label>
                    <select name="day_of_week" class="form-select">
                        @foreach($daysOfWeek as $value => $label)
                            <option value="{{ $value }}" {{ old('day_of_week', $report?->day_of_week ?? 'mon') == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Time <span class="text-danger">*</span></label>
                    <input type="time" name="time" class="form-control @error('time') is-invalid @enderror" 
                           value="{{ old('time', $report?->time ?? '08:00') }}" required>
                    @error('time')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Recipients (comma-separated emails) <span class="text-danger">*</span></label>
                    <input type="text" name="recipients" class="form-control @error('recipients') is-invalid @enderror" 
                           value="{{ old('recipients', $report ? implode(', ', $report->recipients ?? []) : '') }}" required
                           placeholder="admin@example.com, manager@example.com">
                    @error('recipients')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Enter email addresses separated by commas</small>
                </div>

                <!-- Aging config -->
                <div class="col-md-6" id="agingConfig" style="{{ old('type', $report?->type) == 'aging' ? '' : 'display: none;' }}">
                    <label class="form-label">Aging Threshold (days)</label>
                    <input type="number" name="config[aging_days]" class="form-control" 
                           value="{{ old('config.aging_days', $report?->getConfig('aging_days', 14)) }}" min="1">
                    <small class="text-muted">Alert for jobs older than this many days</small>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                               {{ old('is_active', $report?->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isActive">
                            <strong>Active</strong> - Report will be sent according to schedule
                        </label>
                    </div>
                </div>
            </div>

            <hr>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>{{ $report ? 'Update Report' : 'Create Report' }}
                </button>
                <a href="{{ route('admin.scheduled-reports.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const scheduleSelect = document.getElementById('scheduleType');
    const dayOfWeekContainer = document.getElementById('dayOfWeekContainer');
    const typeSelect = document.getElementById('reportType');
    const agingConfig = document.getElementById('agingConfig');

    scheduleSelect.addEventListener('change', function() {
        dayOfWeekContainer.style.display = this.value === 'weekly' ? '' : 'none';
    });

    typeSelect.addEventListener('change', function() {
        agingConfig.style.display = this.value === 'aging' ? '' : 'none';
    });
});
</script>
@endpush
