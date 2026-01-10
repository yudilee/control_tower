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
     */
    public function kanban()
    {
        $statuses = PartOrder::getStatuses();
        
        // Get pending orders grouped by status
        $ordersByStatus = [];
        foreach (array_keys($statuses) as $status) {
            $ordersByStatus[$status] = PartOrder::with('job')
                ->where('status', $status)
                ->orderBy('expected_date', 'asc')
                ->get();
        }

        // Summary counts for dashboard
        $summary = [
            'pending' => PartOrder::pending()->count(),
            'due_soon' => PartOrder::dueSoon(7)->count(),
            'overdue' => PartOrder::overdue()->count(),
        ];

        return view('parts.kanban', compact('statuses', 'ordersByStatus', 'summary'));
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
     */
    /**
     * Update status via AJAX (for Kanban drag-drop)
     * Handles status changes, extra fields, and Work Status automation
     */
    public function updateStatus(Request $request, PartOrder $partOrder): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', array_keys(PartOrder::getStatuses())),
            'part_name' => 'nullable|string|max:255',
            'quantity' => 'nullable|integer|min:1',
            'rq' => 'nullable|string|max:50',
            'part_number' => 'nullable|string|max:100',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'remark' => 'nullable|string', // Additional remark for job
        ]);

        $oldStatus = $partOrder->status;
        
        // Update PartOrder fields if provided (filter out status/remark to handle separately)
        $fillable = array_filter($validated, fn($key) => !in_array($key, ['status', 'remark']), ARRAY_FILTER_USE_KEY);
        if (!empty($fillable)) {
            $partOrder->update($fillable);
        }

        // Update Status
        $partOrder->update([
            'status' => $validated['status'],
            'updated_by' => auth()->id(),
            'received_date' => $validated['status'] === PartOrder::STATUS_RECEIVED ? now()->toDateString() : $partOrder->received_date,
        ]);

        // Job Work Status Logic
        if ($validated['status'] === PartOrder::STATUS_ORDERED) {
             // 5. Buka RQ (Step index 4)
             $partOrder->job->update(['work_status' => Job::WORK_STATUSES[4] ?? '5. Buka RQ (Qrder Parts)']);
        } elseif ($validated['status'] === PartOrder::STATUS_RECEIVED) {
             // 6. Parts Datang (Step index 5)
             $partOrder->job->update(['work_status' => Job::WORK_STATUSES[5] ?? '6. Parts Datang (Parts Received)']);
        }
        
        // Add Remark if present
        if (!empty($validated['remark'])) {
             $partOrder->job->addRemark(
                 "Part Status Update ({$partOrder->part_name} -> {$validated['status']}): " . $validated['remark'],
                 auth()->user()->name,
                 auth()->id()
             );
        }

        return response()->json([
            'success' => true,
            'message' => "Status changed from {$oldStatus} to {$validated['status']}",
            'partOrder' => $partOrder->fresh()->load('job'),
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
            'pending' => PartOrder::pending()->count(),
            'due_soon' => PartOrder::dueSoon(7)->count(),
            'overdue' => PartOrder::overdue()->count(),
        ]);
    }
}
