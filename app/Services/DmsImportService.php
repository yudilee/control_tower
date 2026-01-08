<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\AuditLog;
use App\Models\Import;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DmsImportService
{
    protected int $created = 0;
    protected int $updated = 0;
    protected int $errors = 0;
    protected array $errorMessages = [];
    protected ?string $fileName = null;

    /**
     * Import customers from Excel file
     */
    public function importCustomers(string $filePath, ?string $fileName = null): array
    {
        $this->resetCounters();
        $this->fileName = $fileName ?? basename($filePath);
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            
            // Get headers from first row
            $headers = array_shift($rows);
            $headerMap = $this->mapCustomerHeaders($headers);
            
            DB::beginTransaction();
            
            foreach ($rows as $rowIndex => $row) {
                try {
                    $this->processCustomerRow($row, $headerMap);
                } catch (\Exception $e) {
                    $this->errors++;
                    $this->errorMessages[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                    Log::warning("DMS Customer Import Row Error", [
                        'row' => $rowIndex + 2,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Create import history record
            $import = $this->createImportRecord('dms_customers');
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        
        return $this->getResults('customers', $import ?? null);
    }


    /**
     * Import vehicles from Excel file
     */
    public function importVehicles(string $filePath, ?string $fileName = null): array
    {
        $this->resetCounters();
        $this->fileName = $fileName ?? basename($filePath);
        
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            
            // Get headers from first row
            $headers = array_shift($rows);
            $headerMap = $this->mapVehicleHeaders($headers);
            
            DB::beginTransaction();
            
            foreach ($rows as $rowIndex => $row) {
                try {
                    $this->processVehicleRow($row, $headerMap);
                } catch (\Exception $e) {
                    $this->errors++;
                    $this->errorMessages[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                    Log::warning("DMS Vehicle Import Row Error", [
                        'row' => $rowIndex + 2,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Create import history record
            $import = $this->createImportRecord('dms_vehicles');
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        
        return $this->getResults('vehicles', $import ?? null);
    }

    /**
     * Map customer Excel headers to database columns
     */
    protected function mapCustomerHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $col => $header) {
            $header = trim($header ?? '');
            $map[strtolower($header)] = $col;
        }
        return $map;
    }

    /**
     * Map vehicle Excel headers to database columns
     */
    protected function mapVehicleHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $col => $header) {
            $header = trim($header ?? '');
            $map[strtolower($header)] = $col;
        }
        return $map;
    }

    /**
     * Process a single customer row
     */
    protected function processCustomerRow(array $row, array $map): void
    {
        $dmsMagic = $this->getValue($row, $map, 'magic cust');
        if (!$dmsMagic) {
            return; // Skip rows without magic ID
        }

        $data = [
            'dms_magic' => (string) $dmsMagic,
            'name' => $this->getValue($row, $map, ' nama customer') ?: $this->getValue($row, $map, 'nama customer'),
            'address_1' => $this->getValue($row, $map, 'address 1'),
            'address_2' => $this->getValue($row, $map, 'address 2'),
            'address_3' => $this->getValue($row, $map, 'address 3'),
            'address_4' => $this->getValue($row, $map, 'address 4'),
            'address_5' => $this->getValue($row, $map, 'address 5'),
            'company_name' => $this->getValue($row, $map, 'company name'),
            'email' => $this->cleanEmail($this->getValue($row, $map, 'e-mail address')),
            'department' => $this->getValue($row, $map, 'dept'),
            'dms_created_at' => $this->parseDate($this->getValue($row, $map, 'date created')),
            'dms_imported_at' => now(),
        ];

        // Build address from parts
        $addressParts = array_filter([
            $data['address_1'], $data['address_2'], $data['address_3'], 
            $data['address_4'], $data['address_5']
        ]);
        if (!empty($addressParts)) {
            $data['address'] = implode(', ', $addressParts);
        }

        // Filter out null values for update
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');

        $existing = Customer::where('dms_magic', (string) $dmsMagic)->first();
        
        if ($existing) {
            $existing->update($data);
            $this->updated++;
        } else {
            Customer::create($data);
            $this->created++;
        }
    }

    /**
     * Clean email - remove placeholder values
     */
    protected function cleanEmail(?string $email): ?string
    {
        if (!$email) return null;
        
        $email = trim($email);
        
        // List of invalid placeholder emails
        $invalidEmails = ['*', '-', 'c/s', 'n/a', 'na', 'none', 'null', '.', '..', '@', '-@-', '*@*'];
        
        if (in_array(strtolower($email), $invalidEmails)) {
            return null;
        }
        
        // Must contain @ to be a valid email
        if (!str_contains($email, '@')) {
            return null;
        }
        
        return $email;
    }

    /**
     * Process a single vehicle row with audit trail
     */
    protected function processVehicleRow(array $row, array $map): void
    {
        $dmsMagic = $this->getValue($row, $map, 'magic');
        $plateNumber = $this->getValue($row, $map, 'registration no');
        
        if (!$dmsMagic && !$plateNumber) {
            return; // Skip rows without identifier
        }

        $customerDmsMagic = $this->getValue($row, $map, 'customer magic');

        $data = [
            'dms_magic' => $dmsMagic ? (string) $dmsMagic : null,
            'plate_number' => $plateNumber,
            'model' => $this->getValue($row, $map, 'model'),
            'franchise' => $this->getValue($row, $map, 'franc'),
            'variant' => $this->getValue($row, $map, 'variant'),
            'description' => $this->getValue($row, $map, 'description'),
            'vin' => $this->getValue($row, $map, 'chassis no'),
            'mhl_number' => $this->getValue($row, $map, 'mhl number'),
            'engine_number' => $this->getValue($row, $map, 'engine no') ? (string) $this->getValue($row, $map, 'engine no') : null,
            'customer_dms_magic' => $customerDmsMagic ? (string) $customerDmsMagic : null,
            'registration_date' => $this->parseDate($this->getValue($row, $map, 'reg. date')),
            'last_service_date' => $this->parseDate($this->getValue($row, $map, 'last service date')),
            'dms_imported_at' => now(),
        ];

        // Get customer name from linked customer or from row
        $customerName = $this->getValue($row, $map, 'surname');
        if ($customerName) {
            $data['customer_name'] = $customerName;
        }

        // Filter out null values for update
        $updateData = array_filter($data, fn($v) => $v !== null && $v !== '');

        // Find existing vehicle by dms_magic or plate number
        $existing = null;
        if ($dmsMagic) {
            $existing = Vehicle::where('dms_magic', (string) $dmsMagic)->first();
        }
        if (!$existing && $plateNumber) {
            $existing = Vehicle::where('plate_number', $plateNumber)->first();
        }

        if ($existing) {
            // IMPORTANT: Preserve is_in_workshop status on updates
            unset($updateData['is_in_workshop']);
            
            // Store old values for audit
            $oldValues = $existing->toArray();
            
            $existing->update($updateData);
            
            // Audit log for changes
            $this->auditVehicleUpdate($existing, $oldValues, $updateData);
            $this->updated++;
        } else {
            // New vehicle: set is_in_workshop = false
            $data['is_in_workshop'] = false;
            
            $vehicle = Vehicle::create($data);
            
            // Audit log for creation
            $this->auditVehicleCreate($vehicle);
            $this->created++;
        }

        // Update customer phone numbers if we have customer_dms_magic
        if ($customerDmsMagic) {
            $this->updateCustomerPhones($customerDmsMagic, $row, $map);
        }
    }

    /**
     * Update customer phone numbers from vehicle import
     */
    protected function updateCustomerPhones(string $customerDmsMagic, array $row, array $map): void
    {
        $customer = Customer::where('dms_magic', $customerDmsMagic)->first();
        if (!$customer) {
            return;
        }

        $phoneData = [];
        $phone1 = $this->getValue($row, $map, 'phone1');
        $phone2 = $this->getValue($row, $map, 'phone2');
        $phone3 = $this->getValue($row, $map, 'phone3');
        $phone4 = $this->getValue($row, $map, 'phone4');

        if ($phone1) $phoneData['phone_1'] = $this->cleanPhone($phone1);
        if ($phone2) $phoneData['phone_2'] = $this->cleanPhone($phone2);
        if ($phone3) $phoneData['phone_3'] = $this->cleanPhone($phone3);
        if ($phone4) $phoneData['phone_4'] = $this->cleanPhone($phone4);

        // Update main phone if not set
        if (!$customer->phone && !empty($phoneData['phone_1'])) {
            $phoneData['phone'] = $phoneData['phone_1'];
        }

        if (!empty($phoneData)) {
            $customer->update($phoneData);
        }
    }

    /**
     * Clean phone number
     */
    protected function cleanPhone($phone): ?string
    {
        if (!$phone) return null;
        $phone = (string) $phone;
        // Remove scientific notation if present
        if (strpos($phone, 'E') !== false || strpos($phone, 'e') !== false) {
            $phone = number_format((float) $phone, 0, '', '');
        }
        return $phone;
    }

    /**
     * Create audit log for vehicle creation
     */
    protected function auditVehicleCreate(Vehicle $vehicle): void
    {
        AuditLog::create([
            'auditable_type' => Vehicle::class,
            'auditable_id' => $vehicle->id,
            'action' => 'created',
            'new_values' => $vehicle->toArray(),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Create audit log for vehicle update
     */
    protected function auditVehicleUpdate(Vehicle $vehicle, array $oldValues, array $newValues): void
    {
        // Only log if there are actual changes
        $changes = [];
        foreach ($newValues as $key => $value) {
            if (isset($oldValues[$key]) && $oldValues[$key] != $value) {
                $changes[$key] = ['old' => $oldValues[$key], 'new' => $value];
            }
        }

        if (empty($changes)) {
            return;
        }

        AuditLog::create([
            'auditable_type' => Vehicle::class,
            'auditable_id' => $vehicle->id,
            'action' => 'updated',
            'old_values' => array_column($changes, 'old'),
            'new_values' => array_column($changes, 'new'),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Get value from row using header map
     */
    protected function getValue(array $row, array $map, string $header): mixed
    {
        $header = strtolower($header);
        if (!isset($map[$header])) {
            return null;
        }
        $value = $row[$map[$header]] ?? null;
        if ($value === '' || $value === null) {
            return null;
        }
        if (is_string($value)) {
            $value = trim($value);
        }
        return $value;
    }

    /**
     * Parse date from various formats
     */
    protected function parseDate(?string $date): ?string
    {
        if (!$date || $date === '/ /' || $date === '/  /') {
            return null;
        }
        
        try {
            // Try d/m/Y format first
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $date, $matches)) {
                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                $year = strlen($matches[3]) == 2 ? '20' . $matches[3] : $matches[3];
                return "{$year}-{$month}-{$day}";
            }
            
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Reset counters
     */
    protected function resetCounters(): void
    {
        $this->created = 0;
        $this->updated = 0;
        $this->errors = 0;
        $this->errorMessages = [];
    }

    /**
     * Create import history record
     */
    protected function createImportRecord(string $importType): Import
    {
        return Import::create([
            'file_name' => $this->fileName,
            'import_type' => $importType,
            'records_imported' => $this->created,
            'records_updated' => $this->updated,
            'records_failed' => $this->errors,
            'failed_rows' => array_slice($this->errorMessages, 0, 100), // Limit to 100 errors
            'imported_by' => auth()->id(),
        ]);
    }

    /**
     * Get import results
     */
    protected function getResults(string $type, ?Import $import = null): array
    {
        return [
            'type' => $type,
            'created' => $this->created,
            'updated' => $this->updated,
            'errors' => $this->errors,
            'error_messages' => $this->errorMessages,
            'total' => $this->created + $this->updated,
            'import_id' => $import?->id,
        ];
    }
}

