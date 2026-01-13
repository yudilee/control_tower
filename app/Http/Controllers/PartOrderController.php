<?php

namespace App\Http\Controllers;

use App\Models\PartOrder;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PartOrderController extends Controller
{
    /**
     * Display Kanban board for parts tracking
     * 
     * Pending column shows Jobs that need parts (not PartOrders).
     * Other columns (Buka RQ → Received) show PartOrders.
     * 
     * Permissions:
     * - Pending → Buka RQ: admin, control_tower, assigned foreman
     * - Other status changes: sparepart, admin only
     * 
     * Default filters by role:
     * - SA: own assigned jobs
     * - Foreman: own assigned jobs
     * - Sparepart: all jobs with need_part
     */
    public function kanban(Request $request)
    {
        $user = auth()->user();
        $statuses = PartOrder::getStatuses();
        
        // Determine default filter based on role (only on initial load without filters)
        $defaultForeman = null;
        $defaultSA = null;
        
        // Skip role-based defaults if 'clear' is passed or any filter is set
        if (!$request->has('clear') && !$request->hasAny(['search', 'service_advisor', 'foreman', 'date_from', 'date_to'])) {
            if ($user->role === 'foreman') {
                // Foreman sees their own assigned jobs by default
                $foreman = \App\Models\Foreman::where('user_id', $user->id)->first();
                if ($foreman) {
                    $defaultForeman = $foreman->name;
                }
            } elseif ($user->role === 'sa') {
                // SA sees their own assigned jobs by default
                $sa = \App\Models\ServiceAdvisor::where('user_id', $user->id)->first();
                if ($sa) {
                    $defaultSA = $sa->name;
                }
            }
            // Sparepart/admin/control_tower see all need_part jobs (no filter applied)
        }
        
        // Pending column: Jobs with need_part=true AND uninvoiced
        // These are jobs waiting to have an RQ opened
        $pendingJobsQuery = Job::where('need_part', true)
            ->where('status', '!=', 'invoiced')
            ->with(['partOrders' => function($q) {
                $q->select('job_id', 'status');
            }]);
        
        // Apply filters to pending jobs
        if ($request->filled('search')) {
            $search = $request->input('search');
            $pendingJobsQuery->where(function($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('plate_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }
        
        // Apply foreman filter (from request or default)
        $foremanFilter = $request->input('foreman', $defaultForeman);
        if ($foremanFilter) {
            $pendingJobsQuery->where('foreman', $foremanFilter);
        }
        
        // Apply SA filter (from request or default)
        $saFilter = $request->input('service_advisor', $defaultSA);
        if ($saFilter) {
            $pendingJobsQuery->where('service_advisor', $saFilter);
        }
        
        $pendingJobs = $pendingJobsQuery->orderBy('job_date', 'desc')->get();
        
        // Build base query for PartOrders (Buka RQ → Received columns)
        $baseQuery = PartOrder::with('job')
            ->join('jobs', 'part_orders.job_id', '=', 'jobs.id')
            ->select('part_orders.*');
        
        // Apply filters to part orders
        if ($request->filled('search')) {
            $search = $request->input('search');
            $baseQuery->where(function($q) use ($search) {
                $q->where('part_orders.rq', 'like', "%{$search}%")
                  ->orWhere('part_orders.no_order_part', 'like', "%{$search}%")
                  ->orWhere('jobs.job_number', 'like', "%{$search}%")
                  ->orWhere('jobs.plate_number', 'like', "%{$search}%");
            });
        }
        
        // Apply foreman filter to part orders
        if ($foremanFilter) {
            $baseQuery->where('jobs.foreman', $foremanFilter);
        }
        
        // Apply SA filter to part orders
        if ($saFilter) {
            $baseQuery->where('jobs.service_advisor', $saFilter);
        }
        
        if ($request->filled('date_from')) {
            $baseQuery->whereDate('part_orders.order_date', '>=', $request->input('date_from'));
        }
        
        if ($request->filled('date_to')) {
            $baseQuery->whereDate('part_orders.order_date', '<=', $request->input('date_to'));
        }
        
        // Get orders grouped by status (excluding pending - that's for jobs)
        $ordersByStatus = [];
        foreach (array_keys($statuses) as $status) {
            if ($status === 'pending') continue; // Pending is for jobs, not orders
            $ordersByStatus[$status] = (clone $baseQuery)
                ->where('part_orders.status', $status)
                ->orderBy('part_orders.created_at', 'desc')
                ->get();
        }

        // Summary counts
        $summary = [
            'pending' => $pendingJobs->count(),
            'due_soon' => PartOrder::dueSoon(7)->count(),
            'overdue' => PartOrder::overdue()->count(),
        ];
        
        // Get filter options
        $filterOptions = [
            'service_advisors' => Job::where('need_part', true)
                ->distinct()
                ->whereNotNull('service_advisor')
                ->pluck('service_advisor')
                ->sort()
                ->values(),
            'foremen' => Job::where('need_part', true)
                ->distinct()
                ->whereNotNull('foreman')
                ->pluck('foreman')
                ->sort()
                ->values(),
        ];
        
        // Permission flags for the view
        // Pending → Buka RQ: admin, control_tower, assigned foreman
        // Other transitions: sparepart, admin
        $permissions = [
            'canOpenRq' => in_array($user->role, ['admin', 'control_tower', 'foreman']),
            'canUpdateStatus' => in_array($user->role, ['admin', 'sparepart']),
            'userRole' => $user->role,
            'userForeman' => $user->role === 'foreman' 
                ? (\App\Models\Foreman::where('user_id', $user->id)->first()?->name ?? null)
                : null,
        ];
        
        // Pass applied filters for form values
        $appliedFilters = [
            'foreman' => $foremanFilter,
            'service_advisor' => $saFilter,
        ];

        return view('parts.kanban', compact('statuses', 'ordersByStatus', 'pendingJobs', 'summary', 'filterOptions', 'permissions', 'appliedFilters'));
    }

    /**
     * Display parts list (table view)
     */
    public function index(Request $request)
    {
        $query = PartOrder::with('job');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('part_name', 'like', "%{$search}%")
                  ->orWhere('part_number', 'like', "%{$search}%")
                  ->orWhereHas('job', function($jq) use ($search) {
                      $jq->where('job_number', 'like', "%{$search}%");
                  });
            });
        }
        if ($request->filter === 'overdue') {
            $query->overdue();
        } elseif ($request->filter === 'due_soon') {
            $query->dueSoon(7);
        }

        $partOrders = $query->orderBy('expected_date', 'asc')->paginate(20);
        $statuses = PartOrder::getStatuses();

        return view('parts.index', compact('partOrders', 'statuses'));
    }

    /**
     * Show form to create new part order for a job
     */
    public function create(Request $request)
    {
        $job = null;
        $jobs = collect();
        
        if ($request->filled('job_id')) {
            $job = Job::findOrFail($request->job_id);
        } else {
            // Get all jobs that need parts (uninvoiced only)
            $jobs = Job::uninvoiced()
                ->where('need_part', true)
                ->orderBy('job_date', 'desc')
                ->get(['id', 'job_number', 'customer_name', 'plate_number']);
        }

        $statuses = PartOrder::getStatuses();
        return view('parts.form', compact('job', 'jobs', 'statuses'));
    }

    /**
     * Store new part order
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'job_id' => 'required|exists:jobs,id',
            'part_name' => 'required|string|max:255',
            'part_number' => 'nullable|string|max:100',
            'quantity' => 'required|integer|min:1',
            'order_date' => 'required|date',
            'expected_date' => 'required|date|after_or_equal:order_date',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|string',
        ]);

        $validated['status'] = $validated['status'] ?? PartOrder::STATUS_PENDING;
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $partOrder = PartOrder::create($validated);

        // Update job's need_part flag
        Job::where('id', $validated['job_id'])->update(['need_part' => true]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Part order created successfully.',
                'partOrder' => $partOrder->load('job'),
            ]);
        }

        return redirect()->route('jobs.show', $validated['job_id'])
            ->with('success', 'Part order created successfully.');
    }

    /**
     * Show single part order
     */
    public function show(PartOrder $partOrder)
    {
        $partOrder->load('job', 'creator', 'updater');
        return view('parts.show', compact('partOrder'));
    }

    /**
     * Show form to edit part order
     */
    public function edit(PartOrder $partOrder)
    {
        $partOrder->load('job');
        $statuses = PartOrder::getStatuses();
        return view('parts.form', compact('partOrder', 'statuses'));
    }

    /**
     * Update part order
     */
    public function update(Request $request, PartOrder $partOrder)
    {
        $validated = $request->validate([
            'part_name' => 'required|string|max:255',
            'part_number' => 'nullable|string|max:100',
            'quantity' => 'required|integer|min:1',
            'order_date' => 'required|date',
            'expected_date' => 'required|date|after_or_equal:order_date',
            'received_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'status' => 'required|string',
        ]);

        $validated['updated_by'] = auth()->id();

        // Auto-set received_date when status changes to received
        if ($validated['status'] === PartOrder::STATUS_RECEIVED && !$validated['received_date']) {
            $validated['received_date'] = now()->toDateString();
        }

        $partOrder->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Part order updated successfully.',
                'partOrder' => $partOrder->fresh()->load('job'),
            ]);
        }

        return redirect()->route('jobs.show', $partOrder->job_id)
            ->with('success', 'Part order updated successfully.');
    }

    /**
     * Delete part order
     */
    public function destroy(PartOrder $partOrder)
    {
        $jobId = $partOrder->job_id;
        $partOrder->delete();

        // Check if job still has pending part orders
        $remainingParts = PartOrder::where('job_id', $jobId)->pending()->count();
        if ($remainingParts === 0) {
            Job::where('id', $jobId)->update(['need_part' => false]);
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Part order deleted.',
            ]);
        }

        return redirect()->back()->with('success', 'Part order deleted.');
    }

    /**
     * Update status via AJAX (for Kanban drag-drop)
     * Handles status changes, extra fields, and Work Status automation.
     * Enforces 1-step movement only (e.g., buka_rq → ordered, not buka_rq → received).
     * 
     * Permission: Only sparepart and admin can update statuses.
     */
    public function updateStatus(Request $request, PartOrder $partOrder): JsonResponse
    {
        $user = auth()->user();
        
        // Permission check: only sparepart and admin can update status
        if (!in_array($user->role, ['admin', 'sparepart'])) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update part order status.',
            ], 403);
        }
        
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', array_keys(PartOrder::getStatuses())),
            'no_order_part' => 'nullable|string|max:100',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'remark' => 'nullable|string',
        ]);

        $oldStatus = $partOrder->status;
        $newStatus = $validated['status'];
        
        // Define allowed transitions (1-step only)
        $allowedTransitions = [
            PartOrder::STATUS_BUKA_RQ => [PartOrder::STATUS_ORDERED],
            PartOrder::STATUS_ORDERED => [PartOrder::STATUS_CONFIRMED],
            PartOrder::STATUS_CONFIRMED => [PartOrder::STATUS_SHIPPED],
            PartOrder::STATUS_SHIPPED => [PartOrder::STATUS_RECEIVED],
        ];
        
        // Validate 1-step movement
        if (!isset($allowedTransitions[$oldStatus]) || !in_array($newStatus, $allowedTransitions[$oldStatus])) {
            return response()->json([
                'success' => false,
                'message' => "Invalid transition: {$oldStatus} → {$newStatus}. Only 1-step movement is allowed.",
            ], 400);
        }
        
        // Validate required fields when moving to "ordered"
        if ($newStatus === PartOrder::STATUS_ORDERED) {
            if (empty($validated['no_order_part']) || empty($validated['order_date']) || empty($validated['expected_date'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order No, Order Date, and Expected Date are required when moving to Ordered.',
                ], 400);
            }
        }
        
        // Update PartOrder fields if provided
        $fillable = array_filter($validated, fn($key) => !in_array($key, ['status', 'remark']), ARRAY_FILTER_USE_KEY);
        if (!empty($fillable)) {
            $partOrder->update($fillable);
        }

        // Update Status
        $partOrder->update([
            'status' => $newStatus,
            'updated_by' => auth()->id(),
            'received_date' => $newStatus === PartOrder::STATUS_RECEIVED ? now()->toDateString() : $partOrder->received_date,
        ]);

        // Job Work Status Logic
        $job = $partOrder->job;
        
        if ($newStatus === PartOrder::STATUS_RECEIVED) {
            // Check if ALL part orders for this job are received
            $unreceived = PartOrder::where('job_id', $job->id)
                ->whereNotIn('status', [PartOrder::STATUS_RECEIVED, PartOrder::STATUS_INSTALLED, PartOrder::STATUS_CANCELLED])
                ->count();
            
            if ($unreceived === 0) {
                // All parts received - update job work_status to "6. Parts Datang"
                $job->update(['work_status' => Job::WORK_STATUSES[5] ?? '6. Parts Datang (Parts Received)']);
            }
        }
        
        // Add Remark if present
        if (!empty($validated['remark'])) {
            $job->addRemark(
                "Part Order RQ:{$partOrder->rq} status: {$oldStatus} → {$newStatus}. " . $validated['remark'],
                auth()->user()->name,
                auth()->id()
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Status changed from {$oldStatus} to {$newStatus}",
            'partOrder' => $partOrder->fresh()->load('job'),
        ]);
    }

    /**
     * Create a new PartOrder from Job (for Kanban: Pending → Buka RQ)
     * Called when dragging a Job card to the Buka RQ column.
     * 
     * Permission: admin, control_tower, or assigned foreman only.
     */
    public function createFromJob(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        $validated = $request->validate([
            'job_id' => 'required|exists:jobs,id',
            'rq' => 'required|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        $job = Job::findOrFail($validated['job_id']);
        
        // Permission check: admin, control_tower, or assigned foreman
        $canOpenRq = false;
        if (in_array($user->role, ['admin', 'control_tower'])) {
            $canOpenRq = true;
        } elseif ($user->role === 'foreman') {
            // Foreman can only open RQ for their assigned jobs (supports multiple foreman assignments)
            $foremanNames = \App\Models\Foreman::where('user_id', $user->id)->pluck('name')->toArray();
            if (!empty($foremanNames) && in_array($job->foreman, $foremanNames)) {
                $canOpenRq = true;
            }
        }
        
        if (!$canOpenRq) {
            $errorMsg = 'You do not have permission to open RQ for this job.';
            if ($user->role === 'foreman') {
                $errorMsg = "This job is assigned to foreman '{$job->foreman}', not to you. You can only open RQ for jobs assigned to your linked foreman profile.";
            }
            return response()->json([
                'success' => false,
                'message' => $errorMsg,
            ], 403);
        }
        
        // Create new PartOrder with status buka_rq
        $partOrder = PartOrder::create([
            'job_id' => $job->id,
            'rq' => $validated['rq'],
            'notes' => $validated['notes'] ?? null,
            'status' => PartOrder::STATUS_BUKA_RQ,
            'order_date' => now()->toDateString(),

            'created_by' => auth()->id(),
        ]);
        
        // Update job work_status to "5. Buka RQ"
        $job->update(['work_status' => Job::WORK_STATUSES[4] ?? '5. Buka RQ (Qrder Parts)']);
        
        // Log activity
        \App\Models\JobActivity::log($job, 'rq_opened', 
            "RQ opened: {$validated['rq']}",
            ['rq' => $validated['rq'], 'part_order_id' => $partOrder->id]
        );
        
        return response()->json([
            'success' => true,
            'message' => "RQ {$validated['rq']} created for job {$job->job_number}",
            'partOrder' => $partOrder->load('job'),
        ]);
    }

    /**
     * Get parts for a specific job (AJAX)
     */
    public function forJob(Job $job): JsonResponse
    {
        $partOrders = $job->partOrders()->with('creator')->get();

        return response()->json([
            'success' => true,
            'partOrders' => $partOrders,
        ]);
    }

    /**
     * Get summary counts for dashboard
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'pending' => Job::where('need_part', true)->where('status', '!=', 'invoiced')->count(),
            'due_soon' => PartOrder::dueSoon(7)->count(),
            'overdue' => PartOrder::overdue()->count(),
        ]);
    }
}
