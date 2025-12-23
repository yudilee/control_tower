<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .alert { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #343a40; color: white; }
        .amount { text-align: right; }
        .urgent { color: #dc3545; font-weight: bold; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $reportTitle }}</h1>
        <p>{{ $reportDate }}</p>
    </div>
    
    <div class="content">
        <div class="alert">
            <strong>⚠️ Attention Required:</strong> There are <strong>{{ $totalJobs }}</strong> jobs older than {{ $agingDays }} days
            with a total value of <strong>Rp {{ number_format($totalAmount, 0, ',', '.') }}</strong>
        </div>

        <table>
            <tr>
                <th>WIP</th>
                <th>Plate</th>
                <th>Customer</th>
                <th>SA</th>
                <th>Date</th>
                <th>Days</th>
                <th class="amount">Amount</th>
            </tr>
            @foreach($jobs as $job)
            <tr>
                <td>{{ $job->job_number }}</td>
                <td>{{ $job->plate_number }}</td>
                <td>{{ Str::limit($job->customer_name, 25) }}</td>
                <td>{{ $job->service_advisor ?? '-' }}</td>
                <td>{{ $job->job_date?->format('d/m/Y') }}</td>
                <td class="urgent">{{ $job->job_date ? now()->diffInDays($job->job_date) : '-' }}d</td>
                <td class="amount">Rp {{ number_format($job->total_sales ?? 0, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    
    <div class="footer">
        <p>This is an automated alert from Control Tower.</p>
        <p>{{ config('app.url') }}</p>
    </div>
</body>
</html>
