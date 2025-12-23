<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #198754; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
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
        <p><strong>Total New Jobs:</strong> {{ $totalNewJobs }} | <strong>Total Invoiced:</strong> {{ $totalInvoiced }}</p>

        <h3>SA Performance</h3>
        <table>
            <tr>
                <th>Service Advisor</th>
                <th>New Jobs</th>
                <th class="amount">New Amount</th>
                <th>Invoiced</th>
                <th class="amount">Invoiced Amount</th>
            </tr>
            @foreach($performance as $sa => $data)
            <tr>
                <td>{{ $sa }}</td>
                <td>{{ $data['new_jobs'] }}</td>
                <td class="amount">Rp {{ number_format($data['new_amount'], 0, ',', '.') }}</td>
                <td>{{ $data['invoiced'] }}</td>
                <td class="amount">Rp {{ number_format($data['invoiced_amount'], 0, ',', '.') }}</td>
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
