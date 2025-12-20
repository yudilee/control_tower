@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0 text-gray-800">Database Backups</h1>
            <p class="text-muted small">Manage database backups, view audit logs, and perform restoration.</p>
        </div>
        <div class="col-md-6 text-end">
            <!-- Create Backup Button triggering Modal -->
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                <i class="bi bi-plus-circle me-1"></i> Create New Backup
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
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
                                <td>{{ number_format($backup->size / 1048576, 2) }} MB</td>
                                <td>
                                    <span class="badge bg-secondary">{{ $backup->created_by ?? 'System' }}</span>
                                </td>
                                <td>{{ $backup->created_at->format('d M Y H:i:s') }} <br> <small class="text-muted">({{ $backup->created_at->diffForHumans() }})</small></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.backups.download', $backup->filename) }}" class="btn btn-outline-secondary" title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <form action="{{ route('admin.backups.restore', $backup->filename) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger" title="Restore" onclick="return confirm('⚠️ WARNING: Restore database from {{ $backup->filename }}?\n\nThis will OVERWRITE all current data. This action cannot be undone!\n\nAre you absolutely sure?');">
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
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No backups found. Create one to get started.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Schedule Configuration Card -->
    <div class="card shadow mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Scheduled Backup</h5>
            <span class="badge {{ $schedule->enabled ? 'bg-success' : 'bg-secondary' }}">
                {{ $schedule->enabled ? 'Enabled' : 'Disabled' }}
            </span>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.backups.schedule') }}" method="POST">
                @csrf
                <div class="row g-3">
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
                            <option value="0" {{ $schedule->day_of_week == 0 ? 'selected' : '' }}>Sunday</option>
                            <option value="1" {{ $schedule->day_of_week == 1 ? 'selected' : '' }}>Monday</option>
                            <option value="2" {{ $schedule->day_of_week == 2 ? 'selected' : '' }}>Tuesday</option>
                            <option value="3" {{ $schedule->day_of_week == 3 ? 'selected' : '' }}>Wednesday</option>
                            <option value="4" {{ $schedule->day_of_week == 4 ? 'selected' : '' }}>Thursday</option>
                            <option value="5" {{ $schedule->day_of_week == 5 ? 'selected' : '' }}>Friday</option>
                            <option value="6" {{ $schedule->day_of_week == 6 ? 'selected' : '' }}>Saturday</option>
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
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-save"></i> Save
                        </button>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    For scheduling to work, ensure a cron job runs <code>php artisan schedule:run</code> every minute on your server.
                </p>
            </form>
        </div>
    </div>
</div>

<script>
function toggleDayFields() {
    const freq = document.getElementById('frequency').value;
    document.getElementById('dayOfWeekGroup').style.display = freq === 'weekly' ? '' : 'none';
    document.getElementById('dayOfMonthGroup').style.display = freq === 'monthly' ? '' : 'none';
}
</script>

<!-- Create Backup Modal -->
<div class="modal fade" id="createBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.backups.create') }}" method="POST">
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
@endsection
