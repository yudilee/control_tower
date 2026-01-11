{{-- Widget: Pending Invoices (Finance) --}}
@props(['pendingInvoices' => collect()])

<div class="card h-100">
    <div class="card-header-modern">
        <span class="card-header-title">
            <i class="bi bi-receipt text-warning"></i>Pending Invoices
        </span>
        <a href="{{ route('finance.kanban') }}" class="btn btn-sm btn-outline-warning rounded-pill px-3">View All</a>
    </div>
    <div class="list-group list-group-flush">
        @forelse($pendingInvoices as $invoice)
        <a href="{{ route('jobs.show', $invoice->job_id) }}" class="list-group-item list-group-item-action py-3">
            <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                <h6 class="mb-0 fw-bold">{{ $invoice->invoice_number }}</h6>
                <span class="text-success fw-bold">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span>
            </div>
            <p class="mb-1 small text-muted">{{ $invoice->job->customer_name ?? 'N/A' }}</p>
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">{{ $invoice->invoice_date?->format('d M Y') }}</small>
                @if($invoice->status == 'pending')
                <span class="badge bg-warning text-dark">Pending</span>
                @elseif($invoice->status == 'partially_paid')
                <span class="badge bg-info">Partial</span>
                @endif
            </div>
        </a>
        @empty
        <div class="text-center py-5 text-muted">
            <i class="bi bi-check-circle display-4 d-block mb-3 opacity-25"></i>
            All invoices paid
        </div>
        @endforelse
    </div>
</div>
