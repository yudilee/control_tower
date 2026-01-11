{{-- Widget: Quick Actions --}}

<div class="mb-4">
    <h5 class="mb-4 fw-bold text-muted text-uppercase small ls-1">Quick Actions</h5>
    <div class="row g-4">
        <div class="col-md-3">
            <a href="{{ route('jobs.create') }}" class="action-card">
                <div class="action-icon-wrapper">
                    <i class="bi bi-plus-lg"></i>
                </div>
                <div class="action-title">New Job</div>
                <div class="action-desc">Create a new job order</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('imports.upload') }}" class="action-card">
                <div class="action-icon-wrapper">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <div class="action-title">Import Data</div>
                <div class="action-desc">Upload Excel/ODS files</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('reports.export-uninvoiced') }}" class="action-card">
                <div class="action-icon-wrapper">
                    <i class="bi bi-file-earmark-arrow-down"></i>
                </div>
                <div class="action-title">Export Report</div>
                <div class="action-desc">Download uninvoiced jobs</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('jobs.kanban') }}" class="action-card">
                <div class="action-icon-wrapper">
                    <i class="bi bi-kanban"></i>
                </div>
                <div class="action-title">Kanban Board</div>
                <div class="action-desc">Visual workflow view</div>
            </a>
        </div>
    </div>
</div>
