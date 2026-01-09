@extends('layouts.app')

@section('title', 'Upload Data')

@section('content')
<!-- Enhanced Loading Overlay -->
<div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(13, 148, 136, 0.95) 0%, rgba(17, 94, 89, 0.95) 100%); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
    <div class="loading-container text-center">
        <!-- Animated Spinner -->
        <div class="position-relative mb-4">
            <div class="spinner-grow text-light" style="width: 5rem; height: 5rem; animation-duration: 1s;" role="status"></div>
            <div class="spinner-grow text-light position-absolute" style="width: 5rem; height: 5rem; top: 0; left: 50%; transform: translateX(-50%); animation-delay: 0.3s; opacity: 0.7;" role="status"></div>
            <div class="spinner-grow text-light position-absolute" style="width: 5rem; height: 5rem; top: 0; left: 50%; transform: translateX(-50%); animation-delay: 0.6s; opacity: 0.4;" role="status"></div>
        </div>
        
        <!-- Main Message -->
        <h3 class="text-white mb-3">
            <i class="bi bi-file-earmark-arrow-up me-2"></i>
            Importing Data...
        </h3>
        
        <!-- Animated Status Messages -->
        <div id="loadingStatus" class="text-white-50 fs-5 mb-3" style="min-height: 30px;">
            Preparing import...
        </div>
        
        <!-- Elapsed Time -->
        <div class="text-white-50 mb-4">
            <i class="bi bi-clock me-1"></i>
            Elapsed: <span id="elapsedTime">0:00</span>
        </div>
        
        <!-- Progress Steps -->
        <div class="d-flex justify-content-center gap-2 mb-4">
            <div class="step-dot" id="step1"></div>
            <div class="step-dot" id="step2"></div>
            <div class="step-dot" id="step3"></div>
            <div class="step-dot" id="step4"></div>
            <div class="step-dot" id="step5"></div>
        </div>
        
        <!-- Tip -->
        <div class="text-white-50 small" style="max-width: 400px;">
            <i class="bi bi-info-circle me-1"></i>
            Large files may take several minutes. Please don't close this page.
        </div>
    </div>
</div>

<style>
.step-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transition: all 0.3s ease;
}
.step-dot.active {
    background: #fff;
    box-shadow: 0 0 10px rgba(255,255,255,0.8);
    transform: scale(1.2);
}
@keyframes pulse-message {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
#loadingStatus {
    animation: pulse-message 2s ease-in-out infinite;
}
</style>

<div class="page-header">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="{{ route('imports.index') }}">Imports</a></li>
            <li class="breadcrumb-item active">Upload</li>
        </ol>
    </nav>
    <h1><i class="bi bi-file-earmark-arrow-up me-2"></i>Upload Data</h1>
</div>

<!-- Workflow Guide -->
<div class="alert alert-light border mb-4">
    <h5 class="mb-3"><i class="bi bi-diagram-3 me-2"></i>Recommended Import Workflow</h5>
    <div class="d-flex align-items-center flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <span class="badge bg-primary rounded-pill me-2" style="width: 28px; height: 28px; line-height: 20px;">1</span>
            <span class="text-muted">DMS Customers</span>
        </div>
        <i class="bi bi-arrow-right text-muted"></i>
        <div class="d-flex align-items-center">
            <span class="badge bg-primary rounded-pill me-2" style="width: 28px; height: 28px; line-height: 20px;">2</span>
            <span class="text-muted">DMS Vehicles</span>
        </div>
        <i class="bi bi-arrow-right text-muted"></i>
        <div class="d-flex align-items-center">
            <span class="badge bg-warning text-dark rounded-pill me-2" style="width: 28px; height: 28px; line-height: 20px;">3</span>
            <span class="text-muted">Uninvoiced Jobs</span>
        </div>
        <i class="bi bi-arrow-right text-muted"></i>
        <div class="d-flex align-items-center">
            <span class="badge bg-success rounded-pill me-2" style="width: 28px; height: 28px; line-height: 20px;">4</span>
            <span class="text-muted">Invoiced Jobs</span>
        </div>
        <i class="bi bi-arrow-right text-muted d-none d-lg-block"></i>
        <div class="d-flex align-items-center d-none d-lg-flex">
            <span class="badge bg-info rounded-pill me-2" style="width: 28px; height: 28px; line-height: 20px;">5</span>
            <span class="text-muted">Refresh Summaries</span>
        </div>
    </div>
</div>

<!-- Step 1: DMS Master Data -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
        <span>
            <span class="badge bg-white text-primary me-2 rounded-pill">Step 1-2</span>
            <i class="bi bi-database me-2"></i>DMS Master Data Import
        </span>
        <small class="opacity-75">Import these FIRST to enable customer linking</small>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 border-info">
                    <div class="card-header bg-info text-white">
                        <span class="badge bg-white text-info me-2">1</span>
                        <i class="bi bi-people me-2"></i>Import DMS Customers
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-2">Import customer master data from DMS. This creates customer records for linking.</p>
                        <form action="{{ route('admin.dms-import.customers') }}" method="POST" enctype="multipart/form-data" class="import-form">
                            @csrf
                            <div class="mb-3">
                                <input type="file" name="file" class="form-control" accept=".xls,.xlsx" required>
                                <div class="form-text">Expected: Magic cust, Title, Nama Customer, Address 1-5, Company Name, Magic Comp, Email, Telp 01-04</div>
                            </div>
                            <button type="submit" class="btn btn-info w-100 text-white">
                                <i class="bi bi-upload me-1"></i>Import Customers
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100 border-secondary">
                    <div class="card-header bg-secondary text-white">
                        <span class="badge bg-white text-secondary me-2">2</span>
                        <i class="bi bi-truck me-2"></i>Import DMS Vehicles
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-2">Import vehicle master data from DMS. Links vehicles to customers.</p>
                        <form action="{{ route('admin.dms-import.vehicles') }}" method="POST" enctype="multipart/form-data" class="import-form">
                            @csrf
                            <div class="mb-3">
                                <input type="file" name="file" class="form-control" accept=".xls,.xlsx" required>
                                <div class="form-text">Expected: Magic, Reg No, Model, Chassis, Customer Magic, Phone1-4</div>
                            </div>
                            <button type="submit" class="btn btn-secondary w-100">
                                <i class="bi bi-upload me-1"></i>Import Vehicles
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Step 3-4: Job Data -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between">
        <span>
            <span class="badge bg-white text-dark me-2 rounded-pill">Step 3-4</span>
            <i class="bi bi-briefcase me-2"></i>Job Data Import
        </span>
        <small class="opacity-75">Jobs will auto-link to DMS customers</small>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <span class="badge bg-dark text-warning me-2">3</span>
                        <i class="bi bi-exclamation-triangle me-2"></i>Import Uninvoiced Jobs
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-2">Import uninvoiced job report from DMS (uiws.xls). Auto-links to customers.</p>
                        <form action="{{ route('imports.uninvoiced') }}" method="POST" enctype="multipart/form-data" class="import-form">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Franchise <span class="text-danger">*</span></label>
                                <select name="franchise" class="form-select" required>
                                    <option value="PC">PC - Passenger Car</option>
                                    <option value="CV">CV - Commercial Vehicle</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.ods,.csv" required>
                            </div>
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="bi bi-upload me-1"></i>Import Uninvoiced
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100 border-success">
                    <div class="card-header bg-success text-white">
                        <span class="badge bg-white text-success me-2">4</span>
                        <i class="bi bi-check-circle me-2"></i>Import Invoiced Jobs
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-2">Import invoiced job data (INV sheet). Marks jobs as invoiced, links to customers.</p>
                        <form action="{{ route('imports.invoiced') }}" method="POST" enctype="multipart/form-data" class="import-form">
                            @csrf
                             <div class="mb-3">
                                <label class="form-label">Franchise <span class="text-danger">*</span></label>
                                <select name="franchise" class="form-select" required>
                                    <option value="PC">PC - Passenger Car</option>
                                    <option value="CV">CV - Commercial Vehicle</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.ods,.csv" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-upload me-1"></i>Import Invoiced
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Step 5: Refresh -->
<div class="card mb-4">
    <div class="card-header bg-info text-white d-flex align-items-center justify-content-between">
        <span>
            <span class="badge bg-white text-info me-2 rounded-pill">Step 5</span>
            <i class="bi bi-arrow-repeat me-2"></i>Refresh Customer Summaries
        </span>
        <small class="opacity-75">Run after all imports to update Customer Lookup</small>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <p class="mb-1">After importing all data, run this command to rebuild the Customer Lookup table:</p>
                <code class="user-select-all">docker exec -it control_tower_app php artisan customers:refresh-summaries</code>
            </div>
            <div class="col-md-4 text-end">
                <a href="{{ route('customers.index') }}" class="btn btn-outline-info">
                    <i class="bi bi-people me-1"></i>Go to Customer Lookup
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Optional: Progress Import -->
<div class="card mb-4">
    <div class="card-header bg-light d-flex align-items-center justify-content-between">
        <span>
            <i class="bi bi-clipboard-check me-2"></i>Import Progress Data
            <span class="badge bg-secondary ms-2">Alternative</span>
        </span>
        <small class="text-muted">For PROGRES JOB files (creates new jobs)</small>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Import job progress data from PROGRES JOB file. This will create new jobs or update existing ones.</p>
        <form action="{{ route('imports.preview') }}" method="POST" enctype="multipart/form-data" class="import-form">
            @csrf
            <input type="hidden" name="import_type" value="progress">
            <div class="row">
                <div class="col-md-8">
                    <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.ods,.csv" required>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-eye me-1"></i>Preview
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="directImport(this.form, 'progress')" title="Skip preview">
                                <i class="bi bi-lightning"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="bi bi-info-circle me-2"></i>Import Instructions
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Franchise Types</h6>
                <ul>
                    <li><strong>PC (Passenger Car):</strong> Private vehicles, sedans, hatchbacks, SUVs</li>
                    <li><strong>CV (Commercial Vehicle):</strong> Trucks, buses, commercial fleet vehicles</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Supported Formats</h6>
                <p>Excel (.xlsx, .xls), OpenDocument (.ods), CSV (.csv)</p>
            </div>
        </div>
        
        <h6>Special Sheets (Auto-Detected)</h6>
        <ul>
            <li><strong>BOOKING 2025:</strong> Imported to Bookings table</li>
            <li><strong>PRE DELIVERY INSPECTION:</strong> Imported to PDI Records table</li>
            <li><strong>JADWAL TOWING STOORING:</strong> Imported to Towing Records table</li>
        </ul>
        
        <div class="alert alert-success mb-0">
            <i class="bi bi-link-45deg me-2"></i><strong>Customer Linking:</strong> When you import job data, the system automatically links jobs to DMS customers using name matching. Import DMS Customers first for best results!
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.import-form');
    const overlay = document.getElementById('loadingOverlay');
    const statusEl = document.getElementById('loadingStatus');
    const elapsedEl = document.getElementById('elapsedTime');
    
    let startTime = null;
    let timerInterval = null;
    let messageInterval = null;
    let stepInterval = null;
    let currentStep = 0;
    
    const statusMessages = [
        'Preparing import...',
        'Reading file contents...',
        'Parsing spreadsheet data...',
        'Validating records...',
        'Processing jobs...',
        'Updating database...',
        'Checking for duplicates...',
        'Syncing vehicle records...',
        'Almost done...',
        'Finalizing import...'
    ];
    
    function formatElapsed(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
    
    function startLoadingAnimation() {
        startTime = Date.now();
        let messageIndex = 0;
        
        // Update elapsed time every second
        timerInterval = setInterval(() => {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            elapsedEl.textContent = formatElapsed(elapsed);
        }, 1000);
        
        // Cycle through status messages every 3 seconds
        messageInterval = setInterval(() => {
            messageIndex = (messageIndex + 1) % statusMessages.length;
            statusEl.textContent = statusMessages[messageIndex];
        }, 3000);
        
        // Animate progress dots
        stepInterval = setInterval(() => {
            // Clear all dots
            for (let i = 1; i <= 5; i++) {
                document.getElementById('step' + i).classList.remove('active');
            }
            // Activate current dot
            currentStep = (currentStep % 5) + 1;
            document.getElementById('step' + currentStep).classList.add('active');
        }, 600);
    }
    
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            overlay.style.display = 'flex';
            startLoadingAnimation();
        });
    });
    
    // Also trigger for direct import
    window.startImportLoading = function() {
        overlay.style.display = 'flex';
        startLoadingAnimation();
    };
});

// Direct import (skip preview)
function directImport(form, importType) {
    const routeMap = {
        'progress': '{{ route("imports.progress") }}',
        'uninvoiced': '{{ route("imports.uninvoiced") }}',
        'invoiced': '{{ route("imports.invoiced") }}'
    };
    
    if (routeMap[importType]) {
        if (window.startImportLoading) {
            window.startImportLoading();
        }
        form.action = routeMap[importType];
        form.submit();
    }
}
</script>
@endpush
@endsection
