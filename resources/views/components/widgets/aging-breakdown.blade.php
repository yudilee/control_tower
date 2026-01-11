{{-- Widget: Aging Breakdown Chart --}}
@props(['chartData' => null])

<div class="card h-100">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span><i class="bi bi-hourglass-split me-2"></i>Job Aging (Uninvoiced)</span>
        <a href="{{ route('reports.aging') }}" class="btn btn-sm btn-outline-primary">Full Report</a>
    </div>
    <div class="card-body">
        <canvas id="agingChart" height="200"></canvas>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const agingCtx = document.getElementById('agingChart');
    if (agingCtx && typeof Chart !== 'undefined') {
        const agingData = @json($chartData['agingData'] ?? []);
        new Chart(agingCtx, {
            type: 'doughnut',
            data: {
                labels: agingData.map(d => d.label),
                datasets: [{
                    data: agingData.map(d => d.count),
                    backgroundColor: agingData.map(d => d.color),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }
});
</script>
@endpush
