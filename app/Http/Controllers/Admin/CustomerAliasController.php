<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAlias;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAliasController extends Controller
{
    /**
     * Show unmatched customer names
     */
    public function index(Request $request)
    {
        // Get distinct customer names from jobs that don't have a customer_id
        $unmatchedNames = DB::table('jobs')
            ->whereNull('customer_id')
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->select('customer_name', DB::raw('COUNT(*) as job_count'))
            ->groupBy('customer_name')
            ->orderBy('job_count', 'desc')
            ->paginate(50);

        // Get all customers for matching dropdown
        $customers = Customer::orderBy('name')->get(['id', 'name', 'dms_magic']);

        // Get existing aliases
        $aliases = CustomerAlias::with('customer')->orderBy('alias_name')->get();

        return view('admin.customer-aliases.index', compact('unmatchedNames', 'customers', 'aliases'));
    }

    /**
     * Create a new customer alias and link matching jobs
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'alias_name' => 'required|string|max:255|unique:customer_aliases,alias_name',
        ]);

        DB::transaction(function () use ($request) {
            // Create the alias
            CustomerAlias::create([
                'customer_id' => $request->customer_id,
                'alias_name' => $request->alias_name,
                'created_by' => auth()->id(),
            ]);

            // Update all jobs with this customer name
            $this->linkJobsByName($request->alias_name, $request->customer_id);
        });

        return redirect()->route('admin.customer-aliases.index')
            ->with('success', "Alias created and jobs linked successfully.");
    }

    /**
     * Link jobs by customer name directly (exact match to customer)
     */
    public function linkDirect(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'customer_name' => 'required|string',
        ]);

        $count = $this->linkJobsByName($request->customer_name, $request->customer_id);

        return redirect()->route('admin.customer-aliases.index')
            ->with('success', "{$count} jobs linked to customer.");
    }

    /**
     * Bulk link by pattern
     */
    public function bulkLink(Request $request)
    {
        $request->validate([
            'mappings' => 'required|array',
            'mappings.*.customer_name' => 'required|string',
            'mappings.*.customer_id' => 'required|exists:customers,id',
            'mappings.*.create_alias' => 'boolean',
        ]);

        $linked = 0;
        $aliasesCreated = 0;

        DB::transaction(function () use ($request, &$linked, &$aliasesCreated) {
            foreach ($request->mappings as $mapping) {
                if (!empty($mapping['customer_id'])) {
                    $linked += $this->linkJobsByName($mapping['customer_name'], $mapping['customer_id']);

                    // Optionally create alias
                    if (!empty($mapping['create_alias'])) {
                        $exists = CustomerAlias::where('alias_name', $mapping['customer_name'])->exists();
                        if (!$exists) {
                            CustomerAlias::create([
                                'customer_id' => $mapping['customer_id'],
                                'alias_name' => $mapping['customer_name'],
                                'created_by' => auth()->id(),
                            ]);
                            $aliasesCreated++;
                        }
                    }
                }
            }
        });

        return redirect()->route('admin.customer-aliases.index')
            ->with('success', "{$linked} jobs linked, {$aliasesCreated} aliases created.");
    }

    /**
     * Delete an alias
     */
    public function destroy(CustomerAlias $alias)
    {
        $alias->delete();
        return redirect()->route('admin.customer-aliases.index')
            ->with('success', 'Alias deleted.');
    }

    /**
     * Find similar customer names for suggestions
     */
    public function suggest(Request $request)
    {
        $name = strtoupper(trim($request->input('name', '')));
        
        if (strlen($name) < 2) {
            return response()->json([]);
        }

        // Find customers with similar names
        $customers = Customer::whereNotNull('name')
            ->where(function ($q) use ($name) {
                $q->whereRaw('UPPER(name) LIKE ?', ["%{$name}%"])
                  ->orWhereRaw('SOUNDEX(name) = SOUNDEX(?)', [$name]);
            })
            ->limit(10)
            ->get(['id', 'name', 'dms_magic']);

        return response()->json($customers);
    }

    /**
     * Link all jobs with a given customer name to a customer
     */
    protected function linkJobsByName(string $name, int $customerId): int
    {
        return Job::whereNull('customer_id')
            ->where('customer_name', $name)
            ->update(['customer_id' => $customerId]);
    }
}
