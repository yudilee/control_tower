@extends('layouts.app')

@section('content')
<div class="container-fluid">
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Validation Error:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0 text-gray-800">Database Backups</h1>
            <p class="text-muted small">Manage database backups, view audit logs, and perform restoration.</p>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#restoreFromFileModal">
                <i class="bi bi-upload me-1"></i>Restore from File
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                <i class="bi bi-plus-circle me-1"></i>Create New Backup
            </button>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <span><i class="bi bi-archive me-2"></i>Backup Files</span>
            <div>
                <form action="{{ route('admin.backups.prune') }}" method="POST" class="d-inline" onsubmit="return confirm('Run automatic pruning based on retention policy?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-scissors me-1"></i>Auto Prune
                    </button>
                </form>
                <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="deleteSelectedBtn" style="display:none;" onclick="deleteSelected()">
                    <i class="bi bi-trash me-1"></i>Delete Selected (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <form id="batchDeleteForm" action="{{ route('admin.backups.delete-batch') }}" method="POST">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:40px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll(this)">
                                </th>
                                <th>Filename</th>
                                <th>Remark</th>
                                <th>Size</th>
                                <th>Created By</th>
                                <th>Created At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($backups as $backup)
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input backup-checkbox" name="filenames[]" value="{{ $backup->filename }}" onchange="updateSelectedCount()">
                                    </td>
                                    <td>
                                        <i class="bi bi-file-earmark-zip-fill text-warning me-2"></i>
                                        {{ $backup->filename }}
                                    </td>
                                    <td>
                                        @if($backup->remark)
                                            <span class="text-muted fst-italic">{{ $backup->remark }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($backup->size > 0)
                                            {{ number_format($backup->size / 1048576, 2) }} MB
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $backup->created_by ?? 'System' }}</span>
                                    </td>
                                    <td>{{ $backup->created_at->format('d M Y H:i:s') }} <br> <small class="text-muted">({{ $backup->created_at->diffForHumans() }})</small></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.backups.download', $backup->filename) }}" class="btn btn-outline-secondary" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <form action="{{ route('admin.backups.restore', $backup->filename) }}" method="POST" class="d-inline restore-form">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-warning" title="Restore" onclick="return confirmRestore('{{ $backup->filename }}');">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.backups.destroy', $backup->filename) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Delete this backup file?');">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No backups found. Create one to get started.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule & Pruning Configuration Card -->
    <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Schedule & Retention</h5>
            <span class="badge {{ $schedule->enabled ? 'bg-success' : 'bg-secondary' }}">
                {{ $schedule->enabled ? 'Enabled' : 'Disabled' }}
            </span>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.backups.schedule') }}" method="POST">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <h6 class="text-muted"><i class="bi bi-clock me-1"></i>Schedule Settings</h6>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="scheduleEnabled" {{ $schedule->enabled ? 'checked' : '' }}>
                            <label class="form-check-label" for="scheduleEnabled">Enable</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="frequency" class="form-label">Frequency</label>
                        <select class="form-select" name="frequency" id="frequency" onchange="toggleDayFields()">
                            <option value="daily" {{ $schedule->frequency == 'daily' ? 'selected' : '' }}>Daily</option>
                            <option value="weekly" {{ $schedule->frequency == 'weekly' ? 'selected' : '' }}>Weekly</option>
                            <option value="monthly" {{ $schedule->frequency == 'monthly' ? 'selected' : '' }}>Monthly</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="time" class="form-label">Time</label>
                        <input type="time" class="form-control" name="time" id="time" value="{{ $schedule->time }}">
                    </div>
                    <div class="col-md-2" id="dayOfWeekGroup" style="{{ $schedule->frequency == 'weekly' ? '' : 'display:none' }}">
                        <label for="day_of_week" class="form-label">Day of Week</label>
                        <select class="form-select" name="day_of_week" id="day_of_week">
                            @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i => $day)
                            <option value="{{ $i }}" {{ ($schedule->day_of_week ?? 0) == $i ? 'selected' : '' }}>{{ $day }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2" id="dayOfMonthGroup" style="{{ $schedule->frequency == 'monthly' ? '' : 'display:none' }}">
                        <label for="day_of_month" class="form-label">Day of Month</label>
                        <input type="number" class="form-control" name="day_of_month" id="day_of_month" min="1" max="31" value="{{ $schedule->day_of_month ?? 1 }}">
                    </div>
                    <div class="col-md-3">
                        <label for="scheduleRemark" class="form-label">Remark</label>
                        <input type="text" class="form-control" name="remark" id="scheduleRemark" value="{{ $schedule->remark }}" placeholder="e.g. Daily backup">
                    </div>
                </div>

                <hr>

                <div class="row g-3">
                    <div class="col-12">
                        <h6 class="text-muted"><i class="bi bi-scissors me-1"></i>Retention Policy (like borgbackup)</h6>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Auto Prune</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="prune_enabled" value="1" id="pruneEnabled" {{ ($schedule->prune_enabled ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="pruneEnabled">Enable</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="keep_daily" class="form-label">Keep Daily</label>
                        <input type="number" class="form-control" name="keep_daily" id="keep_daily" min="0" max="365" value="{{ $schedule->keep_daily ?? 7 }}">
                        <small class="text-muted">Last N days</small>
                    </div>
                    <div class="col-md-2">
                        <label for="keep_weekly" class="form-label">Keep Weekly</label>
                        <input type="number" class="form-control" name="keep_weekly" id="keep_weekly" min="0" max="52" value="{{ $schedule->keep_weekly ?? 4 }}">
                        <small class="text-muted">Last N weeks</small>
                    </div>
                    <div class="col-md-2">
                        <label for="keep_monthly" class="form-label">Keep Monthly</label>
                        <input type="number" class="form-control" name="keep_monthly" id="keep_monthly" min="0" max="24" value="{{ $schedule->keep_monthly ?? 6 }}">
                        <small class="text-muted">Last N months</small>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-save me-1"></i>Save
                        </button>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Retention keeps the most recent backup for each period. Default: 7 daily + 4 weekly + 6 monthly = up to 17 backups.
                </p>
            </form>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; color:white;">
        <div class="spinner-border text-light" style="width:3rem; height:3rem;" role="status"></div>
        <p class="mt-3 fs-5" id="loadingText">Processing...</p>
    </div>
</div>

<script>
function toggleDayFields() {
    const freq = document.getElementById('frequency').value;
    document.getElementById('dayOfWeekGroup').style.display = freq === 'weekly' ? '' : 'none';
    document.getElementById('dayOfMonthGroup').style.display = freq === 'monthly' ? '' : 'none';
}

function showLoading(text) {
    document.getElementById('loadingText').innerText = text;
    document.getElementById('loadingOverlay').style.display = 'block';
}

function confirmRestore(filename) {
    if (confirm('⚠️ WARNING: Restore database from ' + filename + '?\n\nThis will OVERWRITE all current data. This action cannot be undone!\n\nAre you absolutely sure?')) {
        showLoading('Restoring database...');
        return true;
    }
    return false;
}

function toggleSelectAll(checkbox) {
    document.querySelectorAll('.backup-checkbox').forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const selected = document.querySelectorAll('.backup-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = selected;
    document.getElementById('deleteSelectedBtn').style.display = selected > 0 ? 'inline-block' : 'none';
}

function deleteSelected() {
    const count = document.querySelectorAll('.backup-checkbox:checked').length;
    if (confirm('Delete ' + count + ' selected backup(s)?')) {
        showLoading('Deleting backups...');
        document.getElementById('batchDeleteForm').submit();
    }
}
</script>

<!-- Create Backup Modal -->
<div class="modal fade" id="createBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.backups.create') }}" method="POST" onsubmit="showLoading('Creating backup...');">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Database Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="remark" class="form-label">Remark (Optional)</label>
                        <input type="text" class="form-control" id="remark" name="remark" placeholder="e.g. Before manual cleanup">
                    </div>
                    <p class="text-muted small">This will create a full snapshot of the current database state.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Backup</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Restore from File Modal -->
<div class="modal fade" id="restoreFromFileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.backups.restore-file') }}" method="POST" enctype="multipart/form-data" onsubmit="showLoading('Restoring database...');">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Restore from File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This will OVERWRITE all current data!
                    </div>
                    <div class="mb-3">
                        <label for="backup_file" class="form-label">Backup File (.zip, .sql.gz)</label>
                        <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".zip,.sql.gz,.gz,.sql" required>
                    </div>
                    <p class="text-muted small">Upload a previously downloaded backup file to restore the database.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Restore Database</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
