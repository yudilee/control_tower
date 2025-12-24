<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $query = Vehicle::withCount('jobs');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('plate_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%");
            });
        }

        if ($request->filled('in_workshop')) {
            $val = $request->in_workshop;
            $query->where('is_in_workshop', in_array($val, ['yes', '1', 'true'], true) || $val === true);
        }

        // Filter by latest job status (invoiced/uninvoiced)
        if ($request->filled('job_status')) {
            $status = $request->job_status;
            
            // Subquery to get the latest job date for each vehicle's plate
            $latestJobSubquery = \App\Models\Job::selectRaw('plate_number, MAX(job_date) as latest_date')
                ->groupBy('plate_number');
            
            if ($status === 'invoiced') {
                // Vehicles where the latest job is invoiced
                $query->whereExists(function ($q) {
                    $q->from('jobs as j1')
                      ->whereColumn('j1.plate_number', 'vehicles.plate_number')
                      ->where('j1.status', 'invoiced')
                      ->whereRaw('j1.job_date = (SELECT MAX(j2.job_date) FROM jobs j2 WHERE j2.plate_number = vehicles.plate_number)');
                });
            } elseif ($status === 'uninvoiced') {
                // Vehicles where the latest job is uninvoiced
                $query->whereExists(function ($q) {
                    $q->from('jobs as j1')
                      ->whereColumn('j1.plate_number', 'vehicles.plate_number')
                      ->where('j1.status', 'uninvoiced')
                      ->whereRaw('j1.job_date = (SELECT MAX(j2.job_date) FROM jobs j2 WHERE j2.plate_number = vehicles.plate_number)');
                });
            } elseif ($status === 'no_jobs') {
                // Vehicles with no jobs at all
                $query->whereDoesntHave('jobs');
            }
        }

        // Sorting
        $sortField = $request->input('sort', 'created_at');
        $sortDir = $request->input('dir', 'desc');
        $allowedSorts = ['plate_number', 'model', 'year', 'customer_name', 'is_in_workshop', 'created_at', 'jobs_count'];
        
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $perPage = $request->input('per_page', 20);
        $vehicles = $query->paginate($perPage)->withQueryString();

        return view('vehicles.index', compact('vehicles'));
    }

    public function create()
    {
        return view('vehicles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|unique:vehicles,plate_number',
            'model' => 'nullable|string',
            'year' => 'nullable|string',
            'vin' => 'nullable|string',
            'customer_name' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'is_in_workshop' => 'boolean',
        ]);

        $validated['is_in_workshop'] = $request->boolean('is_in_workshop', true);

        Vehicle::create($validated);

        return redirect()->route('vehicles.index')
            ->with('success', 'Vehicle added successfully.');
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load('jobs');
        return view('vehicles.show', compact('vehicle'));
    }

    public function edit(Vehicle $vehicle)
    {
        return view('vehicles.edit', compact('vehicle'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|unique:vehicles,plate_number,' . $vehicle->id,
            'model' => 'nullable|string',
            'year' => 'nullable|string',
            'vin' => 'nullable|string',
            'customer_name' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'is_in_workshop' => 'boolean',
        ]);

        $validated['is_in_workshop'] = $request->boolean('is_in_workshop', $vehicle->is_in_workshop);

        $vehicle->update($validated);

        return redirect()->route('vehicles.show', $vehicle)
            ->with('success', 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle)
    {
        if ($vehicle->jobs()->where('status', 'uninvoiced')->exists()) {
            return redirect()->route('vehicles.index')
                ->with('error', 'Cannot delete vehicle with uninvoiced jobs.');
        }

        $vehicle->delete();

        return redirect()->route('vehicles.index')
            ->with('success', 'Vehicle deleted successfully.');
    }

    public function toggleWorkshop(Vehicle $vehicle)
    {
        $oldStatus = $vehicle->is_in_workshop;
        $vehicle->update([
            'is_in_workshop' => !$vehicle->is_in_workshop,
        ]);
        
        // Log activity for all jobs linked to this vehicle
        $statusText = $vehicle->is_in_workshop ? 'In Workshop' : 'Out of Workshop';
        foreach ($vehicle->jobs()->where('status', 'uninvoiced')->get() as $job) {
            \App\Models\JobActivity::log($job, 'updated', 
                "Vehicle marked as {$statusText}",
                ['field' => 'vehicle_workshop', 'old' => $oldStatus, 'new' => $vehicle->is_in_workshop]
            );
        }

        return redirect()->back()
            ->with('success', 'Workshop status updated.');
    }

    /**
     * Bulk update workshop status for multiple vehicles
     */
    public function bulkUpdateWorkshop(Request $request)
    {
        $request->validate([
            'vehicle_ids' => 'required|array|min:1',
            'vehicle_ids.*' => 'exists:vehicles,id',
            'status' => 'required|in:in,out',
        ]);

        $status = $request->input('status') === 'in';
        $count = Vehicle::whereIn('id', $request->input('vehicle_ids'))
            ->update(['is_in_workshop' => $status]);

        $statusText = $status ? 'In Workshop' : 'Out of Workshop';
        return response()->json([
            'success' => true,
            'message' => "{$count} vehicle(s) marked as {$statusText}",
        ]);
    }
}
