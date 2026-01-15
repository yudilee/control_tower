<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DataCleanupController extends Controller
{
    /**
     * All cleanable tables grouped by category
     */
    protected $tableGroups = [
        'Core Data' => [
            'remarks' => 'Remarks / Comments',
            'job_activities' => 'Job Activities',
            'job_invoices' => 'Job Invoices',
            'part_orders' => 'Part Orders',
            'jobs' => 'Jobs',
            'bookings' => 'Bookings',
            'pdi_records' => 'PDI Records',
            'towing_records' => 'Towing Records',
            'vehicles' => 'Vehicles',
            'customers' => 'Customers',
        ],
        'Customer Links' => [
            'customer_summaries' => 'Customer Summaries (Lookup Cache)',
            'customer_vehicles' => 'Customer-Vehicle Links',
            'customer_aliases' => 'Customer Aliases',
        ],
        'Merge & Duplicates' => [
            'duplicate_customer_groups' => 'Duplicate Customer Groups',
            'customer_merge_suggestions' => 'Customer Merge Suggestions',
            'customer_merge_logs' => 'Customer Merge Logs',
            'dismissed_duplicate_groups' => 'Dismissed Duplicate Groups',
        ],
        'User Data' => [
            'notifications' => 'Notifications',
            'announcements' => 'Announcements',
            'recently_viewed' => 'Recently Viewed Jobs',
            'user_dashboard_preferences' => 'Dashboard Preferences',
            'push_subscriptions' => 'Push Subscriptions',
            'user_sessions' => 'User Sessions',
            'saved_reports' => 'Saved Reports',
        ],
        'System Data' => [
            'imports' => 'Import History',
            'audit_logs' => 'Audit Logs',
            'audit_log_archives' => 'Audit Log Archives',
            'backup_logs' => 'Backup Logs',
            'scheduler_logs' => 'Scheduler Logs',
        ],
    ];

    /**
     * Show the data cleanup confirmation page
     */
    public function index()
    {
        $counts = [];
        $totalRecords = 0;
        
        foreach ($this->tableGroups as $group => $tables) {
            foreach ($tables as $table => $label) {
                try {
                    $count = DB::table($table)->count();
                    $counts[$table] = $count;
                    $totalRecords += $count;
                } catch (\Exception $e) {
                    $counts[$table] = 0; // Table might not exist
                }
            }
        }

        $tableGroups = $this->tableGroups;
        
        $serviceAdvisors = \App\Models\ServiceAdvisor::orderBy('name')->get();
        $foremen = \App\Models\Foreman::orderBy('name')->get();

        return view('admin.data-cleanup.index', compact('counts', 'totalRecords', 'tableGroups', 'serviceAdvisors', 'foremen'));
    }

    /**
     * Execute the data cleanup
     */
    public function cleanup(Request $request)
    {
        $request->validate([
            'confirmation' => 'required|in:DELETE ALL DATA',
            'tables' => 'required|array|min:1',
        ]);

        $tablesToClean = $request->input('tables', []);
        $results = [];

        try {
            // Disable foreign key checks temporarily
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Define proper order to avoid FK issues (child tables first)
            $cleanOrder = [
                // User data first (no FK dependencies)
                'recently_viewed',
                'push_subscriptions',
                'user_dashboard_preferences',
                'announcements',
                'notifications',
                'user_sessions',
                'saved_reports',
                // Job-related child tables
                'remarks',
                'job_activities',
                'job_invoices',
                'part_orders',
                // Customer merge/duplicate tables
                'duplicate_customer_groups',
                'customer_merge_suggestions',
                'customer_merge_logs',
                'dismissed_duplicate_groups',
                // Customer cache tables
                'customer_summaries',
                'customer_vehicles',
                'customer_aliases',
                // Core data tables
                'jobs',
                'bookings',
                'pdi_records',
                'towing_records',
                'vehicles',
                'customers',
                // System logs
                'imports',
                'audit_logs',
                'audit_log_archives',
                'backup_logs',
                'scheduler_logs',
            ];

            foreach ($cleanOrder as $table) {
                if (in_array($table, $tablesToClean)) {
                    try {
                        $count = DB::table($table)->count();
                        if ($count > 0) {
                            DB::table($table)->truncate();
                            $results[$table] = $count;
                        }
                    } catch (\Exception $e) {
                        // Table might not exist, skip silently
                        Log::warning("Could not clean table {$table}: " . $e->getMessage());
                    }
                }
            }

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            // Clean up storage if remarks were cleaned
            if (in_array('remarks', $results)) {
                try {
                    Storage::deleteDirectory('public/remarks');
                    Storage::makeDirectory('public/remarks'); // Recreate empty directory
                    Log::info('Deleted remarks images from storage.');
                } catch (\Exception $e) {
                    Log::warning('Failed to delete remarks images: ' . $e->getMessage());
                }
            }

            // Clear all application cache
            \Artisan::call('cache:clear');
            \Artisan::call('view:clear');

            // Log the cleanup action
            Log::info('Data cleanup executed by ' . auth()->user()->name, [
                'tables' => $tablesToClean,
                'records_deleted' => $results,
            ]);

            $totalDeleted = array_sum($results);
            return redirect()->route('admin.data-cleanup.index')
                ->with('success', "Data cleanup completed! Deleted {$totalDeleted} records from " . count($results) . " table(s). Cache cleared.");

        } catch (\Exception $e) {
            // Re-enable foreign key checks in case of error
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            Log::error('Data cleanup failed: ' . $e->getMessage());
            
            return redirect()->route('admin.data-cleanup.index')
                ->with('error', 'Data cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Reassign personnel (SA/Foreman) in jobs and master data
     */
    public function reassign(Request $request)
    {
        $request->validate([
            'type' => 'required|in:sa,foreman',
            'old_name' => 'required|string',
            'new_name' => 'required|string|different:old_name',
        ]);

        $type = $request->input('type');
        $oldName = trim($request->input('old_name'));
        $newName = trim($request->input('new_name'));
        
        $jobCount = 0;
        $msg = "";

        DB::transaction(function () use ($type, $oldName, $newName, &$jobCount, &$msg) {
            if ($type === 'sa') {
                // Update Jobs
                $jobCount = \App\Models\Job::where('service_advisor', $oldName)->update(['service_advisor' => $newName]);
                
                // Update Master Data
                $oldMaster = \App\Models\ServiceAdvisor::where('name', $oldName)->first();
                if ($oldMaster) {
                    $newMaster = \App\Models\ServiceAdvisor::where('name', $newName)->first();
                    if ($newMaster) {
                        $oldMaster->delete();
                        $msg = "Merged SA '{$oldName}' into '{$newName}'.";
                    } else {
                        $oldMaster->update(['name' => $newName]);
                        $msg = "Renamed SA '{$oldName}' to '{$newName}'.";
                    }
                } else {
                    $msg = "No SA master record found for '{$oldName}', but updated jobs.";
                }
            } else {
                // Update Jobs
                $jobCount = \App\Models\Job::where('foreman', $oldName)->update(['foreman' => $newName]);
                
                // Update Master Data
                $oldMaster = \App\Models\Foreman::where('name', $oldName)->first();
                if ($oldMaster) {
                    $newMaster = \App\Models\Foreman::where('name', $newName)->first();
                    if ($newMaster) {
                        $oldMaster->delete();
                        $msg = "Merged Foreman '{$oldName}' into '{$newName}'.";
                    } else {
                        $oldMaster->update(['name' => $newName]);
                        $msg = "Renamed Foreman '{$oldName}' to '{$newName}'.";
                    }
                } else {
                    $msg = "No Foreman master record found for '{$oldName}', but updated jobs.";
                }
            }
        });

        return redirect()->route('admin.data-cleanup.index')
            ->with('success', "Reassignment complete! Updated {$jobCount} jobs. {$msg}");
    }

    /**
     * Sanitize duplicate customer addresses
     */
    public function sanitizeAddresses(Request $request)
    {
        $dryRun = $request->has('dry_run');
        
        $customers = \App\Models\Customer::whereNotNull('address')
            ->orWhereNotNull('address_1')
            ->get();
        
        $updatedCount = 0;
        $sampleChanges = [];
        $addressFields = ['address', 'address_1', 'address_2', 'address_3', 'address_4', 'address_5'];
        
        foreach ($customers as $customer) {
            $updated = false;
            $customerChanges = [];
            
            // Check each address field for internal duplication
            foreach ($addressFields as $field) {
                $value = $customer->$field;
                if (empty($value)) continue;
                
                $cleaned = $this->deduplicateAddressString($value);
                
                if ($cleaned !== $value) {
                    $customerChanges[] = "{$field}: \"{$value}\" -> \"{$cleaned}\"";
                    if (!$dryRun) {
                        $customer->$field = $cleaned;
                        $updated = true;
                    }
                }
            }
            
            // If main address field is populated, check if address_1-5 are substrings
            $mainAddress = $customer->address ?? $customer->address_1 ?? '';
            if (!empty($mainAddress)) {
                $normalizedMain = strtolower($mainAddress);
                $partsInMain = array_map('trim', explode(',', $normalizedMain));
                
                foreach (['address_1', 'address_2', 'address_3', 'address_4', 'address_5'] as $field) {
                    if ($field === 'address_1' && empty($customer->address)) {
                        continue;
                    }
                    
                    $value = trim($customer->$field ?? '');
                    if (empty($value)) continue;
                    
                    $normalizedValue = strtolower($value);
                    
                    if (strpos($normalizedMain, $normalizedValue) !== false || in_array($normalizedValue, $partsInMain)) {
                        $customerChanges[] = "Cleared {$field} (duplicate of main address)";
                        if (!$dryRun) {
                            $customer->$field = null;
                            $updated = true;
                        }
                    }
                }
            }
            
            if ($updated && !$dryRun) {
                $customer->save();
                $updatedCount++;
                // Store sample of first 20 changes
                if (count($sampleChanges) < 20) {
                    $sampleChanges[] = [
                        'customer_id' => $customer->id,
                        'name' => $customer->name,
                        'changes' => $customerChanges,
                    ];
                }
            } elseif (!empty($customerChanges)) {
                $updatedCount++;
                if (count($sampleChanges) < 20) {
                    $sampleChanges[] = [
                        'customer_id' => $customer->id,
                        'name' => $customer->name,
                        'changes' => $customerChanges,
                    ];
                }
            }
        }
        
        if ($dryRun) {
            return redirect()->route('admin.data-cleanup.index')
                ->with('info', "DRY RUN: Would sanitize {$updatedCount} customer addresses. No changes made.")
                ->with('sample_changes', $sampleChanges);
        }
        
        // Log the sanitization
        \App\Models\DataSanitizeLog::create([
            'type' => 'customer_address',
            'records_affected' => $updatedCount,
            'details' => $sampleChanges,
            'run_by' => auth()->user()->name ?? 'System',
        ]);
        
        return redirect()->route('admin.data-cleanup.index')
            ->with('success', "Address sanitization complete! Cleaned {$updatedCount} customer records.");
    }

    /**
     * Show sanitize history
     */
    public function sanitizeHistory()
    {
        $logs = \App\Models\DataSanitizeLog::orderByDesc('created_at')->paginate(20);
        return view('admin.data-cleanup.sanitize-history', compact('logs'));
    }

    /**
     * Helper: Deduplicate address string
     */
    private function deduplicateAddressString(string $address): string
    {
        $parts = array_map('trim', explode(',', $address));
        
        $count = count($parts);
        if ($count >= 2 && $count % 2 === 0) {
            $half = $count / 2;
            $firstHalf = array_slice($parts, 0, $half);
            $secondHalf = array_slice($parts, $half);
            
            $match = true;
            for ($i = 0; $i < $half; $i++) {
                if (strtolower($firstHalf[$i]) !== strtolower($secondHalf[$i])) {
                    $match = false;
                    break;
                }
            }
            
            if ($match) {
                return implode(', ', $firstHalf);
            }
        }
        
        $uniqueParts = [];
        $seenNormalized = [];
        
        foreach ($parts as $part) {
            $normalized = strtolower(trim($part));
            if (!isset($seenNormalized[$normalized])) {
                $seenNormalized[$normalized] = true;
                $uniqueParts[] = $part;
            }
        }
        
        return implode(', ', $uniqueParts);
    }
}
