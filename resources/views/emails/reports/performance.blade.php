<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportTitle }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; }
        .email-container { max-width: 700px; margin: 0 auto; background: #fff; }
        .header { background: linear-gradient(135deg, #0d6efd, #0056b3); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .header .date { opacity: 0.9; font-size: 14px; }
        .content { padding: 25px; }
        
        /* Summary Cards */
        .summary-grid { display: table; width: 100%; margin-bottom: 25px; }
        .summary-card { display: table-cell; width: 25%; padding: 6px; vertical-align: top; }
        .summary-card-inner { border-radius: 12px; padding: 18px 12px; text-align: center; }
        .summary-card.new .summary-card-inner { background: linear-gradient(135deg, rgba(0, 123, 255, 0.12), rgba(0, 123, 255, 0.2)); border: 1px solid rgba(0, 123, 255, 0.3); }
        .summary-card.invoiced .summary-card-inner { background: linear-gradient(135deg, rgba(40, 167, 69, 0.12), rgba(40, 167, 69, 0.2)); border: 1px solid rgba(40, 167, 69, 0.3); }
        .summary-card.pending .summary-card-inner { background: linear-gradient(135deg, rgba(255, 193, 7, 0.12), rgba(255, 193, 7, 0.2)); border: 1px solid rgba(255, 193, 7, 0.3); }
        .summary-card.rate .summary-card-inner { background: linear-gradient(135deg, rgba(108, 117, 125, 0.12), rgba(108, 117, 125, 0.2)); border: 1px solid rgba(108, 117, 125, 0.3); }
        .summary-value { font-size: 22px; font-weight: 700; }
        .summary-card.new .summary-value { color: #0d6efd; }
        .summary-card.invoiced .summary-value { color: #28a745; }
        .summary-card.pending .summary-value { color: #e0a800; }
        .summary-card.rate .summary-value { color: #6c757d; }
        .summary-label { font-size: 11px; color: #666; margin-top: 5px; }
        .summary-sub { font-size: 10px; color: #888; margin-top: 3px; }
        
        /* Section */
        .section { margin-bottom: 25px; }
        .section-header { background: #f8f9fa; padding: 12px 15px; border-radius: 8px 8px 0 0; border-bottom: 2px solid #e9ecef; }
        .section-title { font-weight: 600; font-size: 14px; color: #495057; }
        .section-badge { background: #0d6efd; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 500; float: right; }
        
        /* Performance Table */
        table.perf-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.perf-table th { background: #343a40; color: white; padding: 12px 10px; text-align: left; font-weight: 500; }
        table.perf-table th.number { text-align: right; }
        table.perf-table th.center { text-align: center; }
        table.perf-table td { padding: 12px 10px; border-bottom: 1px solid #e9ecef; }
        table.perf-table td.number { text-align: right; font-family: 'Courier New', monospace; }
        table.perf-table td.center { text-align: center; }
        table.perf-table tr:nth-child(even) { background: #f8f9fa; }
        table.perf-table tr:hover { background: #e9ecef; }
        
        /* SA Name with rank */
        .sa-rank { display: inline-block; width: 22px; height: 22px; border-radius: 50%; text-align: center; line-height: 22px; font-size: 11px; font-weight: 600; margin-right: 8px; }
        .rank-1 { background: #ffc107; color: #664d03; }
        .rank-2 { background: #e9ecef; color: #495057; }
        .rank-3 { background: #cd7f32; color: white; }
        .rank-other { background: #f8f9fa; color: #6c757d; }
        
        /* Progress Bar */
        .progress-bar { height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden; margin-top: 4px; }
        .progress-fill { height: 100%; border-radius: 3px; }
        .progress-fill.new { background: #0d6efd; }
        .progress-fill.invoiced { background: #28a745; }
        
        /* Metric Cells */
        .metric-cell { min-width: 80px; }
        .metric-value { font-weight: 600; font-size: 14px; }
        .metric-value.new { color: #0d6efd; }
        .metric-value.invoiced { color: #28a745; }
        .metric-sub { font-size: 10px; color: #888; }
        
        /* Rate Badge */
        .rate-badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .rate-badge.high { background: #d4edda; color: #155724; }
        .rate-badge.medium { background: #fff3cd; color: #856404; }
        .rate-badge.low { background: #f8d7da; color: #721c24; }
        
        /* Filters Applied */
        .filters-applied { background: #e7f3ff; border: 1px solid #b6d4fe; border-radius: 8px; padding: 12px 15px; margin-bottom: 20px; font-size: 12px; }
        .filters-applied strong { color: #0d6efd; }
        
        /* Footer */
        .footer { background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e9ecef; }
        .footer p { font-size: 12px; color: #6c757d; margin: 3px 0; }
        .footer a { color: #0d6efd; text-decoration: none; }
        
        @media only screen and (max-width: 600px) {
            .summary-grid, .summary-card { display: block; width: 100%; }
            .summary-card { margin-bottom: 10px; padding: 5px 0; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>{{ $reportTitle }}</h1>
            <div class="date">{{ $reportDate }}</div>
        </div>
        
        <div class="content">
            @if(!empty($appliedFilters))
            <div class="filters-applied">
                <strong>Period:</strong>
                @foreach($appliedFilters as $filter => $value)
                    {{ ucfirst(str_replace('_', ' ', $filter)) }}: {{ $value }}{{ !$loop->last ? ' • ' : '' }}
                @endforeach
            </div>
            @endif
            
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card new">
                    <div class="summary-card-inner">
                        <div class="summary-value">{{ number_format($totalNewJobs) }}</div>
                        <div class="summary-label">New Jobs</div>
                        <div class="summary-sub">Rp {{ number_format($totalNewAmount ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="summary-card invoiced">
                    <div class="summary-card-inner">
                        <div class="summary-value">{{ number_format($totalInvoiced) }}</div>
                        <div class="summary-label">Invoiced</div>
                        <div class="summary-sub">Rp {{ number_format($totalInvoicedAmount ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="summary-card pending">
                    <div class="summary-card-inner">
                        <div class="summary-value">{{ number_format($totalPending ?? ($totalNewJobs - $totalInvoiced)) }}</div>
                        <div class="summary-label">Still Pending</div>
                        <div class="summary-sub">In progress</div>
                    </div>
                </div>
                <div class="summary-card rate">
                    <div class="summary-card-inner">
                        @php
                            $rate = $totalNewJobs > 0 ? round(($totalInvoiced / $totalNewJobs) * 100, 1) : 0;
                        @endphp
                        <div class="summary-value">{{ $rate }}%</div>
                        <div class="summary-label">Close Rate</div>
                        <div class="summary-sub">This period</div>
                    </div>
                </div>
            </div>

            <!-- Performance Table -->
            <div class="section">
                <div class="section-header">
                    <span class="section-title">📊 SA Performance Breakdown</span>
                    <span class="section-badge">{{ count($performance) }} SAs</span>
                </div>
                <table class="perf-table">
                    <thead>
                        <tr>
                            <th>Service Advisor</th>
                            <th class="number">New Jobs</th>
                            <th class="number">New Amount</th>
                            <th class="number">Invoiced</th>
                            <th class="number">Invoiced Amount</th>
                            <th class="center">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $rank = 1; @endphp
                        @foreach($performance as $sa => $data)
                        @php
                            $closeRate = $data['new_jobs'] > 0 ? round(($data['invoiced'] / $data['new_jobs']) * 100, 1) : 0;
                            $rateClass = $closeRate >= 75 ? 'high' : ($closeRate >= 50 ? 'medium' : 'low');
                            $rankClass = $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : 'rank-other'));
                        @endphp
                        <tr>
                            <td>
                                <span class="sa-rank {{ $rankClass }}">{{ $rank }}</span>
                                <strong>{{ $sa }}</strong>
                            </td>
                            <td class="number">
                                <div class="metric-cell">
                                    <div class="metric-value new">{{ $data['new_jobs'] }}</div>
                                </div>
                            </td>
                            <td class="number">Rp {{ number_format($data['new_amount'], 0, ',', '.') }}</td>
                            <td class="number">
                                <div class="metric-cell">
                                    <div class="metric-value invoiced">{{ $data['invoiced'] }}</div>
                                </div>
                            </td>
                            <td class="number">Rp {{ number_format($data['invoiced_amount'], 0, ',', '.') }}</td>
                            <td class="center">
                                <span class="rate-badge {{ $rateClass }}">{{ $closeRate }}%</span>
                            </td>
                        </tr>
                        @php $rank++; @endphp
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an automated report from <strong>Control Tower</strong></p>
            <p><a href="{{ config('app.url') }}">{{ config('app.url') }}</a></p>
        </div>
    </div>
</body>
</html>
