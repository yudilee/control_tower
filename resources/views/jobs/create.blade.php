@extends('layouts.app')

@section('title', 'Add New Job')

@section('content')
<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="{{ route('jobs.index') }}">Jobs</a></li>
            <li class="breadcrumb-item active">Add New</li>
        </ol>
    </nav>
    <h1><i class="bi bi-plus-circle me-2"></i>Add New Job</h1>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('jobs.store') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Job Number <span class="text-danger">*</span></label>
                    <input type="text" name="job_number" class="form-control @error('job_number') is-invalid @enderror" value="{{ old('job_number') }}" required>
                    @error('job_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Franchise <span class="text-danger">*</span></label>
                    <select name="franchise" class="form-select @error('franchise') is-invalid @enderror" required>
                        <option value="PC" {{ old('franchise') == 'PC' ? 'selected' : '' }}>PC - Passenger Car</option>
                        <option value="CV" {{ old('franchise') == 'CV' ? 'selected' : '' }}>CV - Commercial Vehicle</option>
                    </select>
                    @error('franchise')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Plate Number <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" name="plate_number" id="plate_number" class="form-control @error('plate_number') is-invalid @enderror" value="{{ old('plate_number') }}" required>
                        <span class="input-group-text" id="plate_lookup_status"><i class="bi bi-search"></i></span>
                    </div>
                    <small class="text-muted" id="plate_hint">Type plate number to lookup vehicle</small>
                    @error('plate_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unit Type / Model</label>
                    <input type="text" name="unit_type" id="unit_type" class="form-control" value="{{ old('unit_type') }}" placeholder="e.g. XPANDER, L300">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Chassis Number</label>
                    <input type="text" name="chassis_number" class="form-control" value="{{ old('chassis_number') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Customer Name</label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control" value="{{ old('customer_name') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Service Advisor</label>
                    <input type="text" name="service_advisor" class="form-control" value="{{ old('service_advisor') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Technician</label>
                    <input type="text" name="technician" class="form-control" value="{{ old('technician') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Type</label>
                    <input type="text" name="payment_type" class="form-control" value="{{ old('payment_type') }}" placeholder="CASH, AR, etc">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Job Type</label>
                    <select name="job_type" class="form-select">
                        <option value="">-- Select Type --</option>
                        <option value="regular" {{ old('job_type') == 'regular' ? 'selected' : '' }}>Regular Service</option>
                        <option value="pdi" {{ old('job_type') == 'pdi' ? 'selected' : '' }}>PDI</option>
                        <option value="booking" {{ old('job_type') == 'booking' ? 'selected' : '' }}>Booking</option>
                        <option value="towing" {{ old('job_type') == 'towing' ? 'selected' : '' }}>Towing</option>
                        <option value="body_repair" {{ old('job_type') == 'body_repair' ? 'selected' : '' }}>Body Repair</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Job Date</label>
                    <input type="date" name="job_date" class="form-control" value="{{ old('job_date', date('Y-m-d')) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Promise Date</label>
                    <input type="date" name="promise_date" class="form-control" value="{{ old('promise_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Amount (Rp)</label>
                    <input type="number" name="estimated_amount" class="form-control" value="{{ old('estimated_amount') }}" step="0.01">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Work Status</label>
                    <select name="work_status" class="form-select">
                        <option value="">-- Select Status --</option>
                        @foreach(\App\Models\DropdownOption::getOptions('work_status') as $opt)
                        <option value="{{ $opt->value }}" {{ old('work_status') == $opt->value ? 'selected' : '' }}>{{ $opt->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Initial Remark</label>
                    <input type="text" name="initial_remark" class="form-control" placeholder="Optional initial remark" value="{{ old('initial_remark') }}">
                </div>
            </div>
            <hr>
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('jobs.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Job</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const plateInput = document.getElementById('plate_number');
    const unitTypeInput = document.getElementById('unit_type');
    const customerNameInput = document.getElementById('customer_name');
    const lookupStatus = document.getElementById('plate_lookup_status');
    const plateHint = document.getElementById('plate_hint');
    
    let lookupTimeout = null;
    
    plateInput.addEventListener('input', function() {
        clearTimeout(lookupTimeout);
        const plate = this.value.trim();
        
        if (plate.length < 3) {
            lookupStatus.innerHTML = '<i class="bi bi-search"></i>';
            plateHint.textContent = 'Type plate number to lookup vehicle';
            return;
        }
        
        lookupStatus.innerHTML = '<i class="bi bi-hourglass-split spin"></i>';
        plateHint.textContent = 'Looking up...';
        
        lookupTimeout = setTimeout(() => {
            fetch(`/api/vehicles/lookup?plate=${encodeURIComponent(plate)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.found) {
                        lookupStatus.innerHTML = '<i class="bi bi-check-circle text-success"></i>';
                        plateHint.innerHTML = '<span class="text-success">Vehicle found!</span>';
                        
                        // Auto-fill unit type if empty
                        if (!unitTypeInput.value && data.model) {
                            unitTypeInput.value = data.model;
                        }
                        // Auto-fill customer name if empty
                        if (!customerNameInput.value && data.customer_name) {
                            customerNameInput.value = data.customer_name;
                        }
                    } else {
                        lookupStatus.innerHTML = '<i class="bi bi-plus-circle text-info"></i>';
                        plateHint.innerHTML = '<span class="text-info">New vehicle - enter details</span>';
                    }
                })
                .catch(err => {
                    lookupStatus.innerHTML = '<i class="bi bi-search"></i>';
                    plateHint.textContent = 'Type plate number to lookup vehicle';
                });
        }, 500);
    });
});
</script>
<style>
.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush
@endsection

