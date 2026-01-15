@extends('layouts.app')

@section('title', 'Sanitization History')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('admin.data-cleanup.index') }}">Data Cleanup</a></li>
                <li class="breadcrumb-item active">Sanitization History</li>
            </ol>
        </nav>
        <h1><i class="bi bi-clock-history me-2"></i>Sanitization History</h1>
    </div>
    <a href="{{ route('admin.data-cleanup.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Data Cleanup
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Records Affected</th>
                        <th>Run By</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if($log->type == 'customer_address')
                            <span class="badge bg-info">Customer Addresses</span>
                            @else
                            <span class="badge bg-secondary">{{ $log->type }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="fw-bold text-success">{{ number_format($log->records_affected) }}</span> records
                        </td>
                        <td>{{ $log->run_by }}</td>
                        <td>
                            @if($log->details && count($log->details) > 0)
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#details{{ $log->id }}">
                                <i class="bi bi-eye me-1"></i>View Sample ({{ count($log->details) }})
                            </button>
                            <div class="collapse mt-2" id="details{{ $log->id }}">
                                <div class="card card-body small bg-light">
                                    @foreach($log->details as $item)
                                    <div class="mb-2 pb-2 border-bottom">
                                        <strong>{{ $item['name'] ?? 'Unknown' }}</strong> (ID: {{ $item['customer_id'] ?? '?' }})
                                        <ul class="mb-0 mt-1">
                                            @foreach(($item['changes'] ?? []) as $change)
                                            <li class="text-muted">{{ $change }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No sanitization history yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
