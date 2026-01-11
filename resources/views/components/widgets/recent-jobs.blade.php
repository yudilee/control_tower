{{-- Widget: Recent Jobs Table --}}
@props(['recentJobs' => collect()])

<div class="card h-100">
    <div class="card-header-modern">
        <span class="card-header-title">
            <i class="bi bi-exclamation-triangle text-warning"></i>Recent Open Jobs
        </span>
        <a href="{{ route('jobs.index', ['status' => 'uninvoiced']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0 table-hover">
            <thead>
                <tr>
                    <th>Job #</th>
                    <th>Plate No</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentJobs as $job)
                <tr onclick="window.location='{{ route('jobs.show', $job) }}'" style="cursor: pointer;">
                    <td class="fw-bold text-primary">{{ $job->job_number }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $job->plate_number }}</span></td>
                    <td class="text-truncate" style="max-width: 150px;">{{ $job->customer_name }}</td>
                    <td>{{ $job->job_date?->format('d M') }}</td>
                    <td><x-work-status :value="$job->work_status" /></td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-5">
                        <i class="bi bi-check2-circle display-4 d-block mb-3 opacity-25"></i>
                        No uninvoiced jobs found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
