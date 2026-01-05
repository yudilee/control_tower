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
        .header { background: linear-gradient(135deg, #fd7e14, #dc3545); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .header .date { opacity: 0.9; font-size: 14px; }
        .content { padding: 25px; }
        
        /* Alert Box */
        .alert-box { background: linear-gradient(135deg, #fff3cd, #ffeeba); border: 2px solid #ffc107; border-radius: 12px; padding: 20px; margin-bottom: 25px; text-align: center; }
        .alert-icon { font-size: 32px; margin-bottom: 10px; }
        .alert-text { font-size: 16px; color: #856404; }
        .alert-text strong { color: #664d03; }
        
        /* Summary Cards */
        .summary-grid { display: table; width: 100%; margin-bottom: 25px; }
        .summary-card { display: table-cell; width: 25%; padding: 6px; vertical-align: top; }
        .summary-card-inner { border-radius: 12px; padding: 15px; text-align: center; }
        .summary-card.primary .summary-card-inner { background: linear-gradient(135deg, rgba(13, 110, 253, 0.12), rgba(13, 110, 253, 0.2)); border: 1px solid rgba(13, 110, 253, 0.3); }
        .summary-card.info .summary-card-inner { background: linear-gradient(135deg, rgba(23, 162, 184, 0.12), rgba(23, 162, 184, 0.2)); border: 1px solid rgba(23, 162, 184, 0.3); }
        .summary-card.secondary .summary-card-inner { background: linear-gradient(135deg, rgba(108, 117, 125, 0.12), rgba(108, 117, 125, 0.2)); border: 1px solid rgba(108, 117, 125, 0.3); }
        .summary-card.danger .summary-card-inner { background: linear-gradient(135deg, rgba(220, 53, 69, 0.12), rgba(220, 53, 69, 0.2)); border: 1px solid rgba(220, 53, 69, 0.3); }
        .summary-value { font-size: 22px; font-weight: 700; }
        .summary-card.primary .summary-value { color: #0d6efd; }
        .summary-card.info .summary-value { color: #17a2b8; }
        .summary-card.secondary .summary-value { color: #6c757d; }
        .summary-card.danger .summary-value { color: #dc3545; }
        .summary-label { font-size: 11px; color: #666; margin-top: 5px; }
        
        /* Aging Buckets */
        .bucket-grid { display: table; width: 100%; margin-bottom: 25px; }
        .bucket-card { display: table-cell; width: 25%; padding: 6px; vertical-align: top; }
        .bucket-inner { border-radius: 10px; overflow: hidden; border: 1px solid #e9ecef; }
        .bucket-header { padding: 10px; text-align: center; color: white; font-weight: 600; font-size: 13px; }
        .bucket-header.green { background: #28a745; }
        .bucket-header.blue { background: #17a2b8; }
        .bucket-header.orange { background: #fd7e14; }
        .bucket-header.red { background: #dc3545; }
        .bucket-body { padding: 12px; text-align: center; background: #fff; }
        .bucket-count { font-size: 24px; font-weight: 700; color: #343a40; }
        .bucket-amount { font-size: 11px; color: #6c757d; margin-top: 3px; }
        
        /* Section */
        .section { margin-bottom: 25px; }
        .section-header { background: #f8f9fa; padding: 12px 15px; border-radius: 8px 8px 0 0; border-bottom: 2px solid #e9ecef; }
        .section-title { font-weight: 600; font-size: 14px; color: #495057; }
        .section-badge { background: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 500; float: right; }
        
        /* Table */
        table.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.data-table th { background: #343a40; color: white; padding: 12px 10px; text-align: left; font-weight: 500; }
        table.data-table th.amount { text-align: right; }
        table.data-table th.days { text-align: center; }
        table.data-table td { padding: 10px; border-bottom: 1px solid #e9ecef; }
        table.data-table td.amount { text-align: right; font-family: 'Courier New', monospace; }
        table.data-table td.days { text-align: center; }
        table.data-table tr:nth-child(even) { background: #f8f9fa; }
        
        /* Days Badge */
        .days-badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .days-badge.green { background: #d4edda; color: #155724; }
        .days-badge.blue { background: #d1ecf1; color: #0c5460; }
        .days-badge.orange { background: #fff3cd; color: #856404; }
        .days-badge.red { background: #f8d7da; color: #721c24; }
        
        /* Filters Applied */
        .filters-applied { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px 15px; margin-bottom: 20px; font-size: 12px; }
        .filters-applied strong { color: #856404; }
        
        /* Footer */
        .footer { background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e9ecef; }
        .footer p { font-size: 12px; color: #6c757d; margin: 3px 0; }
        .footer a { color: #0d6efd; text-decoration: none; }
        
        @media only screen and (max-width: 600px) {
            .summary-grid, .summary-card, .bucket-grid, .bucket-card { display: block; width: 100%; }
            .summary-card, .bucket-card { margin-bottom: 10px; padding: 5px 0; }
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
            <!-- Alert Box -->
            <div class="alert-box">
                <div class="alert-icon">⚠️</div>
                <div class="alert-text">
                    <strong>Attention Required:</strong> There are <strong>{{ number_format($totalJobs) }}</strong> jobs 
                    older than <strong>{{ $agingDays }}</strong> days with a total value of 
                    <strong>Rp {{ number_format($totalAmount, 0, ',', '.') }}</strong>
                </div>
            </div>
            
            @if(!empty($appliedFilters))
            <div class="filters-applied">
                <strong>Filters Applied:</strong>
                @foreach($appliedFilters as $filter => $value)
                    {{ ucfirst(str_replace('_', ' ', $filter)) }}: {{ $value }}{{ !$loop->last ? ' • ' : '' }}
                @endforeach
            </div>
            @endif
            
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card primary">
                    <div class="summary-card-inner">
                        <div class="summary-value">{{ number_format($totalJobs) }}</div>
                        <div class="summary-label">Total Aging Jobs</div>
                    </div>
                </div>
                <div class="summary-card info">
                    <div class="summary-card-inner">
                        <div class="summary-value">Rp {{ number_format($totalAmount, 0, ',', '.') }}</div>
                        <div class="summary-label">Pending Sales</div>
                    </div>
                </div>
                <div class="summary-card secondary">
                    <div class="summary-card-inner">
                        <div class="summary-value">{{ number_format($avgAge ?? 0, 1) }}d</div>
                        <div class="summary-label">Average Age</div>
                    </div>
                </div>
                <div class="summary-card danger">
                    <div class="summary-card-inner">
                        <div class="summary-value">{{ number_format($criticalCount ?? 0) }}</div>
                        <div class="summary-label">Critical (30+ days)</div>
                    </div>
                </div>
            </div>

            @if(isset($agingGroups))
            <!-- Aging Buckets -->
            <div class="bucket-grid">
                @foreach($agingGroups as $key => $group)
                <div class="bucket-card">
                    <div class="bucket-inner">
                        <div class="bucket-header {{ $group['color'] }}">{{ $group['label'] }}</div>
                        <div class="bucket-body">
                            <div class="bucket-count">{{ number_format($group['count']) }}</div>
                            <div class="bucket-amount">Rp {{ number_format($group['amount'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            @if(isset($jobs) && count($jobs) > 0)
            <!-- Jobs Table -->
            <div class="section">
                <div class="section-header">
                    <span class="section-title">📋 Aging Jobs Detail</span>
                    <span class="section-badge">{{ count($jobs) }} jobs</span>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>WIP</th>
                            <th>Plate</th>
                            <th>Customer</th>
                            <th>SA</th>
                            <th>Job Date</th>
                            <th class="days">Days</th>
                            <th class="amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jobs as $job)
                        @php
                            $daysOld = $job->job_date ? (int) abs(now()->diffInDays($job->job_date)) : 0;
                            $daysBadgeClass = $daysOld >= 30 ? 'red' : ($daysOld >= 14 ? 'orange' : ($daysOld >= 7 ? 'blue' : 'green'));
                        @endphp
                        <tr>
                            <td><strong>{{ $job->job_number }}</strong></td>
                            <td>{{ $job->plate_number }}</td>
                            <td>{{ Str::limit($job->customer_name, 20) }}</td>
                            <td>{{ $job->service_advisor ?? '-' }}</td>
                            <td>{{ $job->job_date?->format('d/m/Y') }}</td>
                            <td class="days">
                                <span class="days-badge {{ $daysBadgeClass }}">{{ $daysOld }}d</span>
                            </td>
                            <td class="amount">{{ number_format($job->total_sales ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        
        <div class="footer">
            <p>This is an automated alert from <strong>Control Tower</strong></p>
            <p><a href="{{ config('app.url') }}">{{ config('app.url') }}</a></p>
        </div>
    </div>
</body>
</html>
