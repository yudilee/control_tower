{{-- Widget: My Jobs (Jobs assigned to current user) --}}
@props(['myJobs' => collect()])

<div class="card h-100">
    <div class="card-header-modern">
        <span class="card-header-title">
            <i class="bi bi-briefcase-fill text-primary"></i>My Jobs
        </span>
        <a href="{{ route('jobs.index', ['my_jobs' => 1]) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-modern mb-0 table-hover">
            <thead>
                <tr>
                    <th>Job #</th>
                    <th>Plate No</th>
                    <th>Customer</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($myJobs as $job)
                <tr onclick="window.location='{{ route('jobs.show', $job) }}'" style="cursor: pointer;">
                    <td class="fw-bold text-primary">{{ $job->job_number }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $job->plate_number }}</span></td>
                    <td class="text-truncate" style="max-width: 120px;">{{ $job->customer_name }}</td>
                    <td><x-work-status :value="$job->work_status" /></td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">
                        <i class="bi bi-inbox display-4 d-block mb-2 opacity-25"></i>
                        No jobs assigned to you
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
