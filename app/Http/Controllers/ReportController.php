<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\SavedReport;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class ReportController extends Controller
{
    // ===== EXISTING REPORT METHODS =====
    
    public function uninvoiced(Request $request)
    {
        $query = Job::with('vehicle')
            ->uninvoiced();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('latest_remark', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('job_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('job_date', '<=', $request->date_to);
        }
        
        // New filters
        if ($request->filled('franchise')) {
            $query->where('franchise', $request->franchise);
        }
        if ($request->filled('service_advisor')) {
            $query->where('service_advisor', $request->service_advisor);
        }
        if ($request->filled('foreman')) {
            $query->where('foreman', $request->foreman);
        }
        if ($request->filled('work_status')) {
            $query->where('work_status', $request->work_status);
        }
        if ($request->filled('need_part')) {
            $query->where('need_part', $request->need_part == '1');
        }
        
        // Sorting
        $sortField = $request->input('sort', 'job_date');
        $sortDir = $request->input('dir', 'desc');
        $allowedSorts = ['job_number', 'plate_number', 'service_advisor', 'foreman', 'job_date', 'total_sales', 'labour_sales', 'part_sales', 'work_status', 'latest_remark_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest('job_date');
        }

        $jobs = $query->paginate(20);

        return view('reports.uninvoiced', compact('jobs'));
    }

    public function invoiced(Request $request)
    {
        $query = Job::with(['vehicle', 'invoices'])
            ->withCount('invoices')
            ->invoiced();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('invoice_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date_to);
        }

        // Additional filters
        if ($request->filled('franchise')) {
            $query->where('franchise', $request->franchise);
        }
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }
        if ($request->filled('type_sale')) {
            $query->where('type_sale', $request->type_sale);
        }
        if ($request->filled('service_advisor')) {
            $query->where('service_advisor', $request->service_advisor);
        }
        
        // Sorting
        $sortField = $request->input('sort', 'invoice_date');
        $sortDir = $request->input('dir', 'desc');
        $allowedSorts = ['job_number', 'plate_number', 'service_advisor', 'foreman', 'job_date', 'invoice_number', 'invoice_date', 'inv_ppn_meterai'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest('invoiced_at');
        }

        $jobs = $query->paginate(50)->withQueryString();

        // Filter options
        $filterOptions = [
            'franchise' => ['PC', 'CV'],
            'department' => Job::invoiced()->whereNotNull('department')->distinct()->pluck('department')->sort()->values()->toArray(),
            'type_sale' => Job::invoiced()->whereNotNull('type_sale')->distinct()->pluck('type_sale')->sort()->values()->toArray(),
            'service_advisor' => Job::invoiced()->whereNotNull('service_advisor')->distinct()->pluck('service_advisor')->sort()->values()->toArray(),
        ];

        return view('reports.invoiced', compact('jobs', 'filterOptions'));
    }

    public function exportInvoiced(Request $request)
    {
        set_time_limit(0); // Unlimited execution time
        ini_set('memory_limit', '-1'); // Unlimited memory
        $query = Job::with('invoices')->invoiced()->latest('invoice_date');

        // Apply same filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('invoice_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date_to);
        }
        if ($request->filled('franchise')) {
            $query->where('franchise', $request->franchise);
        }
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }
        if ($request->filled('type_sale')) {
            $query->where('type_sale', $request->type_sale);
        }
        if ($request->filled('service_advisor')) {
            $query->where('service_advisor', $request->service_advisor);
        }

        $jobs = $query->get();
        $format = $request->input('format', 'xlsx');
        $columns = $request->input('columns', ['job_number', 'plate_number', 'service_advisor', 'job_date', 'invoice_number', 'invoice_date', 'inv_ppn_meterai']);

        // Column definitions
        $allColumns = [
            'job_number' => 'WIP',
            'franchise' => 'Franchise',
            'department' => 'Dept',
            'plate_number' => 'Plate No',
            'customer_name' => 'Customer',
            'service_advisor' => 'SA',
            'foreman' => 'Foreman',
            'job_date' => 'Job Date',
            'date_in' => 'Date In',
            'date_out' => 'Date Out',
            'invoice_number' => 'Invoice #',
            'invoice_date' => 'Inv Date',
            'type_sale' => 'Type Sale',
            'inv_amount' => 'Amount',
            'inv_ppn' => 'PPN',
            'inv_ppn_meterai' => 'Total',
        ];

        $selectedColumns = array_intersect_key($allColumns, array_flip($columns));

        // Calculate summary stats for PDF
        $totalAll = $jobs->sum('inv_ppn_meterai');
        $totalPC = $jobs->where('franchise', 'PC')->sum('inv_ppn_meterai');
        $totalCV = $jobs->where('franchise', 'CV')->sum('inv_ppn_meterai');
        $deptTotals = $jobs->where('franchise', 'PC')->groupBy('department')->map(fn($g) => $g->sum('inv_ppn_meterai'))->sortDesc();
        $typeSaleTotalsPC = $jobs->where('franchise', 'PC')->groupBy('type_sale')->map(fn($g) => $g->sum('inv_ppn_meterai'))->sortDesc();
        $typeSaleTotalsCV = $jobs->where('franchise', 'CV')->groupBy('type_sale')->map(fn($g) => $g->sum('inv_ppn_meterai'))->sortDesc();

        if ($format === 'pdf') {
            $typeSaleLabels = ['INT' => 'Internal', 'WAR' => 'Warranty', 'CASH' => 'Cash', 'CREDIT' => 'Credit'];
            
            $html = '<html><head><style>
                body { font-family: Arial, sans-serif; font-size: 9px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
                th { background: #333; color: white; }
                .summary-card { display: inline-block; width: 30%; margin: 5px 1%; padding: 10px; text-align: center; border-radius: 8px; }
                .card-total { background: #d1fae5; border: 1px solid #10b981; }
                .card-pc { background: #dbeafe; border: 1px solid #3b82f6; }
                .card-cv { background: #fef3c7; border: 1px solid #f59e0b; }
                .card-value { font-size: 14px; font-weight: bold; margin: 5px 0; }
                .breakdown { margin: 15px 0; padding: 10px; background: #f9fafb; border-radius: 5px; }
                .breakdown-title { font-weight: bold; margin-bottom: 8px; }
                .breakdown-item { display: inline-block; padding: 5px 10px; margin: 3px; background: #e5e7eb; border-radius: 4px; }
                .inv-detail { font-size: 8px; color: #555; margin-left: 5px; }
                .inv-row { margin-bottom: 2px; }
                .text-danger { color: red; }
                h1 { font-size: 16px; margin-bottom: 5px; }
                .subtitle { color: #666; margin-bottom: 15px; }
            </style></head><body>';
            
            $html .= '<h1>Invoiced Jobs Report</h1>';
            $html .= '<div class="subtitle">Generated: ' . now()->format('d/m/Y H:i') . ' | Jobs: ' . $jobs->count() . '</div>';
            
            // Summary cards
            $html .= '<div style="margin-bottom: 15px;">';
            $html .= '<div class="summary-card card-total"><div>Total Invoiced</div><div class="card-value">Rp ' . number_format($totalAll, 0, ',', '.') . '</div><div>' . $jobs->count() . ' jobs</div></div>';
            $html .= '<div class="summary-card card-pc"><div>PC (Passenger Car)</div><div class="card-value">Rp ' . number_format($totalPC, 0, ',', '.') . '</div><div>' . $jobs->where('franchise', 'PC')->count() . ' jobs</div></div>';
            $html .= '<div class="summary-card card-cv"><div>CV (Commercial)</div><div class="card-value">Rp ' . number_format($totalCV, 0, ',', '.') . '</div><div>' . $jobs->where('franchise', 'CV')->count() . ' jobs</div></div>';
            $html .= '</div>';
            
            // Type Sale Breakdown
            if ($typeSaleTotalsPC->isNotEmpty()) {
                $html .= '<div class="breakdown"><div class="breakdown-title">PC Type Sale</div>';
                foreach ($typeSaleTotalsPC as $type => $total) {
                    $label = $typeSaleLabels[$type] ?? ($type ?: 'Unknown');
                    $html .= '<span class="breakdown-item">' . $label . ': Rp ' . number_format($total, 0, ',', '.') . '</span>';
                }
                $html .= '</div>';
            }
            
            // Department Breakdown
            if ($deptTotals->isNotEmpty()) {
                $html .= '<div class="breakdown"><div class="breakdown-title">PC Department</div>';
                foreach ($deptTotals as $dept => $total) {
                    $html .= '<span class="breakdown-item">' . ($dept ?: 'No Dept') . ': Rp ' . number_format($total, 0, ',', '.') . '</span>';
                }
                $html .= '</div>';
            }

            // Data table
            $html .= '<table><tr>';
            foreach ($selectedColumns as $label) {
                $html .= '<th>' . $label . '</th>';
            }
            $html .= '</tr>';
            
            foreach ($jobs as $job) {
                $html .= '<tr style="' . ($job->invoices->count() > 1 ? 'background-color: #f8f9fa;' : '') . '">';
                foreach ($selectedColumns as $key => $label) {
                    $value = $job->{$key};
                    if (in_array($key, ['job_date', 'date_in', 'date_out', 'invoice_date']) && $value) {
                        $value = $value->format('d/m/Y');
                    } elseif (in_array($key, ['inv_amount', 'inv_ppn', 'inv_ppn_meterai']) && $value) {
                        $value = number_format($value, 0, ',', '.');
                    }
                    $html .= '<td>' . e($value ?? '-') . '</td>';
                }
                $html .= '</tr>';
                
                // MULTI-INVOICE DETAIL (Simplified for performance)
                if ($job->invoices->count() > 1) {
                    $html .= '<tr><td colspan="' . count($selectedColumns) . '" style="padding: 2px 10px; background-color: #f8f9fa;">';
                    $html .= '<div style="font-weight:bold; font-size:8px; margin-bottom:2px;">Invoice History:</div>';
                    foreach ($job->invoices as $inv) {
                        $isCN = $inv->invoice_type === 'credit_note';
                        $class = $isCN ? 'text-danger' : '';
                        $type = $isCN ? 'CN' : 'INV';
                        $date = $inv->invoice_date?->format('d/m/Y');
                        $amount = number_format($inv->inv_ppn_meterai, 0, ',', '.');
                        
                        $html .= "<div class='inv-row $class'>- $type <b>{$inv->invoice_number}</b> ($date) | {$inv->type_sale} | Rp $amount</div>";
                    }
                    $html .= '</td></tr>';
                }
            }
            $html .= '</table></body></html>';

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="invoiced_jobs_' . date('Y-m-d') . '.pdf"',
            ]);
        }

        // Excel/CSV Export
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->fromArray(array_values($selectedColumns), null, 'A1');
        $sheet->getStyle('A1:' . chr(64 + count($selectedColumns)) . '1')->getFont()->setBold(true);

        $row = 2;
        foreach ($jobs as $job) {
            $rowData = [];
            foreach (array_keys($selectedColumns) as $key) {
                $value = $job->{$key};
                if (in_array($key, ['job_date', 'date_in', 'date_out', 'invoice_date']) && $value) {
                    $value = $value->format('Y-m-d');
                }
                $rowData[] = $value ?? '';
            }
            $sheet->fromArray($rowData, null, 'A' . $row);
            $mainRow = $row;
            $row++;
            
            // MULTI-INVOICE ROWS FOR EXCEL
            if ($job->invoices->count() > 1) {
                // Style the main row to indicate it has children
                $sheet->getStyle('A' . $mainRow . ':' . chr(64 + count($selectedColumns)) . $mainRow)
                      ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                      ->getStartColor()->setARGB('FFE2E8F0');
                
                // Add header row for details
                $sheet->setCellValue('B' . $row, 'Invoice #');
                $sheet->setCellValue('C' . $row, 'Date');
                $sheet->setCellValue('D' . $row, 'Type');
                $sheet->setCellValue('E' . $row, 'Sale Type');
                $sheet->setCellValue('F' . $row, 'Amount');
                $sheet->getStyle('B' . $row . ':F' . $row)->getFont()->setBold(true)->setSize(9);
                $row++;

                foreach ($job->invoices as $inv) {
                    $sheet->setCellValue('B' . $row, $inv->invoice_number);
                    $sheet->setCellValue('C' . $row, $inv->invoice_date?->format('Y-m-d'));
                    $sheet->setCellValue('D' . $row, $inv->invoice_type === 'credit_note' ? 'CN' : 'INV');
                    $sheet->setCellValue('E' . $row, $inv->type_sale ?? '-');
                    $sheet->setCellValue('F' . $row, $inv->inv_ppn_meterai);
                    
                    if ($inv->invoice_type === 'credit_note') {
                        $sheet->getStyle('B' . $row . ':F' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED));
                    } else {
                         $sheet->getStyle('B' . $row . ':F' . $row)->getFont()->setItalic(true)->setSize(9);
                    }
                    $row++;
                }
                $row++; // Add empty spacing row
            }
        }

        foreach (range('A', chr(64 + count($selectedColumns))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        if ($format === 'csv') {
            $filename = 'invoiced_jobs_' . date('Y-m-d_His') . '.csv';
            $writer = new Csv($spreadsheet);
            $contentType = 'text/csv';
        } else {
            $filename = 'invoiced_jobs_' . date('Y-m-d_His') . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $contentType]);
    }

    public function needsParts(Request $request)
    {
        $query = Job::with(['vehicle', 'remarks'])
            ->uninvoiced()
            ->needsParts()
            ->latest('job_date');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%");
            });
        }

        $jobs = $query->paginate(20);

        return view('reports.needs_parts', compact('jobs'));
    }

    /**
     * Aging Report - Jobs grouped by age with color-coded thresholds
     */
    public function aging(Request $request)
    {
        $query = Job::with('vehicle')->uninvoiced();
        
        // Apply filters
        if ($request->filled('service_advisor')) {
            $query->where('service_advisor', $request->service_advisor);
        }
        if ($request->filled('foreman')) {
            $query->where('foreman', $request->foreman);
        }
        if ($request->filled('franchise')) {
            $query->where('franchise', $request->franchise);
        }
        
        $allJobs = $query->get();
        
        // Group by age brackets
        $now = now();
        $agingGroups = [
            '0-7' => ['label' => '0-7 Days', 'color' => 'success', 'icon' => 'check-circle', 'jobs' => collect()],
            '8-14' => ['label' => '8-14 Days', 'color' => 'warning', 'icon' => 'exclamation-triangle', 'jobs' => collect()],
            '15-30' => ['label' => '15-30 Days', 'color' => 'orange', 'icon' => 'clock-history', 'jobs' => collect()],
            '30+' => ['label' => '30+ Days', 'color' => 'danger', 'icon' => 'exclamation-octagon', 'jobs' => collect()],
        ];
        
        foreach ($allJobs as $job) {
            $daysOld = $job->job_date ? $now->diffInDays($job->job_date) : 999;
            
            if ($daysOld <= 7) {
                $agingGroups['0-7']['jobs']->push($job);
            } elseif ($daysOld <= 14) {
                $agingGroups['8-14']['jobs']->push($job);
            } elseif ($daysOld <= 30) {
                $agingGroups['15-30']['jobs']->push($job);
            } else {
                $agingGroups['30+']['jobs']->push($job);
            }
        }
        
        // Statistics
        $totalJobs = $allJobs->count();
        $totalSales = $allJobs->sum('total_sales');
        $avgAge = $allJobs->avg(fn($j) => $j->job_date ? $now->diffInDays($j->job_date) : 0);
        
        // Filter options
        $filterOptions = [
            'service_advisor' => Job::uninvoiced()->whereNotNull('service_advisor')->distinct()->pluck('service_advisor')->sort()->values()->toArray(),
            'foreman' => Job::uninvoiced()->whereNotNull('foreman')->distinct()->pluck('foreman')->sort()->values()->toArray(),
            'franchise' => ['PC', 'CV'],
        ];
        
        return view('reports.aging', compact('agingGroups', 'totalJobs', 'totalSales', 'avgAge', 'filterOptions'));
    }

    /**
     * SA Performance Dashboard - Metrics per Service Advisor
     */
    public function saPerformance(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        
        // Get all jobs in date range
        $query = Job::whereBetween('job_date', [$dateFrom, $dateTo]);
        
        if ($request->filled('franchise')) {
            $query->where('franchise', $request->franchise);
        }
        
        $jobs = $query->get();
        
        // Group by Service Advisor
        $saStats = $jobs->groupBy('service_advisor')->map(function ($saJobs, $saName) {
            $invoiced = $saJobs->where('status', 'invoiced');
            $uninvoiced = $saJobs->where('status', '!=', 'invoiced');
            
            // Calculate turnaround (avg days from job_date to invoiced_at)
            $avgTurnaround = $invoiced->filter(fn($j) => $j->job_date && $j->invoiced_at)
                ->avg(fn($j) => $j->invoiced_at->diffInDays($j->job_date));
            
            return [
                'name' => $saName ?: 'Unassigned',
                'total_jobs' => $saJobs->count(),
                'invoiced_count' => $invoiced->count(),
                'uninvoiced_count' => $uninvoiced->count(),
                'total_sales' => $invoiced->sum('inv_ppn_meterai') ?: $invoiced->sum('total_sales'),
                'uninvoiced_sales' => $uninvoiced->sum('total_sales'),
                'avg_turnaround' => round($avgTurnaround ?? 0, 1),
                'completion_rate' => $saJobs->count() > 0 ? round(($invoiced->count() / $saJobs->count()) * 100, 1) : 0,
            ];
        })->sortByDesc('total_sales')->values();
        
        // Overall stats
        $overallStats = [
            'total_jobs' => $jobs->count(),
            'total_invoiced' => $jobs->where('status', 'invoiced')->count(),
            'total_sales' => $jobs->where('status', 'invoiced')->sum('inv_ppn_meterai') ?: $jobs->where('status', 'invoiced')->sum('total_sales'),
            'avg_turnaround' => round($saStats->avg('avg_turnaround'), 1),
        ];
        
        // For chart data
        $chartData = [
            'labels' => $saStats->take(10)->pluck('name')->toArray(),
            'sales' => $saStats->take(10)->pluck('total_sales')->toArray(),
            'jobs' => $saStats->take(10)->pluck('total_jobs')->toArray(),
        ];
        
        return view('reports.sa-performance', compact('saStats', 'overallStats', 'chartData', 'dateFrom', 'dateTo'));
    }

    public function customerMerges(Request $request)
    {
        $query = \App\Models\CustomerMergeLog::orderBy('created_at', 'desc');

        // Filter by source type
        if ($request->filled('source_type')) {
            $query->where('source_type', $request->source_type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('old_name', 'like', "%{$search}%")
                  ->orWhere('canonical_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        // Stats
        $stats = [
            'total' => \App\Models\CustomerMergeLog::count(),
            'dms_issues' => \App\Models\CustomerMergeLog::where('source_type', 'dms_import')->count(),
            'user_mistakes' => \App\Models\CustomerMergeLog::whereIn('source_type', ['job_progress_import', 'user_entry'])->count(),
            'jobs_fixed' => \App\Models\CustomerMergeLog::sum('jobs_updated'),
            'vehicles_fixed' => \App\Models\CustomerMergeLog::sum('vehicles_updated'),
        ];

        return view('reports.customer_merges', compact('logs', 'stats'));
    }

    public function exportCustomerMerges(Request $request)
    {
        $query = \App\Models\CustomerMergeLog::orderBy('created_at', 'desc');

        // Apply same filters as main view
        if ($request->filled('source_type')) {
            $query->where('source_type', $request->source_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('old_name', 'like', "%{$search}%")
                  ->orWhere('canonical_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->get();
        $format = $request->input('format', 'xlsx');

        // Source type labels
        $sourceLabels = [
            'dms_import' => 'DMS Import',
            'job_progress_import' => 'Job Progress',
            'user_entry' => 'Manual Entry',
        ];

        if ($format === 'pdf') {
            // PDF Export using simple HTML table
            $html = '<html><head><style>
                body { font-family: Arial, sans-serif; font-size: 10px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background: #333; color: white; }
                .dms { background: #fee2e2; }
                .user { background: #fef3c7; }
                h1 { font-size: 16px; }
            </style></head><body>';
            $html .= '<h1>Customer Merge Report - ' . now()->format('d/m/Y H:i') . '</h1>';
            $html .= '<table><tr><th>Date</th><th>Old Name</th><th>Merged To</th><th>Source</th><th>Jobs</th><th>Vehicles</th><th>By</th></tr>';
            
            foreach ($logs as $log) {
                $rowClass = $log->source_type === 'dms_import' ? 'dms' : 'user';
                $html .= '<tr class="' . $rowClass . '">';
                $html .= '<td>' . $log->created_at->format('d/m/Y H:i') . '</td>';
                $html .= '<td>' . e($log->old_name) . '</td>';
                $html .= '<td>' . e($log->canonical_name) . '</td>';
                $html .= '<td>' . ($sourceLabels[$log->source_type] ?? $log->source_type) . '</td>';
                $html .= '<td>' . $log->jobs_updated . '</td>';
                $html .= '<td>' . $log->vehicles_updated . '</td>';
                $html .= '<td>' . e($log->merged_by) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table></body></html>';

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="customer_merges_' . date('Y-m-d') . '.pdf"',
            ]);
        }

        // Excel/CSV Export
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Date', 'Old Name', 'Merged To', 'Source', 'Jobs Updated', 'Vehicles Updated', 'Merged By', 'Notes'];
        $sheet->fromArray($headers, null, 'A1');

        // Style headers
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $row = 2;
        foreach ($logs as $log) {
            $sheet->fromArray([
                $log->created_at->format('d/m/Y H:i'),
                $log->old_name,
                $log->canonical_name,
                $sourceLabels[$log->source_type] ?? $log->source_type,
                $log->jobs_updated,
                $log->vehicles_updated,
                $log->merged_by,
                $log->notes,
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        if ($format === 'csv') {
            $filename = 'customer_merges_' . date('Y-m-d_His') . '.csv';
            $writer = new Csv($spreadsheet);
            $contentType = 'text/csv';
        } else {
            $filename = 'customer_merges_' . date('Y-m-d_His') . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $contentType]);
    }

    public function exportUninvoiced(Request $request)
    {
        $format = $request->input('format', 'xlsx');
        $selectedColumns = $request->input('columns', ['job_number', 'plate_number', 'service_advisor', 'job_date', 'total_sales', 'work_status', 'latest_remark']);
        
        // Build query with filters
        $query = Job::with('vehicle')
            ->uninvoiced()
            ->latest('job_date');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('latest_remark', 'like', "%{$search}%");
            });
        }
        if ($request->filled('date_from')) $query->whereDate('job_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('job_date', '<=', $request->date_to);
        if ($request->filled('franchise')) $query->where('franchise', $request->franchise);
        if ($request->filled('service_advisor')) $query->where('service_advisor', $request->service_advisor);
        if ($request->filled('foreman')) $query->where('foreman', $request->foreman);
        if ($request->filled('work_status')) $query->where('work_status', $request->work_status);
        if ($request->filled('need_part')) $query->where('need_part', $request->need_part == '1');
        
        $jobs = $query->get();
        
        // Column definitions
        $allColumns = [
            'job_number' => 'WIP',
            'franchise' => 'Franchise',
            'plate_number' => 'Plate No',
            'customer_name' => 'Customer',
            'service_advisor' => 'SA',
            'foreman' => 'Foreman',
            'job_date' => 'Job Date',
            'total_sales' => 'Total Sales',
            'labour_sales' => 'Labour',
            'part_sales' => 'Parts',
            'work_status' => 'Work Status',
            'need_part' => 'Need Part',
            'latest_remark' => 'Last Remark',
            'latest_remark_at' => 'Remark Date',
        ];
        
        // Filter to selected columns
        $columns = array_intersect_key($allColumns, array_flip($selectedColumns));
        
        // Summary stats
        $totalJobCount = $jobs->count();
        $pcJobCount = $jobs->where('franchise', 'PC')->count();
        $cvJobCount = $jobs->where('franchise', 'CV')->count();
        $totalSales = $jobs->sum('total_sales');
        $totalLabour = $jobs->sum('labour_sales');
        $totalParts = $jobs->sum('part_sales');
        
        // Handle PDF format
        if ($format === 'pdf') {
            return view('reports.uninvoiced-pdf', [
                'jobs' => $jobs,
                'columns' => $columns,
                'totalJobCount' => $totalJobCount,
                'pcJobCount' => $pcJobCount,
                'cvJobCount' => $cvJobCount,
                'totalSales' => $totalSales,
                'totalLabour' => $totalLabour,
                'totalParts' => $totalParts,
                'filters' => $request->only(['search', 'date_from', 'date_to', 'franchise', 'service_advisor', 'foreman', 'work_status', 'need_part']),
            ]);
        }
        
        // Excel/CSV export
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add headers
        $headers = array_values($columns);
        $sheet->fromArray($headers, null, 'A1');
        
        // Style header
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
        ];
        $sheet->getStyle('A1:' . chr(64 + count($columns)) . '1')->applyFromArray($headerStyle);

        // Add data
        $row = 2;
        foreach ($jobs as $job) {
            $rowData = [];
            foreach (array_keys($columns) as $col) {
                $value = $job->{$col};
                if ($col === 'job_date' && $value) $value = $value->format('d/m/Y');
                if ($col === 'latest_remark_at' && $value) $value = $value->format('d/m/Y');
                if (in_array($col, ['total_sales', 'labour_sales', 'part_sales']) && $value) $value = (float)$value;
                if ($col === 'need_part') $value = $value ? 'Yes' : 'No';
                $rowData[] = $value ?? '';
            }
            $sheet->fromArray($rowData, null, 'A' . $row);
            $row++;
        }

        // Auto-size columns
        foreach (range('A', chr(64 + count($columns))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'uninvoiced_jobs_' . date('Y-m-d_His');
        
        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $filename .= '.csv';
            $contentType = 'text/csv';
        } else {
            $writer = new Xlsx($spreadsheet);
            $filename .= '.xlsx';
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $contentType]);
    }

    public function exportNeedsParts(Request $request)
    {
        $jobs = Job::with(['vehicle', 'remarks'])
            ->uninvoiced()
            ->needsParts()
            ->latest('job_date')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['No', 'Job Number', 'Plate Number', 'Service Advisor', 'Job Date', 'Latest Remark', 'Last Updated'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($jobs as $index => $job) {
            $sheet->fromArray([
                $index + 1,
                $job->job_number,
                $job->plate_number,
                $job->service_advisor,
                $job->job_date?->format('d/m/Y'),
                $job->latest_remark,
                $job->latest_remark_at?->format('d/m/Y H:i'),
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'needs_parts_jobs_' . date('Y-m-d_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ===== CUSTOM REPORT BUILDER =====

    /**
     * Available columns for job reports
     */
    private function getJobColumns(): array
    {
        return [
            'job_number' => ['label' => 'WIP', 'group' => 'Core'],
            'job_card' => ['label' => 'Job Card', 'group' => 'Core'],
            'franchise' => ['label' => 'Franchise', 'group' => 'Core'],
            'department' => ['label' => 'Department', 'group' => 'Core'],
            'job_date' => ['label' => 'Job Date', 'group' => 'Dates', 'type' => 'date'],
            'date_in' => ['label' => 'Date In', 'group' => 'Dates', 'type' => 'date'],
            'date_out' => ['label' => 'Date Out', 'group' => 'Dates', 'type' => 'date'],
            'check_in_time' => ['label' => 'Check-In Time', 'group' => 'Dates'],
            'promise_date' => ['label' => 'Promise Date', 'group' => 'Dates', 'type' => 'date'],
            'deadline' => ['label' => 'Deadline', 'group' => 'Dates', 'type' => 'date'],
            'plate_number' => ['label' => 'Reg No', 'group' => 'Vehicle'],
            'chassis_number' => ['label' => 'Chassis', 'group' => 'Vehicle'],
            'unit_type' => ['label' => 'Unit Type', 'group' => 'Vehicle'],
            'customer_name' => ['label' => 'Customer Name', 'group' => 'Customer'],
            'customer_address' => ['label' => 'Customer Address', 'group' => 'Customer'],
            'service_advisor' => ['label' => 'Service Advisor', 'group' => 'Personnel'],
            'foreman' => ['label' => 'Foreman', 'group' => 'Personnel'],
            'technician' => ['label' => 'Technician', 'group' => 'Personnel'],
            'block' => ['label' => 'Block', 'group' => 'Personnel'],
            'job_type' => ['label' => 'Job Type', 'group' => 'Job Info'],
            'payment_type' => ['label' => 'Payment Type', 'group' => 'Job Info'],
            'work_status' => ['label' => 'Work Status', 'group' => 'Job Info'],
            'labour_sales' => ['label' => 'Labour Sales', 'group' => 'Sales', 'type' => 'number'],
            'part_sales' => ['label' => 'Part Sales', 'group' => 'Sales', 'type' => 'number'],
            'total_sales' => ['label' => 'Total Sales', 'group' => 'Sales', 'type' => 'number'],
            'estimated_amount' => ['label' => 'Estimated', 'group' => 'Sales', 'type' => 'number'],
            'rq' => ['label' => 'RQ', 'group' => 'Parts'],
            'no_order_part_mbina' => ['label' => 'Order Part', 'group' => 'Parts'],
            'need_part' => ['label' => 'Needs Parts', 'group' => 'Parts', 'type' => 'boolean'],
            'status' => ['label' => 'Status', 'group' => 'Invoice'],
            'invoice_number' => ['label' => 'Invoice No', 'group' => 'Invoice'],
            'invoice_date' => ['label' => 'Inv Date', 'group' => 'Invoice', 'type' => 'date'],
            'inv_amount' => ['label' => 'Inv Amount', 'group' => 'Invoice', 'type' => 'number'],
            'latest_remark' => ['label' => 'Latest Remark', 'group' => 'Remarks'],
            'latest_remark_at' => ['label' => 'Remark Updated', 'group' => 'Remarks', 'type' => 'datetime'],
        ];
    }

    private function getFilterOptions(): array
    {
        return [
            'franchise' => ['PC', 'CV'],
            'status' => ['uninvoiced', 'invoiced'],
            'service_advisor' => Job::whereNotNull('service_advisor')->distinct()->pluck('service_advisor')->sort()->values()->toArray(),
            'foreman' => Job::whereNotNull('foreman')->distinct()->pluck('foreman')->sort()->values()->toArray(),
            'department' => Job::whereNotNull('department')->distinct()->pluck('department')->sort()->values()->toArray(),
            'work_status' => Job::whereNotNull('work_status')->distinct()->pluck('work_status')->sort()->values()->toArray(),
        ];
    }

    public function builder()
    {
        $columns = $this->getJobColumns();
        $filterOptions = $this->getFilterOptions();
        $savedReports = SavedReport::where('user_id', auth()->id())->orderBy('name')->get();
        
        $groupedColumns = [];
        foreach ($columns as $key => $col) {
            $group = $col['group'] ?? 'Other';
            $groupedColumns[$group][$key] = $col;
        }
        
        return view('reports.builder', compact('groupedColumns', 'filterOptions', 'savedReports'));
    }

    public function preview(Request $request)
    {
        $data = $this->buildQuery($request);
        $columns = $request->input('columns', []);
        $allColumns = $this->getJobColumns();
        $selectedColumns = array_intersect_key($allColumns, array_flip($columns));
        
        return response()->json([
            'success' => true,
            'columns' => $selectedColumns,
            'data' => $data->take(50)->get()->map(function ($job) use ($selectedColumns) {
                $row = [];
                foreach ($selectedColumns as $key => $col) {
                    $value = $job->{$key};
                    if (isset($col['type'])) {
                        if ($col['type'] === 'date' && $value) $value = $value->format('d/m/Y');
                        elseif ($col['type'] === 'datetime' && $value) $value = $value->format('d/m/Y H:i');
                        elseif ($col['type'] === 'number' && $value) $value = number_format($value, 0, ',', '.');
                        elseif ($col['type'] === 'boolean') $value = $value ? 'Yes' : 'No';
                    }
                    $row[$key] = $value ?? '';
                }
                return $row;
            }),
            'total' => $data->count(),
        ]);
    }

    public function export(Request $request)
    {
        $format = $request->input('format', 'xlsx');
        $columns = $request->input('columns', []);
        $allColumns = $this->getJobColumns();
        $selectedColumns = array_intersect_key($allColumns, array_flip($columns));
        $data = $this->buildQuery($request)->get();
        
        // Handle PDF/Print format
        if ($format === 'pdf' || $format === 'print') {
            $title = $request->input('title', 'Job Report');
            $titleAlign = $request->input('title_align', 'center');
            $orientation = $request->input('orientation', 'portrait');
            $header = $request->input('header', '');
            $footer = $request->input('footer', 'Page {page} of {pages}');
            
            // Process variables in header/footer
            $variables = [
                '{title}' => $title,
                '{date}' => now()->format('d/m/Y'),
                '{time}' => now()->format('H:i'),
                '{page}' => '', // Will be replaced by CSS/browser
                '{pages}' => '', // Will be replaced by CSS/browser
            ];
            
            $processedHeader = str_replace(array_keys($variables), array_values($variables), $header);
            $processedFooter = str_replace(array_keys($variables), array_values($variables), $footer);
            
            // Build applied filters description
            $appliedFilters = collect([
                $request->franchise ? "Franchise: {$request->franchise}" : null,
                $request->status ? "Status: {$request->status}" : null,
                $request->service_advisor ? "SA: {$request->service_advisor}" : null,
                $request->date_from ? "From: {$request->date_from}" : null,
                $request->date_to ? "To: {$request->date_to}" : null,
            ])->filter()->implode(' | ');
            
            return view('reports.print', [
                'columns' => $selectedColumns,
                'data' => $data,
                'title' => $title,
                'titleAlign' => $titleAlign,
                'orientation' => $orientation,
                'header' => $header,
                'footer' => $footer,
                'processedHeader' => $processedHeader,
                'processedFooter' => $processedFooter,
                'appliedFilters' => $appliedFilters,
            ]);
        }
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Header
        $col = 1;
        foreach ($selectedColumns as $colDef) {
            $sheet->setCellValueByColumnAndRow($col++, 1, $colDef['label']);
        }
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
        
        // Data
        $row = 2;
        foreach ($data as $job) {
            $col = 1;
            foreach ($selectedColumns as $key => $colDef) {
                $value = $job->{$key};
                if (isset($colDef['type'])) {
                    if ($colDef['type'] === 'date' && $value) $value = $value->format('Y-m-d');
                    elseif ($colDef['type'] === 'datetime' && $value) $value = $value->format('Y-m-d H:i');
                    elseif ($colDef['type'] === 'boolean') $value = $value ? 'Yes' : 'No';
                }
                $sheet->setCellValueByColumnAndRow($col++, $row, $value ?? '');
            }
            $row++;
        }
        
        foreach (range('A', $sheet->getHighestColumn()) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        
        $filename = 'job_report_' . now()->format('Ymd_His');
        
        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $filename .= '.csv';
            $contentType = 'text/csv';
        } else {
            $writer = new Xlsx($spreadsheet);
            $filename .= '.xlsx';
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => $contentType]);
    }

    public function saveReport(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'columns' => 'required|array|min:1',
            'filters' => 'nullable|array',
        ]);
        
        $report = SavedReport::create([
            'name' => $validated['name'],
            'user_id' => auth()->id(),
            'data_source' => 'jobs',
            'columns' => $validated['columns'],
            'filters' => $validated['filters'] ?? [],
        ]);
        
        return response()->json(['success' => true, 'report' => $report]);
    }

    public function loadReport(SavedReport $report)
    {
        if ($report->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403);
        }
        return response()->json(['success' => true, 'report' => $report]);
    }

    public function deleteReport(SavedReport $report)
    {
        if ($report->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403);
        }
        $report->delete();
        return response()->json(['success' => true]);
    }

    private function buildQuery(Request $request)
    {
        $query = Job::query();
        
        if ($request->filled('date_from')) $query->whereDate('job_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('job_date', '<=', $request->date_to);
        
        foreach (['franchise', 'status', 'service_advisor', 'foreman', 'department', 'work_status'] as $field) {
            if ($request->filled($field)) $query->where($field, $request->input($field));
        }
        
        if ($request->filled('need_part')) {
            $query->where('need_part', $request->need_part === '1');
        }
        
        return $query->orderBy('job_date', 'desc');
    }
}

