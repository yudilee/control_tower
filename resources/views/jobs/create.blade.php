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
                <div class="col-md-8">
                    <label class="form-label">Customer Name</label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control" value="{{ old('customer_name') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Customer Address</label>
                    <textarea name="customer_address" id="customer_address" class="form-control" rows="2">{{ old('customer_address') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Service Advisor</label>
                    <select name="service_advisor" class="form-select">
                        <option value="">-- Select SA --</option>
                        @foreach($serviceAdvisors as $sa)
                        <option value="{{ $sa->name }}" {{ old('service_advisor') == $sa->name ? 'selected' : '' }}>{{ $sa->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Foreman</label>
                    <select name="foreman" class="form-select @error('foreman') is-invalid @enderror">
                        <option value="">-- Select Foreman --</option>
                        @foreach($foremen as $fm)
                        <option value="{{ $fm->name }}" {{ old('foreman') == $fm->name ? 'selected' : '' }}>{{ $fm->name }}</option>
                        @endforeach
                    </select>
                    @error('foreman')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Block</label>
                    <input type="text" name="block" class="form-control" value="{{ old('block') }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Job Type</label>
                    <select name="job_type" class="form-select">
                        <option value="">-- Select Type --</option>
                        <option value="quick_service" {{ old('job_type') == 'quick_service' ? 'selected' : '' }}>Quick Service</option>
                        <option value="warranty" {{ old('job_type') == 'warranty' ? 'selected' : '' }}>Warranty</option>
                        <option value="isp" {{ old('job_type') == 'isp' ? 'selected' : '' }}>ISP</option>
                        <option value="campaign" {{ old('job_type') == 'campaign' ? 'selected' : '' }}>Campaign</option>
                        <option value="cash" {{ old('job_type') == 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="booking_service" {{ old('job_type') == 'booking_service' ? 'selected' : '' }}>Booking Service</option>
                        <option value="pdi" {{ old('job_type') == 'pdi' ? 'selected' : '' }}>Pre Delivery Inspection</option>
                        <option value="internal" {{ old('job_type') == 'internal' ? 'selected' : '' }}>Internal</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date In <small class="text-muted">(Job Date)</small></label>
                    <input type="date" name="job_date" id="job_date" class="form-control" value="{{ old('job_date', date('Y-m-d')) }}">
                    <small class="text-muted d-block mt-1">Press <kbd>t</kbd> for today</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Check-In Time</label>
                    <input type="time" name="check_in_time" class="form-control" value="{{ old('check_in_time', date('H:i')) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Promise Date</label>
                    <input type="date" name="promise_date" id="promise_date" class="form-control" value="{{ old('promise_date') }}">
                    <small class="text-muted d-block mt-1">Press <kbd>t</kbd> for today</small>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Work Status</label>
                    <select name="work_status" class="form-select">
                        <option value="">-- Select Status --</option>
                        @php $firstStatus = \App\Models\Job::WORK_STATUSES[0] ?? ''; @endphp
                        @foreach(\App\Models\Job::getWorkStatusOptions() as $opt)
                        <option value="{{ $opt->value }}" {{ old('work_status', $firstStatus) == $opt->value ? 'selected' : '' }}>{{ $opt->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Job Description</label>
                    <textarea name="job_description" class="form-control" rows="2">{{ old('job_description') }}</textarea>
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
    
    function resetFields() {
        if (unitTypeInput) unitTypeInput.value = '';
        if (customerNameInput) customerNameInput.value = '';
        const addressInput = document.getElementById('customer_address');
        if (addressInput) addressInput.value = '';
        const chassisInput = document.querySelector('input[name="chassis_number"]');
        if (chassisInput) chassisInput.value = '';
    }

    plateInput.addEventListener('input', function() {
        clearTimeout(lookupTimeout);
        const plate = this.value.trim();
        
        if (plate.length < 3) {
            lookupStatus.innerHTML = '<i class="bi bi-search"></i>';
            plateHint.textContent = 'Type plate number to lookup vehicle';
            resetFields(); // Clear fields if input is too short
            return;
        }
        
        lookupStatus.innerHTML = '<i class="bi bi-hourglass-split spin"></i>';
        plateHint.textContent = 'Looking up...';
        
        // Reset fields before new lookup
        resetFields();

        lookupTimeout = setTimeout(() => {
            fetch(`/api/vehicles/lookup?plate=${encodeURIComponent(plate)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.found) {
                        lookupStatus.innerHTML = '<i class="bi bi-check-circle text-success"></i>';
                        plateHint.innerHTML = '<span class="text-success">Vehicle found!</span>';
                        
                        // Auto-fill Unit Type / Model
                        if (data.model && unitTypeInput) {
                            unitTypeInput.value = data.model;
                        }

                        // Auto-fill customer name
                        if (data.customer_name && customerNameInput) {
                            customerNameInput.value = data.customer_name;
                        }

                        // Auto-fill customer address
                        const addressInput = document.getElementById('customer_address');
                        if (addressInput && data.customer_address) {
                            addressInput.value = data.customer_address;
                        }

                        // Auto-fill chassis/VIN
                        const chassisInput = document.querySelector('input[name="chassis_number"]');
                        if (chassisInput && (data.vin || data.chassis_number)) { 
                           chassisInput.value = data.vin || data.chassis_number;
                        }
                    } else {
                        lookupStatus.innerHTML = '<i class="bi bi-plus-circle text-info"></i>';
                        plateHint.innerHTML = '<span class="text-info">New vehicle - enter details</span>';
                        // Fields are already reset by resetFields() above
                    }
                })
                .catch(err => {
                    lookupStatus.innerHTML = '<i class="bi bi-search"></i>';
                    plateHint.textContent = 'Type plate number to lookup vehicle';
                });
        }, 500);
    });
    
    // Shortcut for Job Date
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
            return; 
        }
        
        // Active on any date input with ID ending in 'date'
        if (e.target.type === 'date') {
            if (e.key.toLowerCase() === 't') {
                e.preventDefault();
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth() + 1).padStart(2, '0');
                const dd = String(today.getDate()).padStart(2, '0');
                e.target.value = `${yyyy}-${mm}-${dd}`;
            }
        }
    });
});
</script>
<style>
.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush
@endsection

