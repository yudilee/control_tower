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
        .header { background: linear-gradient(135deg, #ffc107, #e0a800); color: #333; padding: 30px; text-align: center; }
        .header h1 { font-size: 24px; font-weight: 600; margin-bottom: 5px; }
        .header .date { opacity: 0.8; font-size: 14px; }
        .content { padding: 25px; }
        
        /* Alert Box */
        .alert-box { background: linear-gradient(135deg, #fff3cd, #ffeeba); border: 2px solid #ffc107; border-radius: 12px; padding: 20px; margin-bottom: 25px; text-align: center; }
        .alert-icon { font-size: 32px; margin-bottom: 10px; }
        .alert-text { font-size: 16px; color: #856404; }
        .alert-text strong { color: #664d03; }
        
        /* Summary Cards */
        .summary-grid { display: table; width: 100%; margin-bottom: 25px; }
        .summary-card { display: table-cell; width: 33.33%; padding: 8px; vertical-align: top; }
        .summary-card-inner { border-radius: 12px; padding: 20px; text-align: center; }
        .summary-card.total .summary-card-inner { background: linear-gradient(135deg, rgba(255, 193, 7, 0.15), rgba(255, 193, 7, 0.25)); border: 1px solid rgba(255, 193, 7, 0.4); }
        .summary-card.pc .summary-card-inner { background: linear-gradient(135deg, rgba(0, 123, 255, 0.12), rgba(0, 123, 255, 0.2)); border: 1px solid rgba(0, 123, 255, 0.3); }
        .summary-card.cv .summary-card-inner { background: linear-gradient(135deg, rgba(108, 117, 125, 0.12), rgba(108, 117, 125, 0.2)); border: 1px solid rgba(108, 117, 125, 0.3); }
        .summary-value { font-size: 28px; font-weight: 700; }
        .summary-card.total .summary-value { color: #e0a800; }
        .summary-card.pc .summary-value { color: #007bff; }
        .summary-card.cv .summary-value { color: #6c757d; }
        .summary-label { font-size: 12px; color: #666; margin-top: 5px; }
        
        /* Section */
        .section { margin-bottom: 25px; }
        .section-header { background: #f8f9fa; padding: 12px 15px; border-radius: 8px 8px 0 0; border-bottom: 2px solid #e9ecef; }
        .section-title { font-weight: 600; font-size: 14px; color: #495057; }
        .section-badge { background: #ffc107; color: #333; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 500; float: right; }
        
        /* Table */
        table.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.data-table th { background: #343a40; color: white; padding: 12px 10px; text-align: left; font-weight: 500; }
        table.data-table td { padding: 10px; border-bottom: 1px solid #e9ecef; }
        table.data-table tr:nth-child(even) { background: #f8f9fa; }
        
        /* Status Badge */
        .status-badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 500; background: #fff3cd; color: #856404; }
        
        /* Filters Applied */
        .filters-applied { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px 15px; margin-bottom: 20px; font-size: 12px; }
        .filters-applied strong { color: #856404; }
        
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
            <!-- Alert Box -->
            <div class="alert-box">
                <div class="alert-icon">⚙️</div>
                <div class="alert-text">
                    There are <strong>{{ number_format($totalJobs) }}</strong> jobs currently waiting for parts
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
            
            @php
                $pcJobs = $jobs->where('franchise', 'PC');
                $cvJobs = $jobs->where('franchise', 'CV');
            @endphp
            
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card total">
                    <div class="summary-card-inner">
                        <div class="summary-value">{{ number_format($totalJobs) }}</div>
                        <div class="summary-label">Total Parts Pending</div>
                    </div>
                </div>
                <div class="summary-card pc">
                    <div class="summary-card-inner">
                        <div class="summary-value">{{ number_format($pcJobs->count()) }}</div>
                        <div class="summary-label">PC Jobs</div>
                    </div>
                </div>
                <div class="summary-card cv">
                    <div class="summary-card-inner">
                        <div class="summary-value">{{ number_format($cvJobs->count()) }}</div>
                        <div class="summary-label">CV Jobs</div>
                    </div>
                </div>
            </div>

            @if($jobs->isNotEmpty())
            <!-- Jobs Table -->
            <div class="section">
                <div class="section-header">
                    <span class="section-title">📋 Jobs Waiting for Parts</span>
                    <span class="section-badge">{{ $jobs->count() }} jobs</span>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>WIP</th>
                            <th>Plate</th>
                            <th>Customer</th>
                            <th>SA</th>
                            <th>Job Date</th>
                            <th>Work Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jobs as $job)
                        <tr>
                            <td><strong>{{ $job->job_number }}</strong></td>
                            <td>{{ $job->plate_number }}</td>
                            <td>{{ Str::limit($job->customer_name, 20) }}</td>
                            <td>{{ $job->service_advisor ?? '-' }}</td>
                            <td>{{ $job->job_date?->format('d/m/Y') }}</td>
                            <td><span class="status-badge">{{ $job->work_status ?? 'Pending' }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div style="text-align: center; padding: 40px; color: #6c757d;">
                <div style="font-size: 48px; margin-bottom: 15px;">✓</div>
                <p>No jobs are currently waiting for parts.</p>
            </div>
            @endif
        </div>
        
        <div class="footer">
            <p>This is an automated report from <strong>Control Tower</strong></p>
            <p><a href="{{ config('app.url') }}">{{ config('app.url') }}</a></p>
        </div>
    </div>
</body>
</html>
