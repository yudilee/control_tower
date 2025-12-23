<?php

namespace App\Http\Controllers;

use App\Actions\Jobs\CreateJob;
use App\Actions\Jobs\MarkAsInvoiced;
use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Models\Job;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class JobController extends Controller
{
    /**
     * Check if user is authorized to view/edit this specific job
     * SA: must be assigned as service_advisor
     * Foreman: must be assigned as foreman  
     * Sparepart: job must have need_part = true
     */
    private function checkAssignmentAuthorization(Job $job)
    {
        $user = auth()->user();
        
        if ($user->hasRole('sa')) {
            if ($user->serviceAdvisor?->name !== $job->service_advisor) {
                abort(403, 'Unauthorized. You are not assigned to this job.');
            }
        } elseif ($user->hasRole('foreman')) {
            if ($user->foreman?->name !== $job->foreman) {
                abort(403, 'Unauthorized. You are not assigned to this job.');
            }
        } elseif ($user->hasRole('sparepart')) {
            if (!$job->need_part) {
                abort(403, 'Unauthorized. This job does not require parts.');
            }
        }
        return true;
    }

    public function index(Request $request)
    {
        $query = Job::with('vehicle');
        $user = auth()->user();

        // SA/Foreman Restrictions
        if ($user->hasRole('sa')) {
            $saName = $user->serviceAdvisor?->name;
            if ($saName) {
                $query->where('service_advisor', $saName);
            } else {
                // If SA user has no linked SA record, show nothing (security default)
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->hasRole('foreman')) {
            $foremanName = $user->foreman?->name;
            if ($foremanName) {
                $query->where('foreman', $foremanName);
            } else {
                // If Foreman user has no linked Foreman record, show nothing
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('franchise')) {
            $query->where('franchise', $request->franchise);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('latest_remark', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('job_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('job_date', '<=', $request->date_to);
        }

        // Sorting
        $sortField = $request->input('sort');
        $sortDir = $request->input('dir', 'desc');
        $allowedSorts = [
            'job_number', 'job_card', 'franchise', 'department', 'job_date', 
            'date_in', 'date_out', 'check_in_time', 'deadline', 'promise_date',
            'plate_number', 'chassis_number', 'unit_type', 'account_no', 'date_first_reg',
            'customer_name', 'service_advisor', 'foreman', 'technician', 
            'block', 'job_type', 'payment_type', 'type_sale', 'job_description', 'work_status',
            'labour_sales', 'part_sales', 'total_sales', 'estimated_amount',
            'rq', 'no_order_part_mbina', 'lain_lain', 'need_part',
            'status', 'invoice_number', 'invoice_date', 'inv_amount', 'inv_ppn', 'inv_ppn_meterai',
            'latest_remark_at', 'created_at', 'updated_at'
        ];

        if ($sortField && in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            // Default sort: Uninvoiced first, then by Creation Date DESC
            $query->orderByRaw("CASE WHEN status = 'uninvoiced' THEN 0 ELSE 1 END ASC")
                  ->orderBy('created_at', 'desc');
        }

        // Column Filters (Excel-style)
        $filterColumns = ['service_advisor', 'foreman', 'department', 'work_status', 'block', 'technician', 'job_type', 'payment_type'];
        foreach ($filterColumns as $filterCol) {
            if ($request->filled("filter_{$filterCol}")) {
                $query->where($filterCol, $request->input("filter_{$filterCol}"));
            }
        }
        // Boolean filter for need_part
        if ($request->filled('filter_need_part')) {
            $query->where('need_part', $request->input('filter_need_part') === '1');
        }

        $perPage = request()->input('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50, 100]) ? $perPage : 20;

        $jobs = $query->paginate($perPage)->withQueryString();

        // Get distinct values for filter dropdowns
        $filterOptions = [
            'service_advisor' => Job::whereNotNull('service_advisor')->distinct()->pluck('service_advisor')->sort()->values(),
            'foreman' => Job::whereNotNull('foreman')->distinct()->pluck('foreman')->sort()->values(),
            'department' => Job::whereNotNull('department')->distinct()->pluck('department')->sort()->values(),
            'work_status' => Job::whereNotNull('work_status')->distinct()->pluck('work_status')->sort()->values(),
            'block' => Job::whereNotNull('block')->distinct()->pluck('block')->sort()->values(),
            'technician' => Job::whereNotNull('technician')->distinct()->pluck('technician')->sort()->values(),
            'job_type' => Job::whereNotNull('job_type')->distinct()->pluck('job_type')->sort()->values(),
            'payment_type' => Job::whereNotNull('payment_type')->distinct()->pluck('payment_type')->sort()->values(),
        ];

        return view('jobs.index', compact('jobs', 'filterOptions'));
    }

    public function create()
    {
        return view('jobs.create');
    }

    public function store(StoreJobRequest $request, CreateJob $action)
    {
        $validated = $request->validated();
        $user = auth()->user();

        $job = $action->execute(
            $validated,
            $request->input('initial_remark'),
            $user?->name,
            $user?->id
        );

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Job created successfully.');
    }

    public function show(Job $job)
    {
        $this->checkAssignmentAuthorization($job);

        $job->load(['vehicle', 'remarks']);
        $serviceAdvisors = \App\Models\ServiceAdvisor::orderBy('name')->get();
        $foremen = \App\Models\Foreman::orderBy('name')->get();
        return view('jobs.show', compact('job', 'serviceAdvisors', 'foremen'));
    }

    public function edit(Job $job)
    {
        // Redirect to show page which now has inline editing
        return redirect()->route('jobs.show', $job);
    }

    public function update(UpdateJobRequest $request, Job $job)
    {
        $job->update($request->validated());

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Job updated successfully.');
    }

    public function destroy(Job $job)
    {
        $job->delete();

        return redirect()->route('jobs.index')
            ->with('success', 'Job deleted successfully.');
    }

    public function addRemark(Request $request, Job $job)
    {
        $this->checkAssignmentAuthorization($job);

        $validated = $request->validate([
            'remark_text' => 'required|string',
        ]);

        $user = auth()->user();
        $remark = $job->addRemark($validated['remark_text'], $user?->name, $user?->id);
        
        // Load the user relationship for the response
        $remark->load('user');

        // Check if this is an AJAX request
        if ($request->expectsJson()) {
            // Determine role color for badge
            $roleColor = match($user?->role) {
                'admin' => 'danger',
                'manager' => 'primary',
                'control_tower' => 'info',
                'sparepart' => 'warning',
                default => 'secondary',
            };

            return response()->json([
                'success' => true,
                'remark' => [
                    'id' => $remark->id,
                    'text' => $remark->remark_text,
                    'commenter_name' => $remark->commenter_name,
                    'initials' => $remark->commenter_initials,
                    'avatar_color' => sprintf('#%06X', crc32($remark->commenter_name) & 0xFFFFFF),
                    'role_display' => $user?->getRoleDisplayName() ?? 'User',
                    'role_color' => $roleColor,
                    'time_ago' => 'just now',
                ],
            ]);
        }

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Remark added successfully.');
    }

    public function markInvoiced(Request $request, Job $job, MarkAsInvoiced $action)
    {
        // Enforce permission check
        if (!auth()->user()->canMarkInvoiced()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'invoice_number' => 'required|string',
            'remark' => 'required|string',
        ]);

        $user = auth()->user();
        $action->execute(
            $job,
            $validated['invoice_number'],
            $validated['remark'],
            $user->name,
            $user->id
        );

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Job marked as invoiced.');
    }

    /**
     * Update Order & Parts section only (for Sparepart role)
     * Only allowed on jobs where need_part = true
     */
    public function updateOrderParts(Request $request, Job $job)
    {
        // Check if job needs parts - only then sparepart can edit
        if (!$job->need_part) {
            return redirect()->route('jobs.show', $job)
                ->with('error', 'You can only edit Order & Parts for jobs that need parts.');
        }

        $validated = $request->validate([
            'rq' => 'nullable|string',
            'no_order_part_mbina' => 'nullable|string',
            'lain_lain' => 'nullable|string',
        ]);

        $job->update($validated);

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Order & Parts updated successfully.');
    }

    /**
     * Bulk update work status or add bulk remarks
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'job_ids' => 'required|array',
            'job_ids.*' => 'exists:jobs,id',
            'action' => 'required|in:work_status,remark',
        ]);

        $jobIds = $request->input('job_ids');
        $action = $request->input('action');
        $user = auth()->user();
        $count = count($jobIds);

        if ($action === 'work_status') {
            $request->validate(['work_status' => 'required|string']);
            $workStatus = $request->input('work_status');
            
            Job::whereIn('id', $jobIds)->update(['work_status' => $workStatus]);
            
            return redirect()->back()->with('success', "Updated work status to '{$workStatus}' for {$count} jobs.");
        }

        if ($action === 'remark') {
            $request->validate(['remark_text' => 'required|string']);
            $remarkText = $request->input('remark_text');
            
            $jobs = Job::whereIn('id', $jobIds)->get();
            foreach ($jobs as $job) {
                $job->addRemark($remarkText, $user?->name, $user?->id);
            }
            
            return redirect()->back()->with('success', "Added remark to {$count} jobs.");
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }

    /**
     * Kanban board view for jobs
     */
    public function kanban(Request $request)
    {
        $user = auth()->user();
        
        // Get work status options from database
        $workStatuses = \App\Models\DropdownOption::getOptions('work_status');
        
        // Base query - only uninvoiced jobs
        $query = Job::uninvoiced();
        
        // Apply role-based restrictions
        if ($user->hasRole('sa')) {
            $saName = $user->serviceAdvisor?->name;
            if ($saName) {
                $query->where('service_advisor', $saName);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->hasRole('foreman')) {
            $foremanName = $user->foreman?->name;
            if ($foremanName) {
                $query->where('foreman', $foremanName);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        
        // Optional filters
        if ($request->filled('franchise')) {
            $query->where('franchise', $request->franchise);
        }
        if ($request->filled('service_advisor')) {
            $query->where('service_advisor', $request->service_advisor);
        }
        
        // Get jobs grouped by work status
        $jobs = $query->orderBy('job_date', 'desc')->get();
        
        $jobsByStatus = [];
        foreach ($workStatuses as $status) {
            $jobsByStatus[$status->value] = $jobs->filter(fn($job) => 
                ($job->work_status ?? 'pending') === $status->value
            )->take(50); // Limit per column for performance
        }
        
        // Also include jobs with no status (pending)
        $pendingJobs = $jobs->filter(fn($job) => empty($job->work_status));
        if ($pendingJobs->isNotEmpty() && isset($jobsByStatus['pending'])) {
            $jobsByStatus['pending'] = $jobsByStatus['pending']->merge($pendingJobs)->take(50);
        }
        
        // Filter options
        $filterOptions = [
            'service_advisor' => Job::uninvoiced()->whereNotNull('service_advisor')
                ->distinct()->pluck('service_advisor')->sort()->values(),
            'franchise' => ['PC', 'CV'],
        ];
        
        return view('jobs.kanban', compact('workStatuses', 'jobsByStatus', 'filterOptions'));
    }

    /**
     * Update job work status via AJAX (for kanban drag-drop)
     */
    public function updateWorkStatus(Request $request, Job $job)
    {
        $validated = $request->validate([
            'work_status' => 'required|string',
        ]);
        
        $job->update(['work_status' => $validated['work_status']]);
        
        return response()->json([
            'success' => true,
            'message' => "Job {$job->job_number} moved to {$validated['work_status']}",
        ]);
    }
}

