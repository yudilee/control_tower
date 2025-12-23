<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #ffc107; color: #333; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #343a40; color: white; }
        .footer { background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $reportTitle }}</h1>
        <p>{{ $reportDate }}</p>
    </div>
    
    <div class="content">
        <p>There are <strong>{{ $totalJobs }}</strong> jobs waiting for parts.</p>

        <table>
            <tr>
                <th>WIP</th>
                <th>Plate</th>
                <th>Customer</th>
                <th>SA</th>
                <th>Date</th>
                <th>RQ Status</th>
            </tr>
            @foreach($jobs as $job)
            <tr>
                <td>{{ $job->job_number }}</td>
                <td>{{ $job->plate_number }}</td>
                <td>{{ Str::limit($job->customer_name, 25) }}</td>
                <td>{{ $job->service_advisor ?? '-' }}</td>
                <td>{{ $job->job_date?->format('d/m/Y') }}</td>
                <td>{{ $job->rq ?? '-' }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    
    <div class="footer">
        <p>This is an automated report from Control Tower.</p>
        <p>{{ config('app.url') }}</p>
    </div>
</body>
</html>
