@extends('customer.layout')

@section('title', 'My Jobs')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-tools me-2"></i>My Jobs</h4>
    <div class="btn-group">
        <a href="{{ route('customer.jobs', ['status' => 'all']) }}" class="btn btn-{{ $status === 'all' ? 'primary' : 'outline-primary' }}">All</a>
        <a href="{{ route('customer.jobs', ['status' => 'uninvoiced']) }}" class="btn btn-{{ $status === 'uninvoiced' ? 'primary' : 'outline-primary' }}">In Progress</a>
        <a href="{{ route('customer.jobs', ['status' => 'invoiced']) }}" class="btn btn-{{ $status === 'invoiced' ? 'primary' : 'outline-primary' }}">Completed</a>
    </div>
</div>

<div class="card portal-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Job #</th>
                        <th>Date</th>
                        <th>Vehicle</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                    <tr>
                        <td><strong>{{ $job->job_number }}</strong></td>
                        <td>{{ $job->job_date?->format('d M Y') ?? '-' }}</td>
                        <td>{{ $job->plate_number }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($job->job_description, 30) }}</td>
                        <td>
                            @if($job->status === 'invoiced')
                            <span class="badge bg-success">Completed</span>
                            @else
                            <x-work-status :value="$job->work_status" />
                            @endif
                        </td>
                        <td>Rp {{ number_format($job->inv_ppn_meterai ?? $job->total_sales ?? 0, 0, ',', '.') }}</td>
                        <td>
                            <a href="{{ route('customer.jobs.show', $job) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                            @if($job->status === 'invoiced')
                            <a href="{{ route('customer.jobs.invoice', $job) }}" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-download"></i>
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-4 d-block mb-3 opacity-25"></i>
                            No jobs found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $jobs->links() }}
</div>
@endsection
