<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerDashboardController extends Controller
{
    public function dashboard()
    {
        $customer = Auth::guard('customer')->user();
        $vehicles = $customer->vehicles()->get();
        
        // Get job statistics
        $jobs = $customer->jobs();
        $stats = [
            'total_jobs' => $jobs->count(),
            'active_jobs' => $jobs->clone()->where('status', 'uninvoiced')->count(),
            'completed_jobs' => $jobs->clone()->where('status', 'invoiced')->count(),
            'total_spent' => $jobs->clone()->where('status', 'invoiced')->sum('inv_ppn_meterai'),
        ];
        
        // Recent jobs (last 5)
        $recentJobs = $customer->jobs()
            ->orderBy('job_date', 'desc')
            ->take(5)
            ->get();
        
        return view('customer.dashboard', compact('customer', 'vehicles', 'stats', 'recentJobs'));
    }

    public function jobs(Request $request)
    {
        $customer = Auth::guard('customer')->user();
        $status = $request->input('status', 'all');
        
        $query = $customer->jobs()->orderBy('job_date', 'desc');
        
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        $jobs = $query->paginate(10);
        
        return view('customer.jobs.index', compact('customer', 'jobs', 'status'));
    }

    public function showJob(Job $job)
    {
        $customer = Auth::guard('customer')->user();
        
        // Verify access
        if (!$customer->canViewJob($job)) {
            abort(403, 'You do not have access to this job.');
        }
        
        $job->load('vehicle', 'remarks');
        
        return view('customer.jobs.show', compact('customer', 'job'));
    }

    public function vehicles()
    {
        $customer = Auth::guard('customer')->user();
        $vehicles = $customer->vehicles()->with('jobs')->get();
        
        return view('customer.vehicles', compact('customer', 'vehicles'));
    }

    public function downloadInvoice(Job $job)
    {
        $customer = Auth::guard('customer')->user();
        
        // Verify access and invoiced status
        if (!$customer->canViewJob($job)) {
            abort(403, 'You do not have access to this job.');
        }
        
        if ($job->status !== 'invoiced') {
            abort(404, 'Invoice not available yet.');
        }
        
        // Generate simple invoice PDF (or HTML for download)
        $html = view('customer.invoice', compact('job', 'customer'))->render();
        
        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="invoice-' . $job->invoice_number . '.html"');
    }

    public function profile()
    {
        $customer = Auth::guard('customer')->user();
        return view('customer.profile', compact('customer'));
    }

    public function updateProfile(Request $request)
    {
        $customer = Auth::guard('customer')->user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);
        
        $customer->update($request->only('name', 'phone', 'address'));
        
        return back()->with('success', 'Profile updated successfully.');
    }
}
