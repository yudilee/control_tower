<?php

namespace App\Http\Controllers;

use App\Models\PartOrder;
use App\Models\Job;
use App\Models\JobActivity;
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
                $q->where('rq', 'like', "%{$search}%")
                  ->orWhere('no_order_part', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
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
            'rq' => 'required|string|unique:part_orders,rq',
            'no_order_part' => 'nullable|string|max:100',
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

        // Log Activity
        $job = $partOrder->job;
        $rqLink = route('part-orders.edit', $partOrder->id);
        
        JobActivity::log(
            $job, 
            JobActivity::ACTION_PARTS_UPDATED, 
            "RQ opened: <a href='{$rqLink}'>{$partOrder->rq}</a>",
            ['rq' => $partOrder->rq]
        );

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
        // Eager load job and remarks for context
        $partOrder->load(['job.remarks' => function($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        // Filter activities relevant to this RQ
        $rqActivities = \App\Models\JobActivity::where('job_id', $partOrder->job_id)
            ->where(function($q) use ($partOrder) {
                // Check description for RQ number
                $q->where('description', 'like', "%{$partOrder->rq}%")
                  // Or check changes JSON column if we used it
                  ->orWhereJsonContains('changes->rq', $partOrder->rq);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $statuses = PartOrder::getStatuses();
        return view('parts.form', compact('partOrder', 'statuses', 'rqActivities'));
    }

    /**
     * Update part order
     */
    public function update(Request $request, PartOrder $partOrder)
    {
        $validated = $request->validate([
            'rq' => 'required|string|unique:part_orders,rq,'.$partOrder->id,
            'no_order_part' => 'nullable|string|max:100',
            'order_date' => 'required|date',
            'expected_date' => 'required|date',
            'received_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'new_comment' => 'nullable|string',
        ]);

        // Status is not editable via form anymore, only via Kanban
        
        $partOrder->update([
            'rq' => $validated['rq'],
            'no_order_part' => $validated['no_order_part'],
            'order_date' => $validated['order_date'],
            'expected_date' => $validated['expected_date'],
            'received_date' => $validated['received_date'] ?? $partOrder->received_date,
            'notes' => $validated['notes'],
            'updated_by' => auth()->id(),
        ]);

        // Log Activity if meaningful changes
        $changes = $partOrder->getChanges();
        if (!empty($changes)) {
            $changeDetails = [];
            foreach ($changes as $field => $newValue) {
                if (in_array($field, ['updated_at', 'updated_by'])) continue;
                $orig = $partOrder->getOriginal($field);
                $changeDetails[] = ucfirst(str_replace('_', ' ', $field)) . ": '{$orig}' → '{$newValue}'";
            }
            
            if (!empty($changeDetails)) {
                $rqLink = route('part-orders.edit', $partOrder->id);
                $desc = "RQ <a href='{$rqLink}'>{$partOrder->rq}</a> updated: " . implode(', ', $changeDetails);
                
                JobActivity::log(
                    $partOrder->job, 
                    JobActivity::ACTION_PARTS_UPDATED, 
                    $desc,
                    $changes
                );

                // Log to AuditLog (System)
                \App\Models\AuditLog::create([
                    'auditable_type' => get_class($partOrder),
                    'auditable_id' => $partOrder->id,
                    'user_id' => auth()->id(),
                    'action' => 'updated',
                    'old_values' => array_map(fn($field) => $partOrder->getOriginal($field), array_keys($changes)),
                    'new_values' => $changes,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        }

        // Auto-set received_date when status changes to received (if it were passed which it isn't here but keeping logic safe)
        // (This logic is mainly handled in updateStatus for Kanban)

        // Handle New Comment
        if (!empty($validated['new_comment'])) {
            $user = auth()->user();
            // Prefix comment with [RQ:{number}]
            $commentText = "[RQ:{$partOrder->rq}] " . $validated['new_comment'];
            
            // Add remark to Job
            $partOrder->job->addRemark($commentText, $user->name, $user->id);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Part order updated successfully.',
                'partOrder' => $partOrder->fresh()->load('job'),
            ]);
        }

        return redirect()->route('part-orders.edit', $partOrder->id)
            ->with('success', 'Part order updated successfully.');
    }

    /**
     * Delete part order
     */
    /**
     * Delete part order (Undo Creation)
     */
    public function destroy(PartOrder $partOrder)
    {
        $user = auth()->user();
        
        // Permission Check: Admin, Sparepart, or Creator
        $canDelete = in_array($user->role, ['admin', 'sparepart']);
        
        if (!$canDelete && $user->id === $partOrder->created_by) {
            $canDelete = true;
        }
        
        if (!$canDelete) {
            $message = 'You do not have permission to delete this part order.';
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }
            return redirect()->back()->with('error', $message);
        }

        $jobId = $partOrder->job_id;
        $rqNumber = $partOrder->rq;
        
        // Log before delete
        $job = $partOrder->job;
        \App\Models\JobActivity::log(
            $job, 
            \App\Models\JobActivity::ACTION_PARTS_UPDATED, 
            "RQ <strong>{$rqNumber}</strong> deleted (Undo Creation)",
            ['rq' => $rqNumber, 'deleted_by' => $user->name]
        );
        
        \App\Models\AuditLog::create([
            'auditable_type' => get_class($partOrder),
            'auditable_id' => $partOrder->id,
            'user_id' => $user->id,
            'action' => 'deleted',
            'old_values' => $partOrder->toArray(),
            'new_values' => null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $partOrder->delete();

        // Check if job still has pending part orders
        $remainingParts = PartOrder::where('job_id', $jobId)->pending()->count();
        if ($remainingParts === 0) {
            Job::where('id', $jobId)->update(['need_part' => false]);
            // Also revert work status if it was "5. Buka RQ" back to previous if needed, 
            // but usually it stays at "Checkup" or similar. 
            // For now, we leave work_status as is or maybe reverting is complex.
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
        $oldStatus = $partOrder->status;
        $newStatus = $request->input('status');
        
        // Permission check based on transition
        // Ready → Received: can be done by foreman, control_tower, sparepart, admin (workshop receives the part)
        // Other transitions: sparepart and admin only
        $canUpdate = false;
        
        if ($oldStatus === PartOrder::STATUS_READY && $newStatus === PartOrder::STATUS_RECEIVED) {
            // Workshop (foreman/control_tower) can mark as received
            $canUpdate = in_array($user->role, ['admin', 'sparepart', 'foreman', 'control_tower']);
        } else {
            // Other transitions: sparepart/admin only
            $canUpdate = in_array($user->role, ['admin', 'sparepart']);
        }
        
        if (!$canUpdate) {
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
        
        // Handle legacy 'ordering' status - map to 'processing'
        if ($oldStatus === 'ordering') {
            $oldStatus = PartOrder::STATUS_PROCESSING;
        }
        if ($newStatus === 'ordering') {
            $newStatus = PartOrder::STATUS_PROCESSING;
        }
        
        // Define allowed transitions (including reverse for Undo)
        $allowedTransitions = [
            // Forward
            PartOrder::STATUS_RQ_SENT => [PartOrder::STATUS_PROCESSING],
            PartOrder::STATUS_PROCESSING => [PartOrder::STATUS_READY, PartOrder::STATUS_RQ_SENT], // Allow back to RQ Sent
            PartOrder::STATUS_READY => [PartOrder::STATUS_RECEIVED, PartOrder::STATUS_PROCESSING], // Allow back to Processing
            PartOrder::STATUS_RECEIVED => [PartOrder::STATUS_READY], // Allow back to Ready
        ];
        
        // Validate transition
        if (!isset($allowedTransitions[$oldStatus]) || !in_array($newStatus, $allowedTransitions[$oldStatus])) {
            return response()->json([
                'success' => false,
                'message' => "Invalid transition: {$oldStatus} → {$newStatus}.",
            ], 400);
        }

        // Strict Undo Ownership Check
        // If moving BACKWARD, only the user who moved it forward (updated_by) OR Admin can undo.
        $isBackward = 
            ($oldStatus === PartOrder::STATUS_PROCESSING && $newStatus === PartOrder::STATUS_RQ_SENT) ||
            ($oldStatus === PartOrder::STATUS_READY && $newStatus === PartOrder::STATUS_PROCESSING) ||
            ($oldStatus === PartOrder::STATUS_RECEIVED && $newStatus === PartOrder::STATUS_READY);

        if ($isBackward) {
            // Check if user is Admin or the one who last updated it
            if ($user->role !== 'admin' && $partOrder->updated_by !== $user->id) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Only the user who moved this card or an Admin can undo this action.',
                ], 403);
            }
        }
        
        // Validate required fields ONLY when moving forward to "processing" (skip if undoing from Ready)
        if ($newStatus === PartOrder::STATUS_PROCESSING && $oldStatus === PartOrder::STATUS_RQ_SENT) {
            if (empty($validated['no_order_part']) || empty($validated['order_date']) || empty($validated['expected_date'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order No, Order Date, and Expected Date are required when processing an order.',
                ], 400);
            }
        }
        
        // Update PartOrder fields if provided
        $fillable = array_filter($validated, fn($key) => !in_array($key, ['status', 'remark']), ARRAY_FILTER_USE_KEY);
        if (!empty($fillable)) {
            $partOrder->update($fillable);
        }

        // Update Status with auto-dates
        // Update Status with auto-dates
        // Logic for Data Clearing on Undo (Reverse Transition)
        $fieldsToClear = [];
        $clearedData = [];

        // Processing -> RQ Sent (Undo Ordering)
        if ($oldStatus === PartOrder::STATUS_PROCESSING && $newStatus === PartOrder::STATUS_RQ_SENT) {
            $fieldsToClear = ['no_order_part', 'order_date', 'expected_date', 'notes'];
        }
        // Ready -> Processing (Undo Ready)
        elseif ($oldStatus === PartOrder::STATUS_READY && $newStatus === PartOrder::STATUS_PROCESSING) {
            $fieldsToClear = ['ready_date'];
        }
        // Received -> Ready (Undo Received)
        elseif ($oldStatus === PartOrder::STATUS_RECEIVED && $newStatus === PartOrder::STATUS_READY) {
            $fieldsToClear = ['received_date'];
        }

        // Capture data before clearing
        if (!empty($fieldsToClear)) {
            foreach ($fieldsToClear as $field) {
                // For dates, we might want to ensure they are nullified in the update payload
                if ($partOrder->$field) {
                    $clearedData[$field] = $partOrder->$field;
                    // Add to update payload (setting to null)
                    $validated[$field] = null;
                    
                    // Also forcibly update the model instance for the immediate update call below if needed, 
                    // but $partOrder->update() inside the 'if (!empty($fillable))' block above handles $fillable.
                    // We need to ensure these nulls are applied. 
                    // The block above filters $validated by keys not in ['status', 'remark'].
                    // So adding them to $validated with null values will work if we re-run the fillable logic or just pass them to the final update.
                }
            }
        }
        
        $updateData = [
            'status' => $newStatus,
            'updated_by' => auth()->id(),
            'ready_date' => $newStatus === PartOrder::STATUS_READY ? now()->toDateString() : ($newStatus === PartOrder::STATUS_PROCESSING && $oldStatus === PartOrder::STATUS_READY ? null : $partOrder->ready_date),
            'received_date' => $newStatus === PartOrder::STATUS_RECEIVED ? now()->toDateString() : ($newStatus === PartOrder::STATUS_READY && $oldStatus === PartOrder::STATUS_RECEIVED ? null : $partOrder->received_date),
        ];

        // Merge cleared fields into update data
        foreach ($fieldsToClear as $field) {
            if (!isset($updateData[$field])) { // Don't overwrite ready_date/received_date logic if already set
                $updateData[$field] = null;
            }
        }

        $partOrder->update($updateData);

        // Log Data Clearing
        if (!empty($clearedData)) {
            // Log to JobActivity
            $job = $partOrder->job;
            $clearedDataStr = implode(', ', array_map(
                fn($v, $k) => "$k: " . (is_object($v) ? $v->toDateString() : $v),
                $clearedData,
                array_keys($clearedData)
            ));
            
            \App\Models\JobActivity::log(
                $job, 
                \App\Models\JobActivity::ACTION_PARTS_UPDATED, 
                "RQ <a href='" . route('part-orders.edit', $partOrder->id) . "'>{$partOrder->rq}</a> reversed: <strong>{$oldStatus}</strong> → <strong>{$newStatus}</strong>. Data cleared: {$clearedDataStr}",
                ['cleared_data' => $clearedData, 'from' => $oldStatus, 'to' => $newStatus]
            );
            
            // Log to AuditLog
            \App\Models\AuditLog::create([
                'auditable_type' => get_class($partOrder),
                'auditable_id' => $partOrder->id,
                'user_id' => auth()->id(),
                'action' => 'updated',
                'old_values' => $clearedData,
                'new_values' => array_fill_keys(array_keys($clearedData), null),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }

        // Job Work Status Logic - Trigger on READY (part available for pickup)
        $job = $partOrder->job;
        
        if ($newStatus === PartOrder::STATUS_READY) {
            // Check if ALL part orders for this job are ready or beyond
            $notReady = PartOrder::where('job_id', $job->id)
                ->whereNotIn('status', [PartOrder::STATUS_READY, PartOrder::STATUS_RECEIVED, PartOrder::STATUS_CANCELLED])
                ->count();
            
            if ($notReady === 0) {
                // All parts ready - update job work_status to "6. Parts Datang"
                $job->update(['work_status' => Job::WORK_STATUSES[5] ?? '6. Parts Datang (Parts Received)']);
            }
        }
        
        // Add Remark if present
        if (!empty($validated['remark'])) {
            $job->addRemark(
                "[RQ:{$partOrder->rq}] Status: {$oldStatus} → {$newStatus}. " . $validated['remark'],
                auth()->user()->name,
                auth()->id()
            );
        }

        // Log Activity
        $rqLink = route('part-orders.edit', $partOrder->id);
        $statusLabel = $partOrder->status_label;
        $oldStatusLabel = PartOrder::getStatuses()[$oldStatus]['label'] ?? $oldStatus;
        
        JobActivity::log(
            $job, 
            JobActivity::ACTION_PARTS_UPDATED, 
            "RQ <a href='{$rqLink}'>{$partOrder->rq}</a> status changed: <strong>{$oldStatusLabel}</strong> → <strong>{$statusLabel}</strong>",
            ['from' => $oldStatus, 'to' => $newStatus]
        );

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
            'rq' => 'required|string|max:50|unique:part_orders,rq',
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
        
        // Create new PartOrder with status rq_sent (RQ submitted to Sparepart)
        $partOrder = PartOrder::create([
            'job_id' => $job->id,
            'rq' => $validated['rq'],
            'notes' => $validated['notes'] ?? null,
            'status' => PartOrder::STATUS_RQ_SENT,
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
