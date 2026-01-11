{{-- Widget: Today's Bookings --}}
@props(['bookingsToday' => collect()])

<div class="card h-100">
    <div class="card-header-modern">
        <span class="card-header-title">
            <i class="bi bi-calendar-check-fill text-success"></i>Today's Bookings
        </span>
        <a href="{{ route('bookings.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">View All</a>
    </div>
    <div class="list-group list-group-flush">
        @forelse($bookingsToday as $booking)
        <a href="{{ route('bookings.show', $booking) }}" class="list-group-item list-group-item-action py-3">
            <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                <h6 class="mb-0 fw-bold">{{ $booking->customer_name }}</h6>
                <small class="text-muted">{{ $booking->booking_time?->format('H:i') ?? 'TBD' }}</small>
            </div>
            <p class="mb-1 small text-muted">{{ $booking->plate_number }} • {{ $booking->service_type }}</p>
            @if($booking->status == 'confirmed')
            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Confirmed</span>
            @elseif($booking->status == 'pending')
            <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>
            @else
            <span class="badge bg-secondary">{{ ucfirst($booking->status) }}</span>
            @endif
        </a>
        @empty
        <div class="text-center py-5 text-muted">
            <i class="bi bi-calendar-x display-4 d-block mb-3 opacity-25"></i>
            No bookings for today
        </div>
        @endforelse
    </div>
</div>
