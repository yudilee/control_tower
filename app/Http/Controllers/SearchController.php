<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Vehicle;
use App\Models\Customer;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search across jobs, vehicles, customers
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([
                'results' => [],
                'message' => 'Please enter at least 2 characters'
            ]);
        }
        
        $results = [];
        
        // Search Jobs
        $jobs = Job::where('job_number', 'like', "%{$query}%")
            ->orWhere('plate_number', 'like', "%{$query}%")
            ->orWhere('invoice_number', 'like', "%{$query}%")
            ->orWhere('customer_name', 'like', "%{$query}%")
            ->limit(5)
            ->get(['id', 'job_number', 'plate_number', 'customer_name', 'status']);
            
        foreach ($jobs as $job) {
            $results[] = [
                'type' => 'job',
                'icon' => 'bi-wrench',
                'title' => $job->job_number,
                'subtitle' => $job->plate_number . ' - ' . ($job->customer_name ?? 'No customer'),
                'badge' => $job->status === 'invoiced' ? 'Invoiced' : 'Uninvoiced',
                'badge_class' => $job->status === 'invoiced' ? 'bg-success' : 'bg-warning text-dark',
                'url' => route('jobs.show', $job->id),
            ];
        }
        
        // Search Vehicles
        $vehicles = Vehicle::where('plate_number', 'like', "%{$query}%")
            ->orWhere('vin', 'like', "%{$query}%")
            ->orWhere('customer_name', 'like', "%{$query}%")
            ->orWhere('model', 'like', "%{$query}%")
            ->limit(5)
            ->get(['id', 'plate_number', 'model', 'customer_name', 'is_in_workshop']);
            
        foreach ($vehicles as $vehicle) {
            $results[] = [
                'type' => 'vehicle',
                'icon' => 'bi-car-front',
                'title' => $vehicle->plate_number,
                'subtitle' => ($vehicle->model ?? 'Unknown') . ' - ' . ($vehicle->customer_name ?? 'No owner'),
                'badge' => $vehicle->is_in_workshop ? 'In Workshop' : null,
                'badge_class' => 'bg-info',
                'url' => route('vehicles.show', $vehicle->id),
            ];
        }
        
        // Search by Customer Name in Jobs (unique customers)
        $customers = Job::where('customer_name', 'like', "%{$query}%")
            ->whereNotNull('customer_name')
            ->select('customer_name')
            ->distinct()
            ->limit(5)
            ->pluck('customer_name');
            
        foreach ($customers as $customerName) {
            $jobCount = Job::where('customer_name', $customerName)->count();
            $results[] = [
                'type' => 'customer',
                'icon' => 'bi-person',
                'title' => $customerName,
                'subtitle' => $jobCount . ' job(s)',
                'badge' => null,
                'badge_class' => null,
                'url' => route('customers.index', ['search' => $customerName]),
            ];
        }
        
        // Sort by relevance (exact matches first)
        usort($results, function($a, $b) use ($query) {
            $aExact = stripos($a['title'], $query) === 0 ? 0 : 1;
            $bExact = stripos($b['title'], $query) === 0 ? 0 : 1;
            return $aExact - $bExact;
        });
        
        return response()->json([
            'results' => array_slice($results, 0, 10),
            'total' => count($results),
            'query' => $query
        ]);
    }
}
