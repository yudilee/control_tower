<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uninvoiced Jobs Report - {{ now()->format('d M Y') }}</title>
    <style>
        @page {
            margin: 15mm 10mm 20mm 10mm;
        }
        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            box-sizing: border-box;
        }
        body {
            font-size: 10px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #dc3545;
        }
        .header .subtitle {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        .summary-cards {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 10px;
        }
        .summary-card {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .summary-card.total {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.2));
            border-color: rgba(220, 53, 69, 0.4);
        }
        .summary-card.pc {
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(0, 123, 255, 0.2));
            border-color: rgba(0, 123, 255, 0.4);
        }
        .summary-card.cv {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.2));
            border-color: rgba(255, 193, 7, 0.4);
        }
        .summary-card .value {
            font-size: 16px;
            font-weight: bold;
        }
        .summary-card.total .value { color: #dc3545; }
        .summary-card.pc .value { color: #007bff; }
        .summary-card.cv .value { color: #e0a800; }
        .summary-card .label {
            font-size: 9px;
            color: #666;
            margin-top: 3px;
        }
        .summary-card .sales {
            display: flex;
            justify-content: space-around;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #ccc;
            font-size: 8px;
        }
        .summary-card .sales .item .val {
            font-weight: 600;
        }
        .filters-applied {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 9px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        th {
            background: #343a40;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 6px;
            border-bottom: 1px solid #dee2e6;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
        .text-end {
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            background: #6c757d;
            color: white;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #999;
            padding: 10px;
            border-top: 1px solid #ddd;
            background: white;
        }
        .footer .page-number::after {
            content: counter(page);
        }
        @media print {
            .no-print { display: none; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📋 Uninvoiced Jobs Report</h1>
        <div class="subtitle">Generated on {{ now()->format('d M Y, H:i') }}</div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card total">
            <div class="value">{{ number_format($totalJobCount) }} Jobs</div>
            <div class="label">Total Uninvoiced</div>
            <div class="sales">
                <div class="item"><div class="val">Rp {{ number_format($totalLabour, 0, ',', '.') }}</div><div>Labour</div></div>
                <div class="item"><div class="val">Rp {{ number_format($totalParts, 0, ',', '.') }}</div><div>Parts</div></div>
                <div class="item"><div class="val">Rp {{ number_format($totalSales, 0, ',', '.') }}</div><div>Total</div></div>
            </div>
        </div>
        <div class="summary-card pc">
            <div class="value">{{ number_format($pcJobCount) }} Jobs</div>
            <div class="label">PC - Passenger Car</div>
        </div>
        <div class="summary-card cv">
            <div class="value">{{ number_format($cvJobCount) }} Jobs</div>
            <div class="label">CV - Commercial Vehicle</div>
        </div>
    </div>

    @if(array_filter($filters))
    <div class="filters-applied">
        <strong>Filters Applied:</strong>
        @if($filters['date_from'] ?? null) From: {{ $filters['date_from'] }} @endif
        @if($filters['date_to'] ?? null) To: {{ $filters['date_to'] }} @endif
        @if($filters['franchise'] ?? null) Franchise: {{ $filters['franchise'] }} @endif
        @if($filters['service_advisor'] ?? null) SA: {{ $filters['service_advisor'] }} @endif
        @if($filters['foreman'] ?? null) Foreman: {{ $filters['foreman'] }} @endif
        @if($filters['work_status'] ?? null) Status: {{ $filters['work_status'] }} @endif
        @if(isset($filters['need_part'])) Parts: {{ $filters['need_part'] == '1' ? 'Needs Parts' : 'No Parts' }} @endif
        @if($filters['search'] ?? null) Search: "{{ $filters['search'] }}" @endif
    </div>
    @endif

    <!-- Data Table -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                @foreach($columns as $key => $label)
                    <th class="{{ in_array($key, ['total_sales', 'labour_sales', 'part_sales']) ? 'text-end' : '' }}">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($jobs as $index => $job)
            <tr>
                <td>{{ $index + 1 }}</td>
                @foreach(array_keys($columns) as $col)
                    <td class="{{ in_array($col, ['total_sales', 'labour_sales', 'part_sales']) ? 'text-end' : '' }}">
                        @if($col === 'job_date')
                            {{ $job->job_date?->format('d/m/Y') }}
                        @elseif($col === 'latest_remark_at')
                            {{ $job->latest_remark_at?->format('d/m/Y') }}
                        @elseif(in_array($col, ['total_sales', 'labour_sales', 'part_sales']))
                            {{ $job->{$col} ? number_format($job->{$col}, 0, ',', '.') : '-' }}
                        @elseif($col === 'work_status')
                            <span class="badge">{{ $job->work_status ?? 'Pending' }}</span>
                        @elseif($col === 'need_part')
                            {{ $job->need_part ? 'Yes' : 'No' }}
                        @else
                            {{ $job->{$col} ?? '-' }}
                        @endif
                    </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <span>Uninvoiced Jobs Report</span> | 
        <span>Printed: {{ now()->format('d M Y H:i') }}</span> | 
        <span>Page <span class="page-number"></span></span>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" style="padding: 10px 30px; font-size: 14px; cursor: pointer; background: #dc3545; color: white; border: none; border-radius: 6px;">
            🖨️ Print / Save as PDF
        </button>
    </div>
</body>
</html>
