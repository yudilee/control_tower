<?php

namespace App\Http\Controllers;

use App\Models\CustomerAlias;
use App\Models\Import;
use App\Models\Job;
use App\Models\JobActivity;
use App\Models\JobInvoice;
use App\Models\Vehicle;
use App\Models\Foreman;
use App\Models\ServiceAdvisor;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\BackupService;

class ImportController extends Controller
{
    protected $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    public function index()
    {
        $imports = Import::with('user')->latest()->paginate(20);
        return view('imports.index', compact('imports'));
    }

    public function show(Import $import)
    {
        // Get dummy WIP jobs created during this import
        $dummyJobs = Job::where('import_id', $import->id)
            ->where('is_dummy_wip', true)
            ->get();
            
        return view('imports.show', compact('import', 'dummyJobs'));
    }

    public function showUploadForm()
    {
        return view('imports.upload');
    }

    /**
     * Preview import data before actual processing.
     * Shows first 100 rows with validation status.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,ods,csv',
            'import_type' => 'required|in:progress,uninvoiced,invoiced',
            'franchise' => 'nullable|in:PC,CV',
        ]);

        try {
            $file = $request->file('file');
            $importType = $request->input('import_type');
            $franchise = $request->input('franchise');

            $extension = strtolower($file->getClientOriginalExtension());
            $reader = IOFactory::createReaderForFile($file->getPathname());
            
            if ($extension === 'ods') {
                $reader->setReadDataOnly(true);
            }
            $reader->setReadEmptyCells(false);
            
            $spreadsheet = $reader->load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (count($rows) < 2) {
                return back()->with('error', 'File is empty or has no data rows.');
            }

            $header = array_shift($rows);
            $headerMap = array_flip(array_map('strtolower', array_map('trim', $header)));
            
            $previewRows = [];
            $validCount = 0;
            $errorCount = 0;
            $warningCount = 0;
            $maxPreviewRows = 100;

            foreach (array_slice($rows, 0, $maxPreviewRows) as $index => $row) {
                $rowNum = $index + 2; // +2 because of header and 0-indexing
                $errors = [];
                $warnings = [];
                
                $jobNumber = $this->getColumnValue($row, $headerMap, ['wip', 'no job', 'job_number', 'job number']);
                $plateNumber = $this->getColumnValue($row, $headerMap, ['reg. no.', 'reg no', 'no polisi', 'plate_number', 'plate number']);
                $customerName = $this->getColumnValue($row, $headerMap, ['customer name', 'customer', 'nama customer']);
                
                // Validation
                if (empty($jobNumber)) {
                    $errors[] = 'Missing WIP/Job Number';
                }
                if (empty($plateNumber) || strlen(trim($plateNumber)) < 3) {
                    $errors[] = 'Missing or invalid Plate Number';
                }
                
                // Check for existing job (warning)
                if ($jobNumber) {
                    $existing = Job::where('job_number', $jobNumber)->first();
                    if ($existing) {
                        if ($importType === 'progress') {
                            $warnings[] = 'Job exists, will be updated';
                        }
                    }
                }
                
                $status = 'valid';
                if (!empty($errors)) {
                    $status = 'error';
                    $errorCount++;
                } elseif (!empty($warnings)) {
                    $status = 'warning';
                    $warningCount++;
                } else {
                    $validCount++;
                }

                $previewRows[] = [
                    'row' => $rowNum,
                    'job_number' => $jobNumber ?: '-',
                    'plate_number' => $plateNumber ?: '-',
                    'customer_name' => $customerName ? \Str::limit($customerName, 30) : '-',
                    'status' => $status,
                    'errors' => $errors,
                    'warnings' => $warnings,
                ];
            }

            // Store file temporarily for confirmation
            $tempPath = $file->store('temp_imports');
            session(['import_preview' => [
                'temp_path' => $tempPath,
                'import_type' => $importType,
                'franchise' => $franchise,
                'file_name' => $file->getClientOriginalName(),
                'total_rows' => count($rows),
            ]]);

            return view('imports.preview', [
                'previewRows' => $previewRows,
                'totalRows' => count($rows),
                'validCount' => $validCount,
                'errorCount' => $errorCount,
                'warningCount' => $warningCount,
                'fileName' => $file->getClientOriginalName(),
                'importType' => $importType,
                'franchise' => $franchise,
                'headers' => array_keys($headerMap),
            ]);

        } catch (\Exception $e) {
            \Log::error('Import preview failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to read file: ' . $e->getMessage());
        }
    }

    /**
     * Confirm and execute the previewed import.
     */
    public function confirmImport(Request $request)
    {
        $previewData = session('import_preview');
        
        if (!$previewData) {
            return redirect()->route('imports.upload')
                ->with('error', 'Preview session expired. Please upload the file again.');
        }

        $tempPath = storage_path('app/' . $previewData['temp_path']);
        
        if (!file_exists($tempPath)) {
            session()->forget('import_preview');
            return redirect()->route('imports.upload')
                ->with('error', 'Temporary file not found. Please upload again.');
        }

        // Create a fake UploadedFile to pass to existing import methods
        $file = new \Illuminate\Http\UploadedFile(
            $tempPath,
            $previewData['file_name'],
            null,
            null,
            true
        );
        
        // Build a fake request
        $fakeRequest = new Request();
        $fakeRequest->files->set('file', $file);
        $fakeRequest->merge(['franchise' => $previewData['franchise']]);

        // Call the appropriate import method
        $importType = $previewData['import_type'];
        session()->forget('import_preview');
        
        // Clean up temp file after import
        try {
            if ($importType === 'progress') {
                return $this->importProgress($fakeRequest);
            } elseif ($importType === 'uninvoiced') {
                return $this->importUninvoiced($fakeRequest);
            } elseif ($importType === 'invoiced') {
                return $this->importInvoiced($fakeRequest);
            }
        } finally {
            @unlink($tempPath);
        }

        return redirect()->route('imports.upload')
            ->with('error', 'Unknown import type.');
    }

    public function importProgress(Request $request)
    {
        // Increase limits for large files
        set_time_limit(0); // No time limit
        ini_set('memory_limit', '2G');
        
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,ods,csv',
        ]);

        $file = $request->file('file');
        
        // Auto-backup before processing
        try {
            $this->backupService->create('Auto-backup before Import Progress: ' . $file->getClientOriginalName());
        } catch (\Exception $e) {
            \Log::error('Auto-backup failed: ' . $e->getMessage());
            // Continue import even if backup fails? User request implies "always make backup".
            // But blocking import might be too harsh if HDD is full. Log error and notify?
            // For now, we continue but with Log warning.
        }

        $extension = strtolower($file->getClientOriginalExtension());
        
        // Use a more memory-efficient reader
        $reader = IOFactory::createReaderForFile($file->getPathname());
        
        // For XLSX files, try to calculate formulas
        // For ODS files, skip formulas as LibreOffice structured references often fail
        if ($extension === 'ods') {
            $reader->setReadDataOnly(true); // Skip formatting, formulas for ODS
        } else {
            $reader->setReadDataOnly(false); // Allow formula calculation for XLSX
        }
        $reader->setReadEmptyCells(false); // Skip empty cells
        
        $spreadsheet = $reader->load($file->getPathname());
        
        // For XLSX, try to calculate formulas; for ODS, disable calculation cache
        if ($extension === 'ods') {
            \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($spreadsheet)->disableCalculationCache();
        }
        
        $sheetCount = $spreadsheet->getSheetCount();
        
        // Create import record first with pending status
        $import = Import::create([
            'file_name' => $file->getClientOriginalName(),
            'import_type' => 'progress',
            'records_imported' => 0,
            'records_updated' => 0,
            'records_failed' => 0,
            'imported_by' => auth()->id(),
        ]);
        $importId = $import->id;
        
        $imported = 0;
        $updated = 0;
        $failed = 0;
        $failedRows = [];
        $maxFailedRows = 100; // Limit stored failed rows to prevent huge JSON

        for ($sheetIndex = 0; $sheetIndex < $sheetCount; $sheetIndex++) {
            $worksheet = $spreadsheet->getSheet($sheetIndex);
            $sheetName = strtoupper(trim($worksheet->getTitle()));
            
            // Skip only Sheet5 (unused) to save memory
            if ($sheetName === 'SHEET5') {
                continue;
            }
            
            // Only load rows if we're going to process this sheet
            // Try to get calculated values first, fallback to raw values if formula error occurs
            try {
                $rows = $worksheet->toArray();
            } catch (\Exception $e) {
                // Formula calculation failed (e.g., structured references), try without calculation
                try {
                    $highestRow = $worksheet->getHighestRow();
                    $highestColumn = $worksheet->getHighestColumn();
                    $rows = $worksheet->rangeToArray('A1:' . $highestColumn . $highestRow, null, false, false);
                } catch (\Exception $e2) {
                    // Skip this sheet entirely if still failing
                    continue;
                }
            }

            if (empty($rows)) {
                continue;
            }

            // Route based on sheet name
            \Log::info("Processing sheet: {$sheetName}, rows: " . count($rows));
            
            if (str_contains($sheetName, 'BOOKING')) {
                \Log::info("Matched BOOKING sheet");
                $result = $this->importBookingSheet($rows);
                \Log::info("Booking import result: ", $result);
                $imported += $result['imported'];
                $updated += $result['updated'];
                $failed += $result['failed'];
                if (!empty($result['failedRows'])) {
                    $failedRows = array_merge($failedRows, array_slice($result['failedRows'], 0, $maxFailedRows - count($failedRows)));
                }
                continue;
            }

            if (str_contains($sheetName, 'PRE DELIVERY') || str_contains($sheetName, 'PDI')) {
                \Log::info("Matched PDI sheet");
                $result = $this->importPdiSheet($rows);
                \Log::info("PDI import result: ", $result);
                $imported += $result['imported'];
                $updated += $result['updated'];
                $failed += $result['failed'];
                if (!empty($result['failedRows'])) {
                    $failedRows = array_merge($failedRows, array_slice($result['failedRows'], 0, $maxFailedRows - count($failedRows)));
                }
                continue;
            }

            if (str_contains($sheetName, 'TOWING') || str_contains($sheetName, 'STOORING')) {
                $result = $this->importTowingSheet($rows);
                $imported += $result['imported'];
                $updated += $result['updated'];
                $failed += $result['failed'];
                if (!empty($result['failedRows'])) {
                    $failedRows = array_merge($failedRows, array_slice($result['failedRows'], 0, $maxFailedRows - count($failedRows)));
                }
                continue;
            }

            // Default: Job Progress sheet
            // Find Header Row
            $headerMap = [];
            $dataStartIndex = 0;
            $foundHeader = false;

            // Scan first 10 rows for header
            for ($i = 0; $i < min(10, count($rows)); $i++) {
                $row = $rows[$i];
                // Check if this row looks like a header (contains specific keywords)
                $rowString = strtolower(implode(' ', array_map('trim', $row)));
                
                // We look for 'wip' AND 'reg' to be sure
                if (str_contains($rowString, 'wip') && (str_contains($rowString, 'reg') || str_contains($rowString, 'plate') || str_contains($rowString, 'polisi'))) {
                    $header = $row;
                    $headerMap = array_flip(array_map('strtolower', array_map('trim', $header)));
                    $dataStartIndex = $i + 1;
                    $foundHeader = true;
                    break;
                }
            }

            if (!$foundHeader) {
                \Log::warning("PROGRESS sheet {$sheetName}: No header row found");
                continue;
            }
            
            \Log::info("PROGRESS sheet {$sheetName}: Found header at row " . ($dataStartIndex - 1) . ", columns: " . implode(', ', array_keys($headerMap)));
            
            // Iterate Data
            // Use for loop with index to easily manage start
            for ($i = $dataStartIndex; $i < count($rows); $i++) {
                $row = $rows[$i];
                try {
                $jobNumber = $this->getColumnValue($row, $headerMap, ['wip', 'no job', 'job_number', 'job number', 'nomer job']);
                
                // Skip empty job numbers or "Summary" rows (where WIP might be '0' or 'TOTAL' or 'GRAND')
                if (empty($jobNumber) || $jobNumber === '0' || str_contains(strtoupper($jobNumber), 'TOTAL') || str_contains(strtoupper($jobNumber), 'GRAND')) {
                    continue;
                }
                
                // Skip invalid job numbers (single digits 1-9, or just serial numbers)
                // Valid WIP should be at least 4 characters for real job numbers
                if (strlen(trim($jobNumber)) < 4 && is_numeric($jobNumber)) {
                    continue;
                }

                $plateNumber = $this->getColumnValue($row, $headerMap, ['reg no', 'reg. no', 'no polisi', 'plate_number', 'plate number', 'nopol']);
                
                // Skip rows without a valid plate number (critical field)
                if (empty($plateNumber) || strlen(trim($plateNumber)) < 3) {
                    continue;
                }
                
                
                $saName = $this->getColumnValue($row, $headerMap, ['sa', 'service_advisor', 'service advisor']);
                $foremanName = $this->getColumnValue($row, $headerMap, ['foreman', 'kepala regu', 'mandor']); 
                
                // Auto-detect Franchise based on columns specific to PC Report
                $isPCFormat = isset($headerMap['address 01']) || isset($headerMap['d']);
                $defaultFranchise = $isPCFormat ? 'PC' : null;

                // Attempt to determine franchise
                $franchise = null;
                if ($saName) {
                    $sa = ServiceAdvisor::where('name', $saName)->first();
                    if ($sa && $sa->franchise) {
                        $franchise = $sa->franchise;
                    }
                }
                
                // Fallback to detected franchise
                if (!$franchise && $defaultFranchise) {
                    $franchise = $defaultFranchise;
                }
                
                // Concatenate Address 01-05
                $addressParts = [];
                for ($k = 1; $k <= 5; $k++) {
                    $key = 'address ' . str_pad($k, 2, '0', STR_PAD_LEFT); // address 01, address 02...
                    $addrVal = $this->getColumnValue($row, $headerMap, [$key]);
                    if ($addrVal) {
                        $addressParts[] = $addrVal;
                    }
                }
                $customerAddress = implode("\n", $addressParts);

                $jobData = [
                    'customer_name' => $this->getColumnValue($row, $headerMap, ['customer name', 'customer', 'nama customer']),
                    'customer_address' => $customerAddress,
                    'department' => $this->getColumnValue($row, $headerMap, ['d', 'dept', 'department']),
                    'work_order_number' => $this->getColumnValue($row, $headerMap, ['no job', 'work order', 'wo']), 
                    'foreman' => $this->helpersFindOrCreate(Foreman::class, $foremanName, $franchise),
                    'block' => $this->getColumnValue($row, $headerMap, ['block', 'blok']),
                    'franchise' => $franchise, 
                    'plate_number' => $plateNumber,
                    'service_advisor' => $this->helpersFindOrCreate(ServiceAdvisor::class, $saName, $franchise),
                    'technician' => $this->getColumnValue($row, $headerMap, ['teknisi', 'technician', 'mekanik']),
                    'job_type' => $this->getColumnValue($row, $headerMap, ['jenis', 'job_type', 'type']),
                    'job_date' => $this->parseDate($this->getColumnValue($row, $headerMap, ['date', 'created', 'tanggal', 'tgl', 'job_date'])),
                    'description' => $this->getColumnValue($row, $headerMap, ['keluhan', 'description', 'keterangan']),
                    'check_in_time' => $this->parseTime($this->getColumnValue($row, $headerMap, [
                        'jam', 'time', 'check in time', 'waktu'
                    ])),
                    'payment_type' => $this->getColumnValue($row, $headerMap, [
                        'code', 'kode', 'payment type', 'tipe bayar'
                    ]),
                    'job_description' => $this->getColumnValue($row, $headerMap, [
                        'operation', 'job description', 'pekerjaan', 'deskripsi'
                    ]),
                    'deadline' => $this->parseDate($this->getColumnValue($row, $headerMap, [
                        'deadline', 'estimasi selesai', 'due date'
                    ])),
                    'unit_type' => $this->getColumnValue($row, $headerMap, [
                        'tipe', 'type unit', 'unit', 'model', 'type', 'kendaraan'
                    ]),
                    // Set default work_status for new jobs so they appear in Kanban
                    // 'work_status' => 'belum_diproses', // REMOVED: Don't overwrite status for existing jobs
                ];
                
                $firstReg = $this->parseDate($this->getColumnValue($row, $headerMap, [
                    'date reg', 'date first reg', 'first reg', 'tgl registrasi pertama'
                ]));
                if ($firstReg) {
                    $jobData['date_first_reg'] = $firstReg;
                }

                // Match by Job Number AND Franchise if possible? 
                // Problem: If franchise is null (new SA), we might match wrong job if duplicates exist.
                // Constraint: User said duplicate WIPs exist across PC/CV.
                // If we don't know franchise, we can't safely distinguish.
                // However, 'updateOrCreate' maps attributes.
                // If we assume Job Number is unique PER Franchise.
                
                $searchCriteria = ['job_number' => $jobNumber];
                if ($franchise) {
                    $searchCriteria['franchise'] = $franchise;
                }
                
                // Manual check for existing job + conflict handling
                $existingJob = Job::where($searchCriteria)->first();
                $job = null;
                $isDummy = false;

                if ($existingJob) {
                    // Normalize plates for comparison
                    $dbPlate = $this->sanitizeText($existingJob->plate_number);
                    $newPlate = $this->sanitizeText($plateNumber);
                    
                    // If plate mismatch AND job is already established (Invoiced or Uninvoiced - not just "work_in_progress")
                    // Actually user said: "if it is same plate number ... allow update data".
                    // Implies if different, we do dummy.
                    // We only want to enable this safety if the job is somewhat authoritative OR to prevent overwriting correct data with typo.
                    // Simpler rule: If plates differ significantly, assume conflict.
                    
                    if ($dbPlate && $newPlate && $dbPlate !== $newPlate) {
                         // CONFLICT Detected
                         // Create Dummy Job
                         $dummyWip = $jobNumber . '-DUP-' . ($i + 1); // Use row index for uniqueness
                         $isDummy = true;
                         
                         $job = Job::create(array_filter(array_merge($jobData, [
                             'job_number' => $dummyWip,
                             'import_id' => $importId,
                             'is_dummy_wip' => true,
                             'description' => ($jobData['description'] ?? '') . " [CONFLICT: Orig WIP {$jobNumber} has plate {$existingJob->plate_number}]"
                         ]), fn($value) => !is_null($value)));
                         
                         // Log the conflict
                         \Log::warning("Import Conflict: WIP {$jobNumber} (Plate {$existingJob->plate_number}) vs Input (Plate {$plateNumber}). Created Dummy {$dummyWip}");
                         // Treat as 'imported' (new record)
                         $imported++;
                         
                         // Add to failedRows as info so user sees it? Or just let it be successfully imported as dummy?
                         // User said "flagged and can be reported in detail info".
                         // We'll mark it as successful import (it is saved), but maybe user can filter by is_dummy_wip later.
                    } else {
                        // Match or update allowed
                        $existingJob->update(array_filter(array_merge($jobData, ['import_id' => $importId]), fn($value) => !is_null($value)));
                        $job = $existingJob;
                        $updated++;
                        
                        // Log import update activity
                        JobActivity::log($job, JobActivity::ACTION_IMPORT_UPDATED, 'Job updated via data import');
                    }
                } else {
                    // New Job - filter null values to allow DB defaults
                    // Set default work_status ONLY for new jobs
                    $job = Job::create(array_filter(array_merge($jobData, ['job_number' => $jobNumber, 'import_id' => $importId, 'work_status' => Job::WORK_STATUSES[0]]), fn($value) => !is_null($value)));
                    $imported++;
                    \Log::info("PROGRESS: Created job {$jobNumber}", ['data' => array_filter($jobData, fn($v) => !is_null($v))]);
                    
                    // Log import creation activity
                    JobActivity::log($job, JobActivity::ACTION_IMPORT_CREATED, 'Job created via data import');
                }

                // Create or update vehicle with model from unit_type
                if (!empty($plateNumber)) {
                    $unitType = $jobData['unit_type'] ?? null;
                    $vehicle = Vehicle::updateOrCreate(
                        ['plate_number' => $plateNumber],
                        array_filter([
                            'is_in_workshop' => true,
                            'model' => $unitType,
                            'customer_name' => $jobData['customer_name'] ?? null,
                            'import_id' => $importId,
                        ], fn($v) => !is_null($v))
                    );

                    // Link job to vehicle record
                    if ($job) {
                        $job->update(['vehicle_id' => $vehicle->id]);
                    }
                }
                } catch (\Exception $e) {
                    $failed++;
                    if (count($failedRows) < $maxFailedRows) {
                        $failedRows[] = [
                            'row' => $i + 1, // +1 for 1-indexed row number in Excel
                            'sheet' => $sheetName,
                            'job_number' => $jobNumber ?? 'N/A',
                            'plate_number' => $plateNumber ?? 'N/A',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            } // end rows loop
        } // end sheets loop

        // Update import record with final counts
        $import->update([
            'records_imported' => $imported,
            'records_updated' => $updated,
            'records_failed' => $failed,
            'failed_rows' => $failedRows,
        ]);

        // Recalculate customer duplicates in background after import
        \Illuminate\Support\Facades\Artisan::queue('customers:find-duplicates');
        \Illuminate\Support\Facades\Artisan::queue('customers:refresh-summaries');

        return redirect()->route('imports.show', $import)
            ->with('success', "Import completed: {$imported} new, {$updated} updated, {$failed} failed.");
    }

    public function importUninvoiced(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,ods,csv,txt',
            'franchise' => 'nullable|in:PC,CV',
        ]);

        $franchise = $request->input('franchise');
        $file = $request->file('file');
        
        // Auto-backup before processing
        // Auto-backup before processing
        try {
            // Increase timeout for backup operation
            set_time_limit(600); // 10 minutes
            $this->backupService->create('Auto-backup before Import Uninvoiced: ' . $file->getClientOriginalName());
        } catch (\Exception $e) {
            \Log::error('Auto-backup failed: ' . $e->getMessage());
        }

        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $header = array_shift($rows);
        $headerMap = array_flip(array_map('strtolower', array_map('trim', $header)));

        // Validate this is an uninvoiced file
        $hasInvoiceColumns = isset($headerMap['invoice']) || isset($headerMap['inv+ppn']) || isset($headerMap['inv+ppn+meterai']);
        
        if ($hasInvoiceColumns) {
            return redirect()->back()
                ->with('error', 'This appears to be an INVOICED file (contains Invoice/Inv+PPN columns). Please use the "Import Invoiced" menu instead.');
        }

        // PERFORMANCE OPTIMIZATION: Pre-fetch and Caching
        // 1. Load helpers (SA, Foreman)
        $saCache = ServiceAdvisor::pluck('id', 'name')->toArray(); // name => id
        $foremanCache = Foreman::pluck('id', 'name')->toArray(); // name => id
        
        // 2. Prepare Collections for Batch Loading
        $wipList = [];
        $plateList = [];
        foreach ($rows as $row) {
            $wip = $this->getColumnValue($row, $headerMap, ['wip', 'no job', 'job_number', 'job number', 'nomer job', 'no. job']);
            if ($wip) $wipList[] = $wip;
            
            $plate = $this->getColumnValue($row, $headerMap, ['reg. no.', 'reg no', 'no polisi', 'plate_number', 'plate number', 'nopol']);
            if ($plate) $plateList[] = $plate;
        }
        $wipList = array_unique($wipList);
        $plateList = array_unique($plateList);

        // 3. Batch Load Existing Data
        $existingJobsCollection = Job::whereIn('job_number', $wipList)->get();
        // Group by job_number to handle duplicates (PC vs CV)
        $existingJobsMap = $existingJobsCollection->groupBy('job_number');
        $existingVehiclesMap = Vehicle::whereIn('plate_number', $plateList)->get()->keyBy('plate_number');
        
        // 4. Local Cache for Customer Lookups to avoid repeated DB hits
        $customerLookupCache = []; // name|plate => customer_id

        // Mute dashboard broadcasts for bulk operation
        Job::$muteBroadcast = true;
        
        $imported = 0;
        $updated = 0;
        $failed = 0;
        $customersLinked = 0;
        $customersUnlinked = [];
        $failedRows = [];
        $conflictRows = [];
        $maxFailedRows = 100;
        $rowIndex = 0;

        // Create import record
        $import = Import::create([
            'file_name' => $file->getClientOriginalName(),
            'import_type' => 'uninvoiced',
            'records_imported' => 0,
            'records_updated' => 0,
            'records_failed' => 0,
            'imported_by' => auth()->id(),
        ]);
        $importId = $import->id;

        // Wrap processing in Transaction
        \DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $rowIndex++;
                $jobNumber = null;
                $plateNumber = null;
                try {
                    // Extract IDs
                    $jobNumber = $this->getColumnValue($row, $headerMap, [
                        'wip', 'no job', 'job_number', 'job number', 'nomer job', 'no. job'
                    ]);
                    
                    if (empty($jobNumber)) {
                        continue; // Skip empty rows
                    }

                    $plateNumber = $this->getColumnValue($row, $headerMap, [
                        'reg. no.', 'reg no', 'no polisi', 'plate_number', 'plate number', 'nopol'
                    ]);

                    $customerName = $this->getColumnValue($row, $headerMap, [
                        'customer name', 'customer', 'nama customer', 'nama pelanggan'
                    ]);

                    // Address Construction
                    $addressParts = [];
                    for ($k = 1; $k <= 5; $k++) {
                        $key = 'address ' . str_pad($k, 2, '0', STR_PAD_LEFT);
                        $addrVal = $this->getColumnValue($row, $headerMap, [$key]);
                        if ($addrVal) $addressParts[] = $addrVal;
                    }
                    $customerAddress = !empty($addressParts) 
                        ? implode("\n", $addressParts) 
                        : $this->getColumnValue($row, $headerMap, ['customer address', 'alamat', 'address', 'alamat customer']);
                    
                    // Optimized Customer Lookup (Memoized)
                    $customerId = null;
                    if (!empty($customerName)) {
                        $cacheKey = $customerName . '|' . $plateNumber;
                        if (isset($customerLookupCache[$cacheKey])) {
                            $customerId = $customerLookupCache[$cacheKey];
                            if ($customerId) $customersLinked++; // Count hits as linked (approx)
                        } else {
                            $linkedCustomer = CustomerAlias::findCustomerByName($customerName, $plateNumber);
                            if ($linkedCustomer) {
                                $customerId = $linkedCustomer->id;
                                $customersLinked++;
                                $customerLookupCache[$cacheKey] = $customerId;
                            } else {
                                $customersUnlinked[$customerName] = ($customersUnlinked[$customerName] ?? 0) + 1;
                                $customerLookupCache[$cacheKey] = null; // Cache miss too
                            }
                        }
                    }

                    // Helper Retrieval (Local Cache)
                    $saName = $this->getColumnValue($row, $headerMap, ['service advisor', 'sa', 'service_advisor']);
                    if ($saName && !isset($saCache[$saName])) {
                        // Create if not exists and update cache
                        $sa = ServiceAdvisor::firstOrCreate(['name' => $saName], ['active' => true, 'franchise' => $franchise]);
                        $saCache[$saName] = $sa->id;
                    }

                    $foremanName = $this->getColumnValue($row, $headerMap, ['foreman', 'kepala regu', 'mandor']);
                    if ($foremanName && !isset($foremanCache[$foremanName])) {
                        $fm = Foreman::firstOrCreate(['name' => $foremanName], ['active' => true, 'franchise' => $franchise]);
                        $foremanCache[$foremanName] = $fm->id;
                    }

                    $jobData = [
                        'block' => $this->getColumnValue($row, $headerMap, ['ll', 'block', 'blok']),
                        'department' => $this->getColumnValue($row, $headerMap, ['d', 'dept', 'department']),
                        'franchise' => $franchise,
                        'job_card' => $this->getColumnValue($row, $headerMap, ['job card', 'jobcard', 'no job card']),
                        'plate_number' => $plateNumber,
                        'customer_name' => $customerName,
                        'customer_id' => $customerId,
                        'customer_address' => $customerAddress,
                        'customer_address' => $customerAddress,
                        'unit_type' => $this->getColumnValue($row, $headerMap, ['type unit', 'unit', 'model', 'type', 'kendaraan', 'tipe unit', 'vehicle type']),
                        'type_unit' => $this->getColumnValue($row, $headerMap, ['type unit', 'unit', 'model', 'type', 'kendaraan', 'tipe unit', 'vehicle type']),
                        'account_no' => $this->getColumnValue($row, $headerMap, ['acc   no', 'acc no', 'account no', 'account', 'no akun', 'akun']),
                        'service_advisor' => $saName,
                        'technician' => $this->getColumnValue($row, $headerMap, ['teknisi', 'technician', 'mekanik']),
                        'foreman' => $foremanName,
                        'job_date' => $this->parseDate($this->getColumnValue($row, $headerMap, ['created', 'date registered', 'tanggal', 'date', 'tgl', 'job_date', 'tgl job', 'check in date'])),
                        'date_in' => $this->parseDate($this->getColumnValue($row, $headerMap, ['created', 'date registered', 'tanggal', 'date', 'tgl', 'job_date', 'check in date'])),
                        'check_in_time' => $this->parseTime($this->getColumnValue($row, $headerMap, [
                            'jam', 'time', 'check in time', 'waktu'
                        ])),
                        'payment_type' => $this->getColumnValue($row, $headerMap, ['code', 'kode', 'payment type', 'tipe bayar']),
                        'job_description' => $this->getColumnValue($row, $headerMap, ['operation', 'job description', 'pekerjaan', 'deskripsi']),
                        'deadline' => $this->parseDate($this->getColumnValue($row, $headerMap, ['deadline', 'estimasi selesai', 'due date'])),
                        'labour_sales' => $this->parseAmount($this->getColumnValue($row, $headerMap, ['labour sale', 'labour sales', 'labor sale', 'labor sales', 'jasa', 'biaya jasa'])),
                        'part_sales' => $this->parseAmount($this->getColumnValue($row, $headerMap, ['part sales', 'parts sales', 'part sale', 'sparepart', 'biaya part'])),
                        'total_sales' => $this->parseAmount($this->getColumnValue($row, $headerMap, ['total sales', 'total  sales', 'total', 'grand total', 'estimasi', 'amount', 'nilai'])),
                        'estimated_amount' => $this->parseAmount($this->getColumnValue($row, $headerMap, ['total sales', 'total', 'estimasi', 'amount', 'nilai'])),
                        'rq' => $this->getColumnValue($row, $headerMap, ['rq', 'requisition', 'req']),
                        'no_order_part_mbina' => $this->getColumnValue($row, $headerMap, ['no order part mbina', 'order part', 'no order']),
                        'lain_lain' => $this->getColumnValue($row, $headerMap, ['lain lain', 'lain-lain', 'other', 'lainnya']),
                        'latest_remark' => $this->getColumnValue($row, $headerMap, ['remarks', 'remark', 'keterangan', 'catatan']),
                        'update_remarks' => $this->getColumnValue($row, $headerMap, ['update remarks', 'update remark', 'update keterangan']),
                        'status' => 'uninvoiced',
                    ];
                    
                    $firstReg = $this->parseDate($this->getColumnValue($row, $headerMap, ['date reg', 'date first reg', 'first reg', 'tgl registrasi pertama']));
                    if ($firstReg) {
                        $jobData['date_first_reg'] = $firstReg;
                    }

                    // Process Job Logic using Maps
                    $jobCandidates = $existingJobsMap[$jobNumber] ?? collect();
                    $existingJob = null;
                    
                    // 1. Try exact franchise match
                    $existingJob = $jobCandidates->firstWhere('franchise', $franchise);
                    
                    // 2. Fallback: If only 1 candidate and we don't know franchise, or just want to match loosely?
                    // Strict mode: Only match if franchise matches.
                    // But if DB job has no franchise (legacy)? 
                    if (!$existingJob && $jobCandidates->count() === 1) {
                         $candidate = $jobCandidates->first();
                         if (empty($candidate->franchise)) {
                             $existingJob = $candidate;
                         }
                    }
                    
                    // RECONCILIATION Checks (simplified for performance, but keeping core logic)
                    if (!$existingJob && !empty($plateNumber)) {
                        // Check for Dummy Candidate (needs DB query as we don't cache all dummies, but it's rare)
                        $dummyCandidate = Job::where('is_dummy_wip', true)
                            ->where('plate_number', $plateNumber)
                            ->where('franchise', $franchise)
                            ->orderBy('created_at', 'desc')
                            ->first();
                            
                        if ($dummyCandidate) {
                            $dummyCandidate->update([
                                'job_number' => $jobNumber,
                                'is_dummy_wip' => false,
                                'description' => ($dummyCandidate->description ?? '') . " [RECONCILED: Original Typo WIP was {$dummyCandidate->job_number}]"
                            ]);
                            $existingJob = $dummyCandidate; // Treat as existing now
                            $existingJobsMap[$jobNumber] = ($existingJobsMap[$jobNumber] ?? collect())->push($existingJob); // Update map
                            \Log::info("UNINVOICED import: RECONCILED Dummy Job {$dummyCandidate->id}");
                        }
                    }

                    if ($existingJob) {
                        // Conflict/Swap Check
                        $dbPlate = $this->sanitizeText($existingJob->plate_number);
                        $newPlate = $this->sanitizeText($plateNumber);
                        
                        // If plate mismatch on existing regular Job
                        if ($dbPlate && $newPlate && $dbPlate !== $newPlate) {
                            // Check for SWAP Scenario (Rare, so DB query ok)
                            $dummyWithCorrectPlate = Job::where('is_dummy_wip', true)
                                ->where('plate_number', $plateNumber)
                                ->where('franchise', $franchise)
                                ->first();

                            if ($dummyWithCorrectPlate) {
                                // SWAP LOGIC
                                $oldWip = $existingJob->job_number;
                                $wrongWip = $oldWip . '-WRONG-' . $existingJob->id;
                                
                                $existingJob->update([
                                    'job_number' => $wrongWip, 
                                    'is_dummy_wip' => true,
                                    'import_id' => $importId
                                ]);
                                
                                $dummyWithCorrectPlate->update([
                                    'job_number' => $jobNumber,
                                    'is_dummy_wip' => false,
                                    'import_id' => $importId
                                ]);
                                
                                $conflictRows[] = ['row' => $rowIndex, 'type' => 'SWAP', 'action' => "Swapped {$oldWip} to {$plateNumber}"];
                                $existingJob = $dummyWithCorrectPlate;
                                $existingJobsMap[$jobNumber] = ($existingJobsMap[$jobNumber] ?? collect())->push($existingJob);
                            } else {
                                // Conflict - Create Dummy
                                $dummyWip = $jobNumber . '-DUP-' . $rowIndex;
                                $job = Job::create(array_filter(array_merge($jobData, [
                                    'job_number' => $dummyWip,
                                    'import_id' => $importId,
                                    'is_dummy_wip' => true,
                                    'description' => ($jobData['description'] ?? '') . " [CONFLICT: Orig WIP {$jobNumber} has plate {$existingJob->plate_number}]"
                                ]), fn($v) => !is_null($v)));
                                $imported++;
                                continue; // Done with this row
                            }
                        }
                        
                        // Normal Update
                        $oldPlate = $existingJob->plate_number; // Track for orphan cleanup
                        $existingJob->update(array_filter(array_merge($jobData, ['import_id' => $importId]), fn($v) => !is_null($v)));
                        $updated++;
                        
                        // Check for orphan vehicle cleanup if plate changed
                        if ($oldPlate && $oldPlate !== $plateNumber) {
                             $this->cleanupOrphanVehicle($oldPlate, $plateNumber, $importId);
                             // Update local vehicle map to reflect change if needed?
                             // It's complex to update map perfectly, but acceptable for this edge case.
                        }
                        
                    } else {
                        // Create New
                        $job = Job::create(array_filter(array_merge($jobData, ['job_number' => $jobNumber, 'import_id' => $importId, 'work_status' => Job::WORK_STATUSES[0]]), fn($v) => !is_null($v)));
                        $imported++;
                        $existingJobsMap[$jobNumber] = ($existingJobsMap[$jobNumber] ?? collect())->push($job); // Add to map for subsequent rows
                    }

                    // Vehicle Update (using Map)
                    if (!empty($plateNumber)) {
                        $vehicleData = array_filter([
                            'is_in_workshop' => true,
                            'model' => $jobData['unit_type'] ?? null,
                            'customer_name' => $jobData['customer_name'] ?? null,
                            'customer_id' => $customerId, // Link customer
                            'import_id' => $importId,
                            'registration_date' => $jobData['date_first_reg'] ?? null, // Map reg date
                        ], fn($v) => !is_null($v));

                        if (isset($existingVehiclesMap[$plateNumber])) {
                            $vehicle = $existingVehiclesMap[$plateNumber];
                            $vehicle->update($vehicleData);
                        } else {
                            $vehicle = Vehicle::create(array_merge(['plate_number' => $plateNumber], $vehicleData));
                            $existingVehiclesMap[$plateNumber] = $vehicle;
                        }

                        // NEW: Explicitly link job to the vehicle record
                        if (isset($job) && $job) {
                            $job->update(['vehicle_id' => $vehicle->id]);
                        }
                    }

                } catch (\Exception $e) {
                    $failed++;
                    if (count($failedRows) < $maxFailedRows) {
                        $failedRows[] = [
                            'row' => $rowIndex, 
                            'job_number' => $jobNumber ?? 'N/A', 
                            'error' => $e->getMessage()
                        ];
                    }
                }
            } // end loop

            \DB::commit(); // Commit all changes
            
            // Unmute and fire single broadcast
            Job::$muteBroadcast = false;
            try {
                event(new \App\Events\DashboardUpdated());
            } catch (\Exception $e) {}

        } catch (\Exception $e) {
            \DB::rollBack();
            Job::$muteBroadcast = false; // Reset flag on error
            \Log::error("Import transaction failed: " . $e->getMessage());
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }

        // Update import record
        $import->update([
            'records_imported' => $imported,
            'records_updated' => $updated,
            'records_failed' => $failed,
            'failed_rows' => $failedRows,
            'conflict_rows' => $conflictRows,
            'customers_linked' => $customersLinked,
            'customers_unlinked' => array_keys($customersUnlinked),
        ]);

        // Background jobs - DISABLED for performance (they run sync and block the UI)
        // \Illuminate\Support\Facades\Artisan::queue('customers:find-duplicates');
        // \Illuminate\Support\Facades\Artisan::queue('customers:refresh-summaries');

        $unlinkedCount = count($customersUnlinked);
        $linkingMsg = $customersLinked > 0 || $unlinkedCount > 0 
            ? " Customer linking: {$customersLinked} linked, {$unlinkedCount} unlinked."
            : "";

        return redirect()->route('imports.show', $import)
            ->with('success', "Import completed: {$imported} new, {$updated} updated, {$failed} failed.{$linkingMsg}");
    }

    public function importInvoiced(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,ods,csv,txt',
            'franchise' => 'required|in:PC,CV',
        ]);

        $franchise = $request->input('franchise');
        $file = $request->file('file');
        
        // Auto-backup before processing
        try {
            // Increase timeout for backup operation
            set_time_limit(600); // 10 minutes
            // $this->backupService->create('Auto-backup before Import Invoiced: ' . $file->getClientOriginalName());
        } catch (\Exception $e) {
            \Log::error('Auto-backup failed: ' . $e->getMessage());
        }

        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $header = array_shift($rows);
        $headerMap = array_flip(array_map('strtolower', array_map('trim', $header)));

        // Validate this is an invoiced file, not an uninvoiced file
        // Invoiced files should have columns like 'invoice', 'inv+ppn', 'inv+ppn+meterai'
        $hasInvoiceColumns = isset($headerMap['invoice']) || isset($headerMap['inv+ppn']) || isset($headerMap['inv+ppn+meterai']);
        
        if (!$hasInvoiceColumns) {
            return redirect()->back()
                ->with('error', 'This does not appear to be an INVOICED file (missing Invoice/Inv+PPN columns). Please use the correct import menu for this file type.');
        }

        $imported = 0;
        $updated = 0;
        $failed = 0;
        $customersLinked = 0;
        $customersUnlinked = [];
        $failedRows = [];
        $conflictRows = [];
        $maxFailedRows = 100;
        $rowIndex = 0;

        // Create import record first to get import_id
        $import = Import::create([
            'file_name' => $file->getClientOriginalName(),
            'import_type' => 'invoiced',
            'records_imported' => 0,
            'records_updated' => 0,
            'records_failed' => 0,
            'imported_by' => auth()->id(),
        ]);
        $importId = $import->id;

        // Mute broadcasts for performance
        Job::$muteBroadcast = true;
        
        // Pre-fetch all WIPs to avoid N+1 queries
        $wipList = [];
        $plateList = [];
        foreach ($rows as $row) {
            $wip = $this->getColumnValue($row, $headerMap, ['wip', 'no job', 'job_number', 'job number']);
            if ($wip) $wipList[] = trim((string)$wip);
            
            $plate = $this->getColumnValue($row, $headerMap, ['reg no', 'vehicle no', 'no polisi', 'nopol']);
            if ($plate) $plateList[] = $this->sanitizeText($plate);
        }
        $wipList = array_unique($wipList);
        $plateList = array_unique($plateList);

        // Batch load existing jobs
        // We load all jobs matching these WIPs. In case of duplicates (PC vs CV), we'll filter in memory.
        $existingJobsCollection = Job::whereIn('job_number', $wipList)->get();
        
        // Group by job_number for easy lookup. Note: job_number is not unique globaly (PC/CV collision potential)
        $existingJobsMap = [];
        foreach ($existingJobsCollection as $job) {
            $existingJobsMap[$job->job_number][] = $job;
        }

        // Batch load dummy/conflict jobs for resolution logic
        // We load all dummy jobs that might match our plates or WIPs
        $dummyJobs = Job::where('is_dummy_wip', true)
            ->where(function($q) use ($wipList, $plateList) {
                $q->whereIn('job_number', $wipList)
                  ->orWhereIn('plate_number', $plateList);
            })->get();

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $rowIndex++;
                $jobNumber = null;
                $plateNumber = null;
                try {
                    // Get WIP (job number) - required
                    $jobNumber = $this->getColumnValue($row, $headerMap, ['wip', 'no job', 'job_number', 'job number']);
                    
                    if (empty($jobNumber)) {
                        continue;
                    }
                
                // Skip invalid job numbers (single digits, summary rows, etc.)
                if (strlen(trim($jobNumber)) < 4 && is_numeric($jobNumber)) {
                    continue;
                }
                if (str_contains(strtoupper($jobNumber), 'TOTAL') || str_contains(strtoupper($jobNumber), 'GRAND')) {
                    continue;
                }

                $normalizedJobNumber = trim((string)$jobNumber);

                // Get invoice number - from "Invoice" column (NOT "Inv" - that's the amount)
                $invoiceNumber = $this->getColumnValue($row, $headerMap, [
                    'invoice', 'no invoice', 'invoice_number', 'no faktur'
                ]);

                // Parse invoice date - from "date" column
                $invoiceDate = $this->parseDate($this->getColumnValue($row, $headerMap, [
                    'date', 'invoice date', 'tgl invoice', 'tanggal invoice'
                ]));

                // Get other fields from the invoice report
                $plateNumber = $this->getColumnValue($row, $headerMap, ['reg no', 'vehicle no', 'no polisi', 'nopol']);
                $customerName = $this->getColumnValue($row, $headerMap, ['customer name', 'customer', 'nama customer']);
                $chassisNumber = $this->getColumnValue($row, $headerMap, ['chassis number', 'chassis', 'no rangka']);
                
                // Find linked DMS customer (uses vehicle fallback if name doesn't match)
                $linkedCustomer = null;
                $customerId = null;
                if (!empty($customerName)) {
                    // Use static cache within findCustomerByName to avoid repeated DB hits
                    $linkedCustomer = CustomerAlias::findCustomerByName($customerName, $plateNumber, $chassisNumber);
                    if ($linkedCustomer) {
                        $customerId = $linkedCustomer->id;
                        $customersLinked++;
                    } else {
                        $customersUnlinked[$customerName] = ($customersUnlinked[$customerName] ?? 0) + 1;
                    }
                }
                $accountNo = $this->getColumnValue($row, $headerMap, ['account', 'account no', 'akun']);
                $typeSale = $this->getColumnValue($row, $headerMap, ['type sale', 'tipe sale', 'jenis']);
                $department = $this->getColumnValue($row, $headerMap, ['dept', 'd', 'department']);
                $dateIn = $this->parseDate($this->getColumnValue($row, $headerMap, ['date in', 'tgl masuk', 'tanggal masuk']));
                $dateOut = $this->parseDate($this->getColumnValue($row, $headerMap, ['wldate out', 'date out', 'tgl keluar', 'tanggal keluar']));
                
                // Parse amounts
                $invAmount = $this->parseAmount($this->getColumnValue($row, $headerMap, ['inv', 'amount']));
                $invPpn = $this->parseAmount($this->getColumnValue($row, $headerMap, ['inv+ppn', 'inv ppn']));
                $invPpnMeterai = $this->parseAmount($this->getColumnValue($row, $headerMap, ['inv+ppn+meterai', 'inv ppn meterai', 'total']));

                // Determine if this is a Credit Note (CN) - skip work_status update for CN
                $isCreditNote = $typeSale && (
                    str_contains(strtoupper($typeSale), 'CN') ||
                    str_contains(strtolower($typeSale), 'credit note') ||
                    str_contains(strtolower($typeSale), 'credit_note')
                );

                // Prepare invoice-specific data (fields that come from invoice report)
                $invoiceData = array_filter([
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $invoiceDate,
                    'inv_amount' => $invAmount,
                    'inv_ppn' => $invPpn,
                    'inv_ppn_meterai' => $invPpnMeterai,
                    'type_sale' => $typeSale,
                    'date_in' => $dateIn,
                    'date_out' => $dateOut,
                    'chassis_number' => $chassisNumber,
                    'customer_id' => $customerId,
                    'status' => 'invoiced',
                    'invoiced_at' => $invoiceDate ?? now(),
                    // Set work_status to "11. Proses Invoice" for regular invoices, skip for CN
                    // User Request: Force update status for Invoiced import
                   'work_status' => $isCreditNote ? null : Job::WORK_STATUSES[10], 
                ], fn($v) => !is_null($v));

                
                // Find existing job from memory map
                $job = null;
                if (isset($existingJobsMap[$normalizedJobNumber])) {
                    // Look for exact franchise match first
                    foreach ($existingJobsMap[$normalizedJobNumber] as $candidate) {
                        if ($candidate->franchise === $franchise) {
                            $job = $candidate;
                            break;
                        }
                    }
                    // Fallback to any match if no franchise match
                    if (!$job && count($existingJobsMap[$normalizedJobNumber]) > 0) {
                        $job = $existingJobsMap[$normalizedJobNumber][0];
                    }
                }
                
                // RECONCILIATION: Check for Dummys logic (Memory-based)
                if (!$job && !empty($plateNumber)) {
                    // Try to find a dummy job with matching plate number in our pre-loaded dummies
                    $dummyCandidate = $dummyJobs->filter(function($d) use ($plateNumber, $franchise) {
                        return $d->plate_number === $plateNumber && $d->franchise === $franchise;
                    })->first(); // Prioritize exact match

                    // Fuzzy match fallback
                    if (!$dummyCandidate) {
                         $dummyCandidate = $dummyJobs->filter(function($d) use ($plateNumber) {
                            return $d->plate_number === $plateNumber;
                        })->first();
                    }
                        
                    if ($dummyCandidate) {
                        $job = $dummyCandidate;
                        // Fix the WIP and remove dummy flag
                        $job->update([
                            'job_number' => $normalizedJobNumber,
                            'is_dummy_wip' => false,
                            'description' => ($job->description ?? '') . " [RECONCILED: Original Typo WIP was {$job->job_number}]"
                        ]);
                        \Log::info("INVOICE import: RECONCILED Dummy Job {$job->id} (Plate {$plateNumber}) to Real WIP {$normalizedJobNumber}");
                        
                        // Update our memory map for future lookups
                        $existingJobsMap[$normalizedJobNumber][] = $job;
                    }
                }

                if ($job) {
                    // WIP SWAP LOGIC: Check if current job holder has wrong plate
                    $dbPlate = $this->sanitizeText($job->plate_number);
                    $invoicePlate = $this->sanitizeText($plateNumber);
                    
                    if ($dbPlate && $invoicePlate && $dbPlate !== $invoicePlate) {
                        // Current job has DIFFERENT plate than Invoice - might be wrong holder
                        // Search for Dummy with the correct (Invoice) plate in memory
                         $dummyWithCorrectPlate = $dummyJobs->filter(function($d) use ($plateNumber, $invoicePlate) {
                            return $d->plate_number === $plateNumber || $d->plate_number === $invoicePlate;
                        })->first();
                        
                        if ($dummyWithCorrectPlate) {
                            // SWAP: Demote current holder, Promote Dummy
                            $oldWip = $job->job_number;
                            $wrongWip = $oldWip . '-WRONG-' . $job->id;
                            
                            // Track conflict for report
                            $conflictRows[] = [
                                'row' => $rowIndex,
                                'type' => 'SWAP',
                                'original_wip' => $oldWip,
                                'new_wip' => $wrongWip,
                                'demoted_plate' => $job->plate_number,
                                'promoted_plate' => $plateNumber,
                                'action' => "Demoted {$job->plate_number} to {$wrongWip}, Promoted {$plateNumber} to {$oldWip}",
                            ];
                            
                            // 1. Demote wrongful holder to Dummy
                            $job->update([
                                'job_number' => $wrongWip,
                                'is_dummy_wip' => true,
                                'import_id' => $importId,
                                'description' => ($job->description ?? '') . " [DEMOTED: WIP {$oldWip} now belongs to {$plateNumber}]"
                            ]);
                            \Log::warning("INVOICE import: SWAPPED - Demoted Job {$oldWip} (Plate {$dbPlate}) to {$wrongWip}");
                            
                            // 2. Promote Dummy to real WIP
                            $dummyWithCorrectPlate->update([
                                'job_number' => $normalizedJobNumber,
                                'is_dummy_wip' => false,
                                'import_id' => $importId,
                                'description' => ($dummyWithCorrectPlate->description ?? '') . " [PROMOTED: Was {$dummyWithCorrectPlate->job_number}, now correct WIP {$normalizedJobNumber}]"
                            ]);
                            \Log::info("INVOICE import: SWAPPED - Promoted Dummy to Real WIP {$normalizedJobNumber} (Plate {$plateNumber})");
                            
                            // 3. Continue updating the promoted job
                            $job = $dummyWithCorrectPlate;
                            $existingJobsMap[$normalizedJobNumber][] = $job; // Update map
                        }
                    }
                    
                    // Track old plate before update for orphan cleanup
                    $oldPlateBeforeUpdate = $job->plate_number;
                    
                    // Update existing job with ONLY invoice-specific data
                    // Don't overwrite existing SA, Foreman, Address, sales figures, etc.
                    $job->update(array_merge($invoiceData, [
                        'import_id' => $importId,
                        'plate_number' => $plateNumber,
                        'customer_name' => $customerName,
                    ]));
                    $updated++;
                    \Log::info("INVOICE import: Updated existing job {$normalizedJobNumber} (ID: {$job->id}, Franchise: {$job->franchise})");
                    
                    // Log activity for timeline
                    JobActivity::log($job, 'invoice_import_updated', 'Job updated via invoice import');
                    
                    // Track and cleanup if plate changed
                    if ($oldPlateBeforeUpdate && $plateNumber && $this->sanitizeText($oldPlateBeforeUpdate) !== $this->sanitizeText($plateNumber)) {
                        // Track plate correction for report
                        $conflictRows[] = [
                            'row' => $rowIndex,
                            'type' => 'PLATE_CORRECTION',
                            'original_wip' => $normalizedJobNumber,
                            'new_wip' => $normalizedJobNumber,
                            'demoted_plate' => $oldPlateBeforeUpdate,
                            'promoted_plate' => $plateNumber,
                            'action' => "Plate corrected from {$oldPlateBeforeUpdate} to {$plateNumber}",
                        ];
                        // Cleanup orphan vehicle
                        $this->cleanupOrphanVehicle($oldPlateBeforeUpdate, $plateNumber, $importId);
                    }
                } else {
                    // For new job, include all available data
                    $newJobData = array_merge($invoiceData, array_filter([
                        'job_number' => $jobNumber,
                        'franchise' => $franchise,
                        'plate_number' => $plateNumber,
                        'customer_name' => $customerName,
                        'account_no' => $accountNo,
                        'department' => $department,
                        'import_id' => $importId,
                        // Set default status for NEW invoiced jobs
                        'work_status' => $isCreditNote ? null : Job::WORK_STATUSES[10],
                    ], fn($v) => !is_null($v)));
                    $job = Job::create($newJobData);
                    $imported++;
                    // Add to map for subsequent rows
                    $existingJobsMap[$normalizedJobNumber][] = $job;

                    \Log::info("INVOICE import: Created NEW job {$normalizedJobNumber} (no existing match found)");
                    
                    // Log activity for timeline
                    JobActivity::log($job, 'invoice_import_created', 'Job created via invoice import');
                }

                // Detect credit note by negative amount
                $isCreditNote = ($invPpnMeterai ?? 0) < 0 || ($invAmount ?? 0) < 0;

                // Create or update invoice history record (prevent duplicates)
                JobInvoice::updateOrCreate(
                    [
                        'job_id' => $job->id,
                        'invoice_number' => $invoiceNumber,
                        'invoice_date' => $invoiceDate,
                    ],
                    [
                        'invoice_type' => $isCreditNote ? 'credit_note' : 'invoice',
                        'inv_amount' => abs($invAmount ?? 0),
                        'inv_ppn' => abs($invPpn ?? 0),
                        'inv_ppn_meterai' => abs($invPpnMeterai ?? 0),
                        'type_sale' => $typeSale,
                        'import_id' => $importId,
                    ]
                );

                // Add remark about invoice (Direct DB insert to avoid notifications)
                $remarkDate = ($invoiceDate instanceof \Carbon\Carbon) ? $invoiceDate->format('d/m/Y') : ($invoiceDate ?: date('d/m/Y'));
                $remarkType = $isCreditNote ? 'Credit Note' : 'Invoice';
                $remarkText = "{$remarkType} on {$remarkDate}" . ($invoiceNumber ? " - #{$invoiceNumber}" : '');
                
                try {
                    // Optimized: Create Remark directly and update Job timestamps manually
                    // Avoiding $job->addRemark() which triggers notifications/broadcasts
                    \App\Models\Remark::create([
                        'job_id' => $job->id,
                        'remark_text' => $remarkText,
                        'created_by' => 'System Import',
                        'user_id' => auth()->id(),
                    ]);
                    
                    // Manually update latest remark fields on Job
                    $job->timestamps = false; // Prevent updated_at change if only remark added? No, we probably want updated_at
                    $job->update([
                        'latest_remark' => $remarkText,
                        'latest_remark_at' => now(),
                    ]);
                    $job->timestamps = true;

                } catch (\Exception $remarkError) {
                    // Ignore remark errors
                }

            } catch (\Exception $e) {
                \Log::error("Invoiced import error for row: " . json_encode($row) . " - " . $e->getMessage());
                $failed++;
                if (count($failedRows) < $maxFailedRows) {
                    $failedRows[] = [
                        'row' => $rowIndex + 1,
                        'sheet' => 'INVOICED',
                        'job_number' => $jobNumber ?? 'N/A',
                        'plate_number' => $plateNumber ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
        
        \Illuminate\Support\Facades\DB::commit();

        // Restore broadcast and trigger one update
        Job::$muteBroadcast = false;
        try {
            event(new \App\Events\DashboardUpdated());
        } catch (\Exception $e) {}

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            Job::$muteBroadcast = false;
            \Log::error("Import Invoiced Fatal Error: " . $e->getMessage());
            $import->update(['notes' => 'Fatal Error: ' . $e->getMessage()]);
            return redirect()->route('imports.index')->with('error', 'Import failed: ' . $e->getMessage());
        }

        // Update import record with final counts
        $import->update([
            'records_imported' => $imported,
            'records_updated' => $updated,
            'records_failed' => $failed,
            'failed_rows' => $failedRows,
            'conflict_rows' => $conflictRows,
            'customers_linked' => $customersLinked,
            'customers_unlinked' => array_keys($customersUnlinked),
        ]);

        $unlinkedCount = count($customersUnlinked);
        $linkingMsg = $customersLinked > 0 || $unlinkedCount > 0 
            ? " Customer linking: {$customersLinked} linked, {$unlinkedCount} unlinked."
            : "";

        return redirect()->route('imports.show', $import)
            ->with('success', "Import completed: {$imported} new, {$updated} updated as invoiced, {$failed} failed.{$linkingMsg}");
    }


    private function getColumnValue(array $row, array $headerMap, array $possibleNames): ?string
    {
        foreach ($possibleNames as $name) {
            if (isset($headerMap[$name]) && isset($row[$headerMap[$name]])) {
                $value = trim($row[$headerMap[$name]]);
                // Skip formula values that weren't calculated
                if (str_starts_with($value, '=')) {
                    return null;
                }
                // Skip Excel error values
                if (in_array($value, ['#N/A', '#REF!', '#VALUE!', '#NAME?', '#DIV/0!', '#NULL!', '#NUM!'])) {
                    return null;
                }
                if ($value !== '') {
                    // Sanitize: remove special chars from start/end, normalize whitespace
                    $value = $this->sanitizeText($value);
                    return $value !== '' ? $value : null;
                }
                return null;
            }
        }
        return null;
    }

    /**
     * Sanitize text value - remove special chars from start/end, normalize whitespace
     */
    private function sanitizeText(?string $value): string
    {
        if (empty($value)) {
            return '';
        }
        
        // Trim whitespace
        $cleaned = trim($value);
        
        // Remove backticks, quotes, and other special chars from start/end
        $cleaned = preg_replace('/^[\`\'\"\s\*\#\@\!\~]+/', '', $cleaned);
        $cleaned = preg_replace('/[\`\'\"\s\*\#\@\!\~]+$/', '', $cleaned);
        
        // Normalize multiple spaces and newlines to single space
        $cleaned = preg_replace('/[\s\r\n]+/', ' ', $cleaned);
        
        return trim($cleaned);
    }

    /**
     * Check if a value is an Excel error or formula
     */
    private function isInvalidValue(?string $value): bool
    {
        if ($value === null) return false;
        if (str_starts_with($value, '=')) return true;
        if (in_array($value, ['#N/A', '#REF!', '#VALUE!', '#NAME?', '#DIV/0!', '#NULL!', '#NUM!'])) return true;
        return false;
    }

    /**
     * Cleanup orphan vehicle when job's plate number is updated
     * Called during authoritative import (Uninvoiced/Invoice) when job plate changes
     * 
     * @param string|null $oldPlate The old plate number
     * @param string|null $newPlate The new (correct) plate number
     * @param int $importId Current import ID for logging
     * @return array|null Info about merge/deletion if performed
     */
    private function cleanupOrphanVehicle(?string $oldPlate, ?string $newPlate, int $importId): ?array
    {
        if (empty($oldPlate) || empty($newPlate) || $oldPlate === $newPlate) {
            return null;
        }
        
        // Check if old plate vehicle exists
        $oldVehicle = Vehicle::where('plate_number', $oldPlate)->first();
        if (!$oldVehicle) {
            return null; // No orphan to cleanup
        }
        
        // Check if old plate has any remaining jobs
        $remainingJobs = Job::where('plate_number', $oldPlate)->count();
        if ($remainingJobs > 0) {
            return null; // Not an orphan yet
        }
        
        // Old plate vehicle has no jobs - it's an orphan
        // Get or create the new plate vehicle
        $newVehicle = Vehicle::firstOrCreate(
            ['plate_number' => $newPlate],
            ['import_id' => $importId]
        );
        
        // Merge customer data from orphan to correct vehicle (if missing)
        if (empty($newVehicle->customer_name) && !empty($oldVehicle->customer_name)) {
            $newVehicle->customer_name = $oldVehicle->customer_name;
        }
        if (empty($newVehicle->model) && !empty($oldVehicle->model)) {
            $newVehicle->model = $oldVehicle->model;
        }
        $newVehicle->save();
        
        // Log and delete the orphan
        \Log::info("ORPHAN CLEANUP: Deleted vehicle {$oldPlate} (orphan after plate correction to {$newPlate})");
        $orphanInfo = [
            'old_plate' => $oldPlate,
            'new_plate' => $newPlate,
            'customer_name' => $oldVehicle->customer_name,
            'action' => 'merged_and_deleted',
        ];
        
        $oldVehicle->delete();
        
        return $orphanInfo;
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        // Skip Excel error values
        if (in_array($value, ['#N/A', '#REF!', '#VALUE!', '#NAME?', '#DIV/0!', '#NULL!', '#NUM!'])) {
            return null;
        }

        try {
            $parsedDate = null;
            
            // Try Excel serial date
            if (is_numeric($value)) {
                $parsedDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } else {
                // Try common date formats
                $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd M Y', 'd F Y', 'd-M-y', 'd-M-Y'];
                foreach ($formats as $format) {
                    $date = \DateTime::createFromFormat($format, $value);
                    if ($date && $date->format($format) === $value) {
                        $parsedDate = $date->format('Y-m-d');
                        break;
                    }
                }
                
                // Last resort - strtotime, but validate result
                if (!$parsedDate) {
                    $timestamp = strtotime($value);
                    if ($timestamp !== false && $timestamp > 0) {
                        $parsedDate = date('Y-m-d', $timestamp);
                    }
                }
            }
            
            // Validate year is reasonable (2000-2100), reject 1970 epoch dates
            if ($parsedDate) {
                $year = (int)substr($parsedDate, 0, 4);
                if ($year < 2000 || $year > 2100) {
                    return null;
                }
            }
            
            return $parsedDate;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseAmount(?string $value): ?float
    {
        if (empty($value)) {
            return null;
        }

        // Remove currency symbols and formatting
        $value = preg_replace('/[^\d.,]/', '', $value);
        $value = str_replace(',', '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function parseTime(?string $value): ?string
    {
        if (empty($value)) return null;
        
        try {
            // Decimal time from Excel (e.g., 0.5 = 12:00)
            if (is_numeric($value) && $value < 1) {
                 return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('H:i:s');
            }
             return date('H:i:s', strtotime($value));
        } catch (\Exception $e) {
            return null;
        }
    }

    private function helpersFindOrCreate($modelClass, $name, $franchise = null)
    {
        if (empty($name)) {
            return null;
        }

        $instance = $modelClass::firstOrCreate(
            ['name' => $name],
            ['active' => true, 'franchise' => $franchise]
        );

        return $instance->name;
    }

    /**
     * Import Booking sheet
     * Actual headers: tgl masuk, tgl booking, customer name, wip, tipe, foreman, sa, type of work, remarks
     */
    private function importBookingSheet(array $rows): array
    {
        $imported = 0; $updated = 0; $failed = 0;
        $failedRows = [];
        
        // Debug: log first 5 rows
        \Log::info("BOOKING SHEET - First 5 rows:", array_slice($rows, 0, 5));
        
        // Find header row - look for 'wip' or 'tgl booking' or 'customer name'
        $headerMap = [];
        $dataStartIndex = 0;
        
        // Indonesian months to skip as data rows
        $months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 
                   'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
        
        for ($i = 0; $i < min(10, count($rows)); $i++) {
            $rowString = strtolower(implode(' ', array_map(fn($v) => trim((string)$v), $rows[$i])));
            \Log::info("BOOKING row {$i}: {$rowString}");
            
            // Look for header row - must contain 'wip' and 'tgl' or 'customer'
            if (str_contains($rowString, 'wip') && (str_contains($rowString, 'tgl') || str_contains($rowString, 'customer') || str_contains($rowString, 'booking'))) {
                $headerMap = array_flip(array_map(fn($v) => strtolower(trim((string)$v)), $rows[$i]));
                $dataStartIndex = $i + 1;
                \Log::info("BOOKING - Found header at row {$i}, headerMap:", $headerMap);
                break;
            }
        }
        
        if (empty($headerMap)) {
            \Log::warning("BOOKING - No header found in first 10 rows");
            return compact('imported', 'updated', 'failed');
        }

        $loggedRows = 0;
        for ($i = $dataStartIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            try {
                // Skip month header rows (JANUARI, FEBRUARI, etc.)
                $firstCell = strtolower(trim($row[0] ?? ''));
                if (in_array($firstCell, $months)) {
                    continue;
                }
                
                // Get WIP as the primary identifier
                $wip = $this->getColumnValue($row, $headerMap, ['wip', 'no job', 'job number']);
                
                // Skip empty WIP or formula errors
                if (empty($wip) || str_contains($wip, '=INDEX') || str_contains($wip, '#REF')) {
                    continue;
                }
                
                // Log first 5 data rows for debugging
                if ($loggedRows < 5) {
                    \Log::info("BOOKING data row {$i}: wip={$wip}, raw row:", $row);
                    $loggedRows++;
                }

                $bookingData = [
                    'wip' => $wip,
                    'customer_name' => $this->getColumnValue($row, $headerMap, ['customer name', 'customer', 'nama']),
                    'booking_date' => $this->parseDate($this->getColumnValue($row, $headerMap, ['tgl booking', 'tgl masuk', 'tanggal', 'date', 'booking date'])),
                    'service_type' => $this->getColumnValue($row, $headerMap, ['type of work', 'tipe', 'type', 'service type', 'jenis']),
                    'foreman' => $this->getColumnValue($row, $headerMap, ['foreman', 'kepala regu']),
                    'service_advisor' => $this->getColumnValue($row, $headerMap, ['sa', 'service advisor']),
                    'notes' => $this->getColumnValue($row, $headerMap, ['remarks', 'notes', 'catatan', 'keterangan']),
                    'status' => 'pending',
                ];

                // Use WIP + booking_date as unique key
                $booking = \App\Models\Booking::updateOrCreate(
                    ['wip' => $wip, 'booking_date' => $bookingData['booking_date']],
                    array_filter($bookingData, fn($v) => !is_null($v))
                );

                $booking->wasRecentlyCreated ? $imported++ : $updated++;
            } catch (\Exception $e) {
                \Log::error("BOOKING import error at row {$i}: " . $e->getMessage());
                $failed++;
                if (count($failedRows) < 100) {
                    $failedRows[] = [
                        'row' => $i + 1,
                        'sheet' => 'BOOKING',
                        'job_number' => $wip ?? 'N/A',
                        'plate_number' => 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
        
        \Log::info("BOOKING import complete: imported={$imported}, updated={$updated}, failed={$failed}");
        return compact('imported', 'updated', 'failed', 'failedRows');
    }

    /**
     * Import PDI sheet
     * Actual headers: date, customer name, chassis no, engine no, type, colour, foreman, wip, status, remarks
     */
    private function importPdiSheet(array $rows): array
    {
        $imported = 0; $updated = 0; $failed = 0;
        $failedRows = [];
        
        // Debug: log first 5 rows
        \Log::info("PDI SHEET - First 5 rows:", array_slice($rows, 0, 5));
        
        // Find header row - look for 'chassis' or 'engine' or 'foreman' + 'wip'
        $headerMap = [];
        $dataStartIndex = 0;
        
        for ($i = 0; $i < min(10, count($rows)); $i++) {
            $rowString = strtolower(implode(' ', array_map(fn($v) => trim((string)$v), $rows[$i])));
            \Log::info("PDI row {$i}: {$rowString}");
            
            // Look for header row with 'chassis' or ('date' and 'customer' and 'type')
            if (str_contains($rowString, 'chassis') || str_contains($rowString, 'engine no') || 
                (str_contains($rowString, 'date') && str_contains($rowString, 'customer') && str_contains($rowString, 'type'))) {
                $headerMap = array_flip(array_map(fn($v) => strtolower(trim((string)$v)), $rows[$i]));
                $dataStartIndex = $i + 1;
                \Log::info("PDI - Found header at row {$i}, headerMap:", $headerMap);
                break;
            }
        }
        
        if (empty($headerMap)) {
            \Log::warning("PDI - No header found in first 10 rows");
            return compact('imported', 'updated', 'failed');
        }

        $loggedRows = 0;
        for ($i = $dataStartIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            try {
                // Get VIN/chassis no as the primary identifier
                $vin = $this->getColumnValue($row, $headerMap, ['chassis no', 'chassis', 'vin', 'no rangka', 'frame']);
                
                // Skip empty VIN
                if (empty($vin)) continue;
                
                // Log first 5 data rows for debugging
                if ($loggedRows < 5) {
                    \Log::info("PDI data row {$i}: vin={$vin}, raw row:", $row);
                    $loggedRows++;
                }

                $pdiData = [
                    'vin' => $vin,
                    'engine_no' => $this->getColumnValue($row, $headerMap, ['engine no', 'engine', 'no mesin']),
                    'wip' => $this->getColumnValue($row, $headerMap, ['wip', 'no job']),
                    'model' => $this->getColumnValue($row, $headerMap, ['type', 'model', 'tipe', 'unit']),
                    'colour' => $this->getColumnValue($row, $headerMap, ['colour', 'color', 'warna']),
                    'pdi_date' => $this->parseDate($this->getColumnValue($row, $headerMap, ['date', 'tanggal', 'tgl'])),
                    'technician' => $this->getColumnValue($row, $headerMap, ['foreman', 'technician', 'mekanik', 'teknisi']),
                    'notes' => $this->getColumnValue($row, $headerMap, ['remarks', 'notes', 'catatan', 'keterangan', 'status']),
                    'status' => 'pending',
                ];

                // Use VIN + pdi_date as unique key
                $pdi = \App\Models\PdiRecord::updateOrCreate(
                    ['vin' => $vin, 'pdi_date' => $pdiData['pdi_date']],
                    array_filter($pdiData, fn($v) => !is_null($v))
                );

                $pdi->wasRecentlyCreated ? $imported++ : $updated++;
            } catch (\Exception $e) {
                \Log::error("PDI import error at row {$i}: " . $e->getMessage());
                $failed++;
                if (count($failedRows) < 100) {
                    $failedRows[] = [
                        'row' => $i + 1,
                        'sheet' => 'PDI',
                        'job_number' => $vin ?? 'N/A',
                        'plate_number' => 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
        
        \Log::info("PDI import complete: imported={$imported}, updated={$updated}, failed={$failed}");
        return compact('imported', 'updated', 'failed', 'failedRows');
    }

    /**
     * Import Towing sheet (special format with month headers)
     */
    private function importTowingSheet(array $rows): array
    {
        $imported = 0; $updated = 0; $failed = 0;
        $failedRows = [];
        $headerMap = [];
        
        // Indonesian months to skip
        $months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 
                   'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];

        for ($i = 0; $i < count($rows); $i++) {
            $row = $rows[$i];
            $firstCell = strtolower(trim($row[0] ?? ''));
            
            // Skip title row or month headers
            if (empty($firstCell) || in_array($firstCell, $months) || str_contains($firstCell, 'jadwal')) {
                continue;
            }
            
            // Detect header row (contains TANGGAL and WIP)
            $rowString = strtolower(implode(' ', array_map('trim', $row)));
            if (str_contains($rowString, 'tanggal') && str_contains($rowString, 'wip')) {
                $headerMap = array_flip(array_map('strtolower', array_map('trim', $row)));
                continue;
            }
            
            // Skip if no header found yet
            if (empty($headerMap)) continue;
            
            // Skip if this is a repeat header (NURHALIM, TUTUT etc. in col A & B)
            if (str_contains($rowString, 'foreman') || str_contains($rowString, 'mechanic')) {
                continue;
            }

            try {
                $plateNumber = $this->getColumnValue($row, $headerMap, ['nopol', 'plate', 'reg no']);
                $scheduledDate = $this->parseDate($this->getColumnValue($row, $headerMap, ['tanggal', 'date']));
                
                if (empty($plateNumber) || empty($scheduledDate)) continue;

                $towingorStoring = strtolower(trim($this->getColumnValue($row, $headerMap, ['stooring / towing', 'stooring/towing', 'towing']) ?? 'towing'));
                $jobType = str_contains($towingorStoring, 'stooring') ? 'storing' : 'towing';

                $towingData = [
                    'plate_number' => $plateNumber,
                    'scheduled_date' => $scheduledDate,
                    'job_type' => $jobType,
                    'status' => 'scheduled',
                    'notes' => $this->getColumnValue($row, $headerMap, ['wip']), // Store WIP as reference
                ];

                $towing = \App\Models\TowingRecord::updateOrCreate(
                    ['plate_number' => $plateNumber, 'scheduled_date' => $scheduledDate],
                    array_filter($towingData, fn($v) => !is_null($v))
                );

                $towing->wasRecentlyCreated ? $imported++ : $updated++;
            } catch (\Exception $e) {
                $failed++;
                if (count($failedRows) < 100) {
                    $failedRows[] = [
                        'row' => $i + 1,
                        'sheet' => 'TOWING',
                        'job_number' => 'N/A',
                        'plate_number' => $plateNumber ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
        
        return compact('imported', 'updated', 'failed', 'failedRows');
    }
}
