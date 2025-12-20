<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataCleanupController extends Controller
{
    /**
     * All cleanable tables grouped by category
     */
    protected $tableGroups = [
        'Core Data' => [
            'remarks' => 'Remarks',
            'job_invoices' => 'Job Invoices',
            'jobs' => 'Jobs',
            'bookings' => 'Bookings',
            'pdi_records' => 'PDI Records',
            'towing_records' => 'Towing Records',
            'vehicles' => 'Vehicles',
        ],
        'Customer Portal' => [
            'customer_vehicles' => 'Customer-Vehicle Links',
            'customers' => 'Customers (Portal)',
        ],
        'Merge & Duplicates' => [
            'customer_merge_logs' => 'Customer Merge Logs',
            'dismissed_duplicate_groups' => 'Dismissed Duplicate Groups',
        ],
        'System Data' => [
            'notifications' => 'Notifications',
            'user_sessions' => 'User Sessions',
            'saved_reports' => 'Saved Reports',
            'imports' => 'Imports',
            'audit_logs' => 'Audit Logs',
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
        
        return view('admin.data-cleanup.index', compact('counts', 'totalRecords', 'tableGroups'));
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
                'remarks',
                'job_invoices',
                'customer_merge_logs',
                'dismissed_duplicate_groups',
                'notifications',
                'user_sessions',
                'saved_reports',
                'customer_vehicles',
                'customers',
                'jobs',
                'bookings',
                'pdi_records',
                'towing_records',
                'vehicles',
                'imports',
                'audit_logs',
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

            // Log the cleanup action
            Log::info('Data cleanup executed by ' . auth()->user()->name, [
                'tables' => $tablesToClean,
                'records_deleted' => $results,
            ]);

            $totalDeleted = array_sum($results);
            return redirect()->route('admin.data-cleanup.index')
                ->with('success', "Data cleanup completed! Deleted {$totalDeleted} records from " . count($results) . " table(s).");

        } catch (\Exception $e) {
            // Re-enable foreign key checks in case of error
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            Log::error('Data cleanup failed: ' . $e->getMessage());
            
            return redirect()->route('admin.data-cleanup.index')
                ->with('error', 'Data cleanup failed: ' . $e->getMessage());
        }
    }
}
