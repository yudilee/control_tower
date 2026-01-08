@extends('layouts.app')

@section('title', 'DMS Import')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-cloud-upload me-2"></i>DMS Import</h1>
        <p class="text-muted">Import customer and vehicle data from DMS Excel files</p>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('import_results'))
@php $results = session('import_results'); @endphp
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <h6 class="alert-heading mb-2"><i class="bi bi-info-circle me-2"></i>Import Results</h6>
    <div class="row">
        <div class="col-md-3">
            <span class="badge bg-success">{{ $results['created'] ?? 0 }} Created</span>
        </div>
        <div class="col-md-3">
            <span class="badge bg-primary">{{ $results['updated'] ?? 0 }} Updated</span>
        </div>
        <div class="col-md-3">
            <span class="badge bg-danger">{{ $results['errors'] ?? 0 }} Errors</span>
        </div>
    </div>
    @if(!empty($results['error_messages']))
    <hr>
    <small class="text-muted">
        @foreach(array_slice($results['error_messages'], 0, 5) as $msg)
        <div>• {{ $msg }}</div>
        @endforeach
        @if(count($results['error_messages']) > 5)
        <div class="mt-1">... and {{ count($results['error_messages']) - 5 }} more errors</div>
        @endif
    </small>
    @endif
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row">
    <!-- Customer Import -->
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-people me-2"></i>Customer Import</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Import customers from DMS Excel file. Expected columns:
                </p>
                <ul class="small text-muted">
                    <li>Magic cust (unique ID)</li>
                    <li>Nama Customer</li>
                    <li>ADDRESS 1-5</li>
                    <li>Company name</li>
                    <li>E-mail address</li>
                    <li>Dept</li>
                    <li>Date created</li>
                </ul>
                
                <form action="{{ route('admin.dms-import.customers') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="customer_file" class="form-label">Select Customer Excel File</label>
                        <input type="file" class="form-control" id="customer_file" name="file" 
                               accept=".xls,.xlsx" required>
                        <div class="form-text">Max file size: 10MB. Accepted formats: .xls, .xlsx</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-upload me-2"></i>Import Customers
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Vehicle Import -->
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-truck me-2"></i>Vehicle Import</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Import vehicles from DMS Excel file. Expected columns:
                </p>
                <ul class="small text-muted">
                    <li>Magic (unique ID)</li>
                    <li>Registration No (plate number)</li>
                    <li>Model, Variant, Description</li>
                    <li>Chassis No, MHL Number, Engine No</li>
                    <li>Customer Magic (links to customer)</li>
                    <li>Phone1-4 (synced to customer)</li>
                    <li>Reg. Date, Last Service Date</li>
                </ul>
                
                <form action="{{ route('admin.dms-import.vehicles') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="vehicle_file" class="form-label">Select Vehicle Excel File</label>
                        <input type="file" class="form-control" id="vehicle_file" name="file" 
                               accept=".xls,.xlsx" required>
                        <div class="form-text">Max file size: 10MB. Accepted formats: .xls, .xlsx</div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-upload me-2"></i>Import Vehicles
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Import Notes -->
<div class="card border-0 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Import Notes</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Customer Import</h6>
                <ul class="text-muted small">
                    <li>Uses <code>Magic cust</code> to identify existing customers</li>
                    <li>Existing customers will be updated with new data</li>
                    <li>New customers will be created</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Vehicle Import</h6>
                <ul class="text-muted small">
                    <li>Uses <code>Magic</code> or <code>Registration No</code> to identify existing vehicles</li>
                    <li><strong>Existing vehicles preserve their "In Workshop" status</strong></li>
                    <li>New vehicles are set to "Not in Workshop"</li>
                    <li>Phone numbers are synced to linked customers</li>
                    <li>All changes are logged in the audit trail</li>
                </ul>
            </div>
        </div>
        
        <div class="alert alert-warning mt-3 mb-0">
            <i class="bi bi-lightbulb me-2"></i>
            <strong>Tip:</strong> Import customers first, then vehicles. This ensures vehicle-customer links work correctly.
        </div>
    </div>
</div>
@endsection
