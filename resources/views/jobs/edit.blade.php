@extends('layouts.app')

@section('title', 'Edit Job - ' . $job->job_number)

@section('content')
<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="{{ route('jobs.index') }}">Jobs</a></li>
            <li class="breadcrumb-item"><a href="{{ route('jobs.show', $job) }}">{{ $job->job_number }}</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
    <h1><i class="bi bi-pencil me-2"></i>Edit Job</h1>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('jobs.update', $job) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Job Number <span class="text-danger">*</span></label>
                    <input type="text" name="job_number" class="form-control @error('job_number') is-invalid @enderror" value="{{ old('job_number', $job->job_number) }}" required>
                    @error('job_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Franchise <span class="text-danger">*</span></label>
                    <select name="franchise" class="form-select @error('franchise') is-invalid @enderror" required>
                        <option value="PC" {{ old('franchise', $job->franchise) == 'PC' ? 'selected' : '' }}>PC - Passenger Car</option>
                        <option value="CV" {{ old('franchise', $job->franchise) == 'CV' ? 'selected' : '' }}>CV - Commercial Vehicle</option>
                    </select>
                    @error('franchise')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Plate Number <span class="text-danger">*</span></label>
                    <input type="text" name="plate_number" class="form-control @error('plate_number') is-invalid @enderror" value="{{ old('plate_number', $job->plate_number) }}" required>
                    @error('plate_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Vehicle</label>
                    <select name="vehicle_id" class="form-select">
                        <option value="">-- Select Vehicle --</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" {{ old('vehicle_id', $job->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->plate_number }} - {{ $vehicle->model }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Job Type</label>
                    <select name="job_type" class="form-select">
                        <option value="">-- Select Type --</option>
                        <option value="regular" {{ old('job_type', $job->job_type) == 'regular' ? 'selected' : '' }}>Regular Service</option>
                        <option value="pdi" {{ old('job_type', $job->job_type) == 'pdi' ? 'selected' : '' }}>PDI</option>
                        <option value="booking" {{ old('job_type', $job->job_type) == 'booking' ? 'selected' : '' }}>Booking</option>
                        <option value="towing" {{ old('job_type', $job->job_type) == 'towing' ? 'selected' : '' }}>Towing</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Service Advisor</label>
                    <input type="text" name="service_advisor" class="form-control" value="{{ old('service_advisor', $job->service_advisor) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Technician</label>
                    <input type="text" name="technician" class="form-control" value="{{ old('technician', $job->technician) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Job Date</label>
                    <input type="date" name="job_date" class="form-control" value="{{ old('job_date', $job->job_date?->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Promise Date</label>
                    <input type="date" name="promise_date" class="form-control" value="{{ old('promise_date', $job->promise_date?->format('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estimated Amount</label>
                    <input type="number" name="estimated_amount" class="form-control" value="{{ old('estimated_amount', $job->estimated_amount) }}" step="0.01">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Work Status</label>
                    <select name="work_status" class="form-select">
                        <option value="">-- Select Status --</option>
                        @foreach(\App\Models\DropdownOption::getOptions('work_status') as $opt)
                        <option value="{{ $opt->value }}" {{ old('work_status', $job->work_status) == $opt->value ? 'selected' : '' }}>{{ $opt->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $job->description) }}</textarea>
                </div>
            </div>
            <hr>
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('jobs.show', $job) }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update Job</button>
            </div>
        </form>
    </div>
</div>
@endsection
