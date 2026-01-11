{{-- Widget: SA Revenue Ranking --}}
@props(['chartData' => null])

<div class="card h-100">
    <div class="card-header bg-light"><i class="bi bi-currency-dollar me-2"></i>Top 5 SA Revenue (Uninvoiced)</div>
    <div class="card-body">
        <canvas id="saRevenueChart" height="200"></canvas>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const saCtx = document.getElementById('saRevenueChart');
    if (saCtx && typeof Chart !== 'undefined') {
        const saRevenue = @json($chartData['saRevenue'] ?? []);
        new Chart(saCtx, {
            type: 'bar',
            data: {
                labels: saRevenue.map(s => s.service_advisor?.substring(0, 15) ?? 'N/A'),
                datasets: [{
                    label: 'Revenue',
                    data: saRevenue.map(s => s.revenue),
                    backgroundColor: '#0d6efd',
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + (value/1000000).toFixed(1) + 'M';
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
@endpush
