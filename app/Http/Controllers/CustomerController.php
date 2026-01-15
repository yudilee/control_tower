<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Job;
use App\Models\CustomerMergeLog;
use App\Models\CustomerSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Display a listing of unique customers - uses cached summary table for speed.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $filter = $request->input('filter');
        $sortField = $request->input('sort', 'name');
        $sortDir = $request->input('dir', 'asc');
        $perPage = $request->input('per_page', 20);

        // Use cached summary table for instant loading
        $query = CustomerSummary::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('dms_magic', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Apply filters
        if ($filter === 'with_uninvoiced') {
            $query->where('uninvoiced_count', '>', 0);
        } elseif ($filter === 'with_sales') {
            $query->where('total_sales', '>', 0);
        } elseif ($filter === 'multi_vehicle') {
            $query->where('vehicle_count', '>=', 2);
        } elseif ($filter === 'dms_linked') {
            $query->whereNotNull('customer_id');
        } elseif ($filter === 'not_linked') {
            $query->whereNull('customer_id');
        }

        // Map sort field
        $dbSortField = match($sortField) {
            'sales_amount' => 'total_sales',
            default => $sortField,
        };

        $customers = $query
            ->orderBy($dbSortField, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        // Convert to expected format with DMS fields
        $customerData = $customers->map(function ($c) {
            return (object)[
                'name' => $c->name,
                'customer_id' => $c->customer_id,
                'dms_magic' => $c->dms_magic,
                'email' => $c->email,
                'phone' => $c->phone,
                'company_name' => $c->company_name,
                'vehicle_count' => $c->vehicle_count,
                'job_count' => $c->job_count,
                'uninvoiced_count' => $c->uninvoiced_count,
                'sales_amount' => $c->total_sales,
                'estimated_sales' => $c->estimated_sales,
                'is_dms_linked' => $c->customer_id !== null,
            ];
        })->toArray();

        // Stats
        $dmsLinkedCount = CustomerSummary::whereNotNull('customer_id')->count();

        return view('customers.index', [
            'customers' => $customers,
            'customerData' => $customerData,
            'search' => $search,
            'sortField' => $sortField,
            'sortDir' => $sortDir,
            'totalCustomers' => CustomerSummary::count(),
            'dmsLinkedCount' => $dmsLinkedCount,
        ]);
    }

    /**
     * Display customer detail with related vehicles and jobs.
     */
    public function show(Request $request)
    {
        $customerName = $request->input('name');
        
        if (empty($customerName)) {
            return redirect()->route('customers.index')->with('error', 'Customer name is required');
        }

        // Try to find linked DMS customer
        $summary = CustomerSummary::where('name', $customerName)->first();
        $dmsCustomer = null;
        
        if ($summary && $summary->customer_id) {
            $dmsCustomer = \App\Models\Customer::find($summary->customer_id);
        } else {
            // Try direct name match
            $dmsCustomer = \App\Models\Customer::whereRaw('UPPER(name) = ?', [strtoupper($customerName)])->first();
            
            // Try alias match
            if (!$dmsCustomer) {
                $alias = \App\Models\CustomerAlias::whereRaw('UPPER(alias_name) = ?', [strtoupper($customerName)])->first();
                if ($alias) {
                    $dmsCustomer = $alias->customer;
                }
            }
            
            // Try via vehicle's customer_id link (fallback when customer_name is truncated)
            if (!$dmsCustomer) {
                $vehicle = Vehicle::where('customer_name', $customerName)
                    ->whereNotNull('customer_id')
                    ->first();
                if ($vehicle && $vehicle->customer_id) {
                    $dmsCustomer = \App\Models\Customer::find($vehicle->customer_id);
                }
            }
        }

        // Build query filter - if DMS linked, query by customer_id to get ALL name variations
        // This matches the aggregation logic in RefreshCustomerSummaries command
        if ($dmsCustomer) {
            // Get related vehicles via customer_id (captures all name variations)
            $vehicles = Vehicle::where('customer_id', $dmsCustomer->id)
                ->withCount('jobs')
                ->orderBy('plate_number')
                ->get();

            // Get related jobs via customer_id (captures all name variations)
            $jobs = Job::where('customer_id', $dmsCustomer->id)
                ->orderBy('job_date', 'desc')
                ->paginate(20)
                ->withQueryString();

            // Summary stats via customer_id
            $stats = [
                'total_vehicles' => $vehicles->count(),
                'total_jobs' => Job::where('customer_id', $dmsCustomer->id)->count(),
                'uninvoiced_jobs' => Job::where('customer_id', $dmsCustomer->id)->where('status', 'uninvoiced')->count(),
                'invoiced_jobs' => Job::where('customer_id', $dmsCustomer->id)->where('status', 'invoiced')->count(),
                'total_sales' => Job::where('customer_id', $dmsCustomer->id)->where('status', 'invoiced')->sum('inv_ppn_meterai') ?? 0,
                'estimated_sales' => Job::where('customer_id', $dmsCustomer->id)->where('status', 'uninvoiced')->sum('total_sales') ?? 0,
            ];
        } else {
            // Fallback to name-based query for unlinked customers
            $vehicles = Vehicle::where('customer_name', $customerName)
                ->withCount('jobs')
                ->orderBy('plate_number')
                ->get();

            $jobs = Job::where('customer_name', $customerName)
                ->orderBy('job_date', 'desc')
                ->paginate(20)
                ->withQueryString();

            $stats = [
                'total_vehicles' => $vehicles->count(),
                'total_jobs' => Job::where('customer_name', $customerName)->count(),
                'uninvoiced_jobs' => Job::where('customer_name', $customerName)->where('status', 'uninvoiced')->count(),
                'invoiced_jobs' => Job::where('customer_name', $customerName)->where('status', 'invoiced')->count(),
                'total_sales' => Job::where('customer_name', $customerName)->where('status', 'invoiced')->sum('inv_ppn_meterai') ?? 0,
                'estimated_sales' => Job::where('customer_name', $customerName)->where('status', 'uninvoiced')->sum('total_sales') ?? 0,
            ];
        }

        return view('customers.show', [
            'customerName' => $customerName,
            'dmsCustomer' => $dmsCustomer,
            'vehicles' => $vehicles,
            'jobs' => $jobs,
            'stats' => $stats,
        ]);
    }

    /**
     * Search customers by name (AJAX autocomplete).
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = DB::table(
            DB::raw("(
                SELECT DISTINCT customer_name as name FROM vehicles WHERE customer_name IS NOT NULL AND customer_name != ''
                UNION
                SELECT DISTINCT customer_name as name FROM jobs WHERE customer_name IS NOT NULL AND customer_name != ''
            ) as customers")
        )
        ->where('name', 'like', "%{$query}%")
        ->orderBy('name')
        ->limit(15)
        ->pluck('name');

        return response()->json($results);
    }

    /**
     * Find and show duplicate/similar customer names - loads from pre-calculated table
     */
    public function duplicates()
    {
        // Load from cached table (instant loading!)
        $groups = \App\Models\DuplicateCustomerGroup::pending()
            ->orderByDesc('dms_count')
            ->orderByDesc('user_count')
            ->get();

        // Convert to format expected by view
        $duplicateGroups = $groups->map(function ($group) {
            return [
                'id' => $group->id,
                'names' => $group->names,
                'entries' => $group->entries,
                'classification' => $group->classification,
                'dms_count' => $group->dms_count,
                'user_count' => $group->user_count,
            ];
        })->toArray();

        return view('customers.duplicates', [
            'duplicateGroups' => $duplicateGroups,
            'totalGroups' => count($duplicateGroups),
            'dmsIssueCount' => collect($duplicateGroups)->where('classification', 'DMS_ISSUE')->count(),
            'userMistakeCount' => collect($duplicateGroups)->where('classification', 'USER_MISTAKE')->count(),
        ]);
    }

    /**
     * Dismiss a duplicate group (mark as reviewed/not duplicate)
     */
    public function dismissGroup(Request $request)
    {
        $request->validate([
            'names' => 'required|array|min:2',
        ]);

        $names = $request->input('names');
        
        // Update cached table
        $hash = \App\Models\DuplicateCustomerGroup::generateHash($names);
        \App\Models\DuplicateCustomerGroup::where('group_hash', $hash)
            ->update(['status' => 'dismissed']);
        
        // Also save to old dismissed table for backward compatibility
        \App\Models\DismissedDuplicateGroup::dismiss(
            $names, 
            'not_duplicate',
            auth()->user()?->name
        );

        return redirect()->route('customers.duplicates')
            ->with('success', 'Group dismissed. It will not appear in future reviews.');
    }

    /**
     * Merge selected customer names into canonical name
     */
    public function merge(Request $request)
    {
        $request->validate([
            'names_to_merge' => 'required|array|min:1',
            'canonical_name' => 'required|string|min:1',
        ]);

        $namesToMerge = $request->input('names_to_merge');
        $canonicalName = trim($request->input('canonical_name'));

        $totalJobsUpdated = 0;
        $totalVehiclesUpdated = 0;

        foreach ($namesToMerge as $oldName) {
            if ($oldName === $canonicalName) {
                continue; // Skip if same as canonical
            }

            // Detect source type by checking linked imports
            $sourceType = $this->detectDuplicateSource($oldName);

            // Update jobs individually for proper audit logging
            $jobs = Job::where('customer_name', $oldName)->get();
            $jobsCount = $jobs->count();
            foreach ($jobs as $job) {
                $job->update(['customer_name' => $canonicalName]);
            }

            // Update vehicles individually for proper audit logging
            $vehicles = Vehicle::where('customer_name', $oldName)->get();
            $vehiclesCount = $vehicles->count();
            foreach ($vehicles as $vehicle) {
                $vehicle->update(['customer_name' => $canonicalName]);
            }

            // Log the merge operation
            CustomerMergeLog::create([
                'old_name' => $oldName,
                'canonical_name' => $canonicalName,
                'source_type' => $sourceType,
                'jobs_updated' => $jobsCount,
                'vehicles_updated' => $vehiclesCount,
                'merged_by' => auth()->user()?->name ?? 'System',
                'notes' => "Merged from duplicate detection. Source: {$sourceType}",
            ]);

            $totalJobsUpdated += $jobsCount;
            $totalVehiclesUpdated += $vehiclesCount;
        }

        // Update cached table - mark as merged
        $hash = \App\Models\DuplicateCustomerGroup::generateHash($namesToMerge);
        \App\Models\DuplicateCustomerGroup::where('group_hash', $hash)
            ->update(['status' => 'merged']);

        return redirect()->route('customers.duplicates')
            ->with('success', "Merged successfully! Updated {$totalJobsUpdated} jobs and {$totalVehiclesUpdated} vehicles to '{$canonicalName}'. Changes have been logged for reporting.");
    }

    /**
     * Merge multiple groups of customer names in batch
     */
    public function mergeBatch(Request $request)
    {
        $request->validate([
            'groups' => 'required|array|min:1',
        ]);

        $groups = $request->input('groups', []);
        $totalJobsUpdated = 0;
        $totalVehiclesUpdated = 0;
        $groupsMerged = 0;

        foreach ($groups as $groupData) {
            $namesToMerge = $groupData['names'] ?? [];
            $canonicalName = trim($groupData['canonical'] ?? '');

            if (empty($namesToMerge) || empty($canonicalName)) {
                continue; // Skip incomplete groups
            }

            foreach ($namesToMerge as $oldName) {
                if ($oldName === $canonicalName) {
                    continue;
                }

                // Detect source type
                $sourceType = $this->detectDuplicateSource($oldName);

                // Update jobs individually for proper audit logging
                $jobs = Job::where('customer_name', $oldName)->get();
                $jobsCount = $jobs->count();
                foreach ($jobs as $job) {
                    $job->update(['customer_name' => $canonicalName]);
                }

                // Update vehicles individually for proper audit logging
                $vehicles = Vehicle::where('customer_name', $oldName)->get();
                $vehiclesCount = $vehicles->count();
                foreach ($vehicles as $vehicle) {
                    $vehicle->update(['customer_name' => $canonicalName]);
                }

                // Log the merge
                CustomerMergeLog::create([
                    'old_name' => $oldName,
                    'canonical_name' => $canonicalName,
                    'source_type' => $sourceType,
                    'jobs_updated' => $jobsCount,
                    'vehicles_updated' => $vehiclesCount,
                    'merged_by' => auth()->user()?->name ?? 'System',
                    'notes' => "Batch merge from duplicate detection. Source: {$sourceType}",
                ]);

                $totalJobsUpdated += $jobsCount;
                $totalVehiclesUpdated += $vehiclesCount;
            }

            $groupsMerged++;
        }

        return redirect()->route('customers.duplicates')
            ->with('success', "Batch merge complete! Processed {$groupsMerged} groups, updated {$totalJobsUpdated} jobs and {$totalVehiclesUpdated} vehicles. All changes have been logged.");
    }

    /**
     * Detect the source of duplicate customer name
     */
    private function detectDuplicateSource(string $customerName): string
    {
        // Check if jobs with this customer came from invoiced/uninvoiced imports (DMS)
        $dmsImportTypes = ['invoiced', 'uninvoiced'];
        $hasDmsJob = Job::where('customer_name', $customerName)
            ->whereHas('import', function($q) use ($dmsImportTypes) {
                $q->whereIn('import_type', $dmsImportTypes);
            })->exists();

        if ($hasDmsJob) {
            return 'dms_import'; // Need to fix in main DMS system
        }

        // Check if from job progress import
        $hasProgressJob = Job::where('customer_name', $customerName)
            ->whereHas('import', function($q) {
                $q->where('import_type', 'progress');
            })->exists();

        if ($hasProgressJob) {
            return 'job_progress_import'; // User mistake during progress import
        }

        // Check vehicle imports
        $hasVehicleImport = Vehicle::where('customer_name', $customerName)
            ->whereNotNull('import_id')
            ->exists();

        if ($hasVehicleImport) {
            return 'vehicle_import';
        }

        // Check if records exist but no import links (legacy or early data)
        $hasJobsWithoutImport = Job::where('customer_name', $customerName)
            ->whereNull('import_id')
            ->exists();
        $hasVehiclesWithoutImport = Vehicle::where('customer_name', $customerName)
            ->whereNull('import_id')
            ->exists();

        if ($hasJobsWithoutImport || $hasVehiclesWithoutImport) {
            return 'legacy_data'; // Records without import links (early system data)
        }

        // Manual entry or unknown
        return 'user_entry';
    }

    /**
     * Get human-readable label for source type
     */
    private function getSourceLabel(string $source): string
    {
        return match($source) {
            'dms_import' => 'DMS (Invoice/Uninvoiced)',
            'job_progress_import' => 'Job Progress Import',
            'vehicle_import' => 'Vehicle Import',
            'legacy_data' => 'Untracked (Conflict Fix/Early Import)',
            'user_entry' => 'Unknown Source',
            default => $source,
        };
    }

    /**
     * Normalize name for comparison (remove common variations)
     */
    private function normalizeForComparison(string $name): string
    {
        $normalized = strtoupper(trim($name));
        
        // Remove common suffixes/prefixes that vary
        $patterns = [
            '/\bPT\.?\s*/' => 'PT ',
            '/\bCV\.?\s*/' => 'CV ',
            '/\bMR\.?\s*/' => 'MR ',
            '/\bMRS\.?\s*/' => 'MRS ',
            '/\bMS\.?\s*/' => 'MS ',
            '/,\s*/' => ' ',
            '/\s+/' => ' ',
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized);
        }
        
        return trim($normalized);
    }
}
