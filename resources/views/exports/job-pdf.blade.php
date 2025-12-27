<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details - {{ $job->job_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #0d6efd;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            background: #f8f9fa;
            padding: 8px 12px;
            border-left: 4px solid #0d6efd;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .field {
            margin-bottom: 8px;
        }
        .field-label {
            color: #666;
            font-size: 10px;
            text-transform: uppercase;
        }
        .field-value {
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .timeline {
            border-left: 2px solid #ddd;
            margin-left: 10px;
            padding-left: 20px;
        }
        .timeline-item {
            margin-bottom: 10px;
            position: relative;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #0d6efd;
        }
        .timeline-time {
            color: #666;
            font-size: 10px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            color: #666;
            font-size: 10px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Job #{{ $job->job_number }}</h1>
        <p>{{ $job->plate_number }} | {{ $job->customer_name }}</p>
        <p>Generated: {{ now()->format('d M Y H:i') }}</p>
    </div>

    <div class="section">
        <div class="section-title">Job Information</div>
        <div class="grid">
            <div class="field">
                <div class="field-label">WIP Number</div>
                <div class="field-value">{{ $job->job_number }}</div>
            </div>
            <div class="field">
                <div class="field-label">Job Card</div>
                <div class="field-value">{{ $job->job_card ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Job Date</div>
                <div class="field-value">{{ $job->job_date?->format('d M Y') ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Status</div>
                <div class="field-value">
                    <span class="badge {{ $job->invoice_number ? 'badge-success' : 'badge-warning' }}">
                        {{ $job->invoice_number ? 'Invoiced' : 'Open' }}
                    </span>
                </div>
            </div>
            <div class="field">
                <div class="field-label">Service Advisor</div>
                <div class="field-value">{{ $job->service_advisor ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Foreman</div>
                <div class="field-value">{{ $job->foreman ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Work Status</div>
                @php $wsOption = \App\Models\DropdownOption::getOption('work_status', $job->work_status); @endphp
                <div class="field-value">{{ $wsOption?->label ?? $job->work_status ?? 'Pending' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Needs Parts</div>
                <div class="field-value">{{ $job->need_part ? 'Yes' : 'No' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Customer & Vehicle</div>
        <div class="grid">
            <div class="field">
                <div class="field-label">Customer Name</div>
                <div class="field-value">{{ $job->customer_name ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Plate Number</div>
                <div class="field-value">{{ $job->plate_number ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Vehicle Model</div>
                <div class="field-value">{{ $job->vehicle?->model ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="field-label">Chassis Number</div>
                <div class="field-value">{{ $job->chassis_number ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Financial Summary</div>
        <div class="grid">
            <div class="field">
                <div class="field-label">Labour Sales</div>
                <div class="field-value">Rp {{ number_format($job->labour_sales ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="field">
                <div class="field-label">Part Sales</div>
                <div class="field-value">Rp {{ number_format($job->part_sales ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="field">
                <div class="field-label">Total Sales</div>
                <div class="field-value" style="font-size: 14px;">Rp {{ number_format($job->total_sales ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="field">
                <div class="field-label">Invoice Number</div>
                <div class="field-value">{{ $job->invoice_number ?? '-' }}</div>
            </div>
        </div>
    </div>

    @if($job->remarks->count() > 0)
    <div class="section">
        <div class="section-title">Remarks</div>
        <table>
            <tr>
                <th width="20%">Date</th>
                <th width="20%">User</th>
                <th>Remark</th>
            </tr>
            @foreach($job->remarks->take(10) as $remark)
            <tr>
                <td>{{ $remark->created_at->format('d M Y H:i') }}</td>
                <td>{{ $remark->user?->name ?? $remark->created_by ?? 'System' }}</td>
                <td>{{ $remark->content }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    @if($job->activities->count() > 0)
    <div class="section">
        <div class="section-title">Activity Timeline</div>
        <div class="timeline">
            @foreach($job->activities->take(10) as $activity)
            <div class="timeline-item">
                <div class="timeline-time">{{ $activity->created_at->format('d M Y H:i') }} | {{ $activity->user_name }}</div>
                <div>{{ $activity->description }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="footer">
        Control Tower - Workshop Management System | Printed: {{ now()->format('d M Y H:i:s') }}
    </div>

    <script>window.print();</script>
</body>
</html>
