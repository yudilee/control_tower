{{-- Widget: Job Trend Chart (7 Days) --}}
@props(['chartData' => null])

<div class="card h-100">
    <div class="card-header bg-light"><i class="bi bi-graph-up me-2"></i>Job Trend (Last 7 Days)</div>
    <div class="card-body">
        <canvas id="jobTrendChart" height="120"></canvas>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const trendCtx = document.getElementById('jobTrendChart');
    if (trendCtx && typeof Chart !== 'undefined') {
        const chartData = @json($chartData['last7Days'] ?? []);
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: chartData.map(d => d.date),
                datasets: [
                    {
                        label: 'New Jobs',
                        data: chartData.map(d => d.new),
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Invoiced',
                        data: chartData.map(d => d.invoiced),
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }
});
</script>
@endpush
