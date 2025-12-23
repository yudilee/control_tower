<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .summary { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .summary-item { display: inline-block; margin-right: 30px; }
        .summary-value { font-size: 24px; font-weight: bold; color: #0d6efd; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #343a40; color: white; }
        .amount { text-align: right; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $reportTitle }}</h1>
        <p>{{ $reportDate }}</p>
    </div>
    
    <div class="content">
        <div class="summary">
            <div class="summary-item">
                <div class="summary-value">{{ $totalJobs }}</div>
                <div>Uninvoiced Jobs</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">Rp {{ number_format($totalAmount, 0, ',', '.') }}</div>
                <div>Total Estimated</div>
            </div>
        </div>

        @if($byFranchise->isNotEmpty())
        <h3>By Franchise</h3>
        <table>
            <tr>
                <th>Franchise</th>
                <th>Jobs</th>
                <th class="amount">Amount</th>
            </tr>
            @foreach($byFranchise as $franchise => $data)
            <tr>
                <td>{{ $franchise ?: 'Unassigned' }}</td>
                <td>{{ $data['count'] }}</td>
                <td class="amount">Rp {{ number_format($data['amount'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </table>
        @endif

        @if($bySA->isNotEmpty())
        <h3>Top Service Advisors</h3>
        <table>
            <tr>
                <th>Service Advisor</th>
                <th>Jobs</th>
                <th class="amount">Amount</th>
            </tr>
            @foreach($bySA as $sa => $data)
            <tr>
                <td>{{ $sa ?: 'Unassigned' }}</td>
                <td>{{ $data['count'] }}</td>
                <td class="amount">Rp {{ number_format($data['amount'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </table>
        @endif
    </div>
    
    <div class="footer">
        <p>This is an automated report from Control Tower.</p>
        <p>{{ config('app.url') }}</p>
    </div>
</body>
</html>
