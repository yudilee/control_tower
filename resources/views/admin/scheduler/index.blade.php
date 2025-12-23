@extends('layouts.app')

@section('title', 'Scheduler Management')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-clock-history me-2"></i>Scheduler Management</h1>
        <p class="text-muted mb-0">Manage scheduled background tasks</p>
    </div>
    <a href="{{ route('admin.scheduler.logs') }}" class="btn btn-outline-secondary">
        <i class="bi bi-journal-text me-1"></i>View All Logs
    </a>
</div>

<div class="card">
    <div class="card-header">
        <i class="bi bi-list-task me-1"></i>Scheduled Tasks
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th style="width:60px;">Status</th>
                    <th>Task</th>
                    <th>Schedule</th>
                    <th>Next Run</th>
                    <th>Last Run</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($schedules as $schedule)
                <tr class="{{ !$schedule->is_enabled ? 'table-secondary' : '' }}">
                    <td class="text-center">
                        <form action="{{ route('admin.scheduler.toggle', $schedule) }}" method="POST" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-link p-0" title="{{ $schedule->is_enabled ? 'Click to disable' : 'Click to enable' }}">
                                <i class="bi bi-{{ $schedule->is_enabled ? 'check-circle-fill text-success' : 'x-circle-fill text-secondary' }}" style="font-size: 1.3rem;"></i>
                            </button>
                        </form>
                    </td>
                    <td>
                        <strong>{{ $schedule->name }}</strong>
                        <br><small class="text-muted">{{ $schedule->description }}</small>
                        <br><code class="small">{{ $schedule->command }}</code>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal{{ $schedule->id }}">
                            <i class="bi bi-pencil me-1"></i>{{ $schedule->schedule_description }}
                        </button>
                    </td>
                    <td>
                        @if($schedule->is_enabled)
                        <i class="bi bi-calendar-event text-primary me-1"></i>{{ $schedule->next_run }}
                        @else
                        <span class="text-muted">Disabled</span>
                        @endif
                    </td>
                    <td>
                        @if($schedule->last_run_at)
                        <span class="badge bg-{{ $schedule->last_status === 'success' ? 'success' : 'danger' }}">
                            {{ ucfirst($schedule->last_status) }}
                        </span>
                        <br><small class="text-muted">{{ $schedule->last_run_at->diffForHumans() }}</small>
                        @else
                        <span class="text-muted">Never</span>
                        @endif
                    </td>
                    <td>
                        <form action="{{ route('admin.scheduler.run') }}" method="POST" class="d-inline"
                              onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm\'></span>';">
                            @csrf
                            <input type="hidden" name="command" value="{{ $schedule->command }}">
                            <button type="submit" class="btn btn-sm btn-success" {{ !$schedule->is_enabled ? 'disabled' : '' }}>
                                <i class="bi bi-play-fill"></i> Run
                            </button>
                        </form>
                    </td>
                </tr>
                
                <!-- Edit Modal -->
                <div class="modal fade" id="editModal{{ $schedule->id }}" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('admin.scheduler.update', $schedule) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Schedule: {{ $schedule->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Frequency</label>
                                        <select name="schedule" class="form-select" id="freq{{ $schedule->id }}" onchange="toggleDayField({{ $schedule->id }})">
                                            <option value="every_minute" {{ $schedule->schedule === 'every_minute' ? 'selected' : '' }}>Every Minute</option>
                                            <option value="hourly" {{ $schedule->schedule === 'hourly' ? 'selected' : '' }}>Hourly</option>
                                            <option value="daily" {{ $schedule->schedule === 'daily' ? 'selected' : '' }}>Daily</option>
                                            <option value="weekly" {{ $schedule->schedule === 'weekly' ? 'selected' : '' }}>Weekly</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Time</label>
                                        <input type="time" name="time" class="form-control" value="{{ $schedule->time }}">
                                    </div>
                                    <div class="mb-3" id="dayField{{ $schedule->id }}" style="{{ $schedule->schedule !== 'weekly' ? 'display:none;' : '' }}">
                                        <label class="form-label">Day of Week</label>
                                        <select name="day_of_week" class="form-select">
                                            <option value="0" {{ $schedule->day_of_week == 0 ? 'selected' : '' }}>Sunday</option>
                                            <option value="1" {{ $schedule->day_of_week == 1 ? 'selected' : '' }}>Monday</option>
                                            <option value="2" {{ $schedule->day_of_week == 2 ? 'selected' : '' }}>Tuesday</option>
                                            <option value="3" {{ $schedule->day_of_week == 3 ? 'selected' : '' }}>Wednesday</option>
                                            <option value="4" {{ $schedule->day_of_week == 4 ? 'selected' : '' }}>Thursday</option>
                                            <option value="5" {{ $schedule->day_of_week == 5 ? 'selected' : '' }}>Friday</option>
                                            <option value="6" {{ $schedule->day_of_week == 6 ? 'selected' : '' }}>Saturday</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Logs -->
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-journal-text me-1"></i>Recent Execution Logs</span>
        <a href="{{ route('admin.scheduler.logs') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Task</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Triggered By</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentLogs as $log)
                <tr>
                    <td><small>{{ $log->created_at->format('d/m H:i:s') }}</small></td>
                    <td><code class="small">{{ $log->command }}</code></td>
                    <td>
                        <span class="badge bg-{{ $log->status === 'success' ? 'success' : ($log->status === 'running' ? 'warning' : 'danger') }}">
                            {{ ucfirst($log->status) }}
                        </span>
                    </td>
                    <td>{{ $log->duration_formatted }}</td>
                    <td><span class="badge bg-{{ $log->triggered_by === 'manual' ? 'info' : 'secondary' }}">{{ ucfirst($log->triggered_by) }}</span></td>
                    <td>
                        @if($log->status === 'success')
                        <small class="text-success text-truncate d-inline-block" style="max-width: 200px;">{{ Str::limit($log->output, 50) }}</small>
                        @elseif($log->status === 'failed')
                        <small class="text-danger text-truncate d-inline-block" style="max-width: 200px;">{{ Str::limit($log->error, 50) }}</small>
                        @else
                        <small class="text-muted">Running...</small>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">No execution logs yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-info-circle me-1"></i>Cron Setup
    </div>
    <div class="card-body">
        <p class="mb-2">For automatic scheduling to work, add this cron job on your server:</p>
        <pre class="bg-dark text-light p-3 rounded mb-0"><code>* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1</code></pre>
    </div>
</div>

<script>
function toggleDayField(id) {
    const freq = document.getElementById('freq' + id).value;
    const dayField = document.getElementById('dayField' + id);
    dayField.style.display = freq === 'weekly' ? 'block' : 'none';
}
</script>
@endsection
