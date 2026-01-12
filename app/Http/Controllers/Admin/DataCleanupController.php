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
}
