<?php

namespace App\Http\Controllers;

use App\Actions\Jobs\CreateJob;
use App\Actions\Jobs\MarkAsInvoiced;
use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Models\Job;
use App\Models\Notification;
use App\Models\Remark;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Controller for managing workshop service jobs.
 * 
 * Handles all job-related operations including listing, creating, updating,
 * deleting jobs, managing remarks, invoice marking, and bulk operations.
 * Implements role-based access control for SA, Foreman, and Sparepart roles.
 * 
 * @package App\Http\Controllers
 * @author Control Tower Team
 */
class JobController extends Controller
{
    /**
     * Check if user is authorized to view/edit this specific job.
     * 
     * Authorization rules:
     * - SA: must be assigned as service_advisor on the job
     * - Foreman: must be assigned as foreman on the job
     * - Sparepart: job must have need_part = true
     * - Other roles: pass through without restriction
     *
     * @param Job $job The job to check authorization for
     * @return bool Returns true if authorized
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 if not authorized
     */
    private function checkAssignmentAuthorization(Job $job): bool
    {
        $user = auth()->user();
        
        if ($user->hasRole('sa')) {
            if ($user->serviceAdvisor?->name !== $job->service_advisor) {
                abort(403, 'Unauthorized. You are not assigned to this job.');
            }
        } elseif ($user->hasRole('foreman')) {
            $foremanNames = $user->foremen()->pluck('name')->toArray();
            if (!in_array($job->foreman, $foremanNames)) {
                abort(403, 'Unauthorized. You are not assigned to this job.');
            }
        } elseif ($user->hasRole('sparepart')) {
            if (!$job->need_part) {
                abort(403, 'Unauthorized. This job does not require parts.');
            }
        }
        return true;
    }

    /**
     * Display a listing of jobs with filtering, sorting, and pagination.
     * 
     * Supports filtering by status, franchise, search term, date range,
     * service advisor, foreman, department, work status, and more.
     * SA/Foreman roles see only their assigned jobs.
     *
     * @param Request $request HTTP request with filter/sort parameters
     * @return View The jobs index view with paginated jobs and filter options
     */
    public function index(Request $request): View
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
            $foremanNames = $user->foremen()->pluck('name')->toArray();
            if (!empty($foremanNames)) {
                $query->whereIn('foreman', $foremanNames);
            } else {
                // If Foreman user has no linked Foreman record, show nothing
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->isFinance()) {
            // Finance role can only see invoiced jobs
            $query->where('status', 'invoiced');
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
        $filterColumns = ['service_advisor', 'foreman', 'department', 'block', 'technician', 'job_type', 'payment_type'];
        foreach ($filterColumns as $filterCol) {
            if ($request->filled("filter_{$filterCol}")) {
                $query->where($filterCol, $request->input("filter_{$filterCol}"));
            }
        }
        
        // Special handling for work_status: first status includes NULL (like Kanban)
        if ($request->filled('filter_work_status')) {
            $workStatusValue = $request->input('filter_work_status');
            $firstStatus = Job::getWorkStatusOptions()->first()?->value;
            
            if ($workStatusValue === $firstStatus) {
                // First status includes jobs with NULL or empty work_status
                $query->where(function($q) use ($workStatusValue) {
                    $q->where('work_status', $workStatusValue)
                      ->orWhereNull('work_status')
                      ->orWhere('work_status', '');
                });
            } else {
                $query->where('work_status', $workStatusValue);
            }
        }
        // Boolean filter for need_part (supports both filter_need_part and need_part params)
        if ($request->filled('filter_need_part')) {
            $query->where('need_part', $request->input('filter_need_part') === '1');
        } elseif ($request->has('need_part') && $request->input('need_part') !== '') {
            $query->where('need_part', $request->input('need_part') == '1');
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

    /**
     * Show the form for creating a new job.
     *
     * @return View The job creation form view
     */
    public function create(): View
    {
        return view('jobs.create');
    }

    /**
     * Store a newly created job in the database.
     *
     * @param StoreJobRequest $request Validated job creation request
     * @param CreateJob $action Job creation action handler
     * @return RedirectResponse Redirects to job detail page with success message
     */
    public function store(StoreJobRequest $request, CreateJob $action): RedirectResponse
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

    /**
     * Display the specified job with related data.
     * 
     * Loads vehicle, remarks, service advisors, and foremen for the detail view.
     * Enforces assignment authorization for role-restricted users.
     *
     * @param Job $job The job to display
     * @return View The job detail view
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 if not authorized
     */
    public function show(Job $job): View
    {
        $this->checkAssignmentAuthorization($job);

        // Track this job as recently viewed
        if (auth()->check()) {
            \App\Models\RecentlyViewed::recordView(auth()->id(), $job->id);
        }

        $job->load(['vehicle', 'remarks']);
        $serviceAdvisors = \App\Models\ServiceAdvisor::orderBy('name')->get();
        $foremen = \App\Models\Foreman::orderBy('name')->get();
        return view('jobs.show', compact('job', 'serviceAdvisors', 'foremen'));
    }

    /**
     * Show the form for editing the specified job.
     * 
     * Redirects to show page which now has inline editing.
     *
     * @param Job $job The job to edit
     * @return RedirectResponse Redirects to job detail page
     */
    public function edit(Job $job): RedirectResponse
    {
        // Redirect to show page which now has inline editing
        return redirect()->route('jobs.show', $job);
    }

    /**
     * Update the specified job in the database.
     * 
     * Tracks changes to key fields (foreman, work status, need_part, customer_name)
     * and logs activities for audit trail.
     *
     * @param UpdateJobRequest $request Validated job update request
     * @param Job $job The job to update
     * @return RedirectResponse Redirects to job detail with success message
     */
    public function update(UpdateJobRequest $request, Job $job): RedirectResponse
    {
        // Track key field changes for activity log
        $oldForeman = $job->foreman;
        $oldWorkStatus = $job->work_status;
        $oldNeedPart = $job->need_part;
        $oldCustomerName = $job->customer_name;
        
        $job->update($request->validated());
        
        // Log activity for key field changes
        if ($oldForeman !== $job->foreman) {
            \App\Models\JobActivity::log($job, 'updated', 
                "Foreman changed from '" . ($oldForeman ?? 'None') . "' to '" . ($job->foreman ?? 'None') . "'",
                ['field' => 'foreman', 'old' => $oldForeman, 'new' => $job->foreman]
            );
        }
        if ($oldWorkStatus !== $job->work_status) {
            \App\Models\JobActivity::log($job, 'work_status_changed', 
                "Work status changed from '" . ($oldWorkStatus ?? 'None') . "' to '" . ($job->work_status ?? 'None') . "'",
                ['old' => $oldWorkStatus, 'new' => $job->work_status]
            );
        }
        if ($oldNeedPart !== $job->need_part) {
            $status = $job->need_part ? 'enabled' : 'disabled';
            \App\Models\JobActivity::log($job, 'updated', 
                "Needs Parts flag {$status}",
                ['field' => 'need_part', 'old' => $oldNeedPart, 'new' => $job->need_part]
            );
        }
        if ($oldCustomerName !== $job->customer_name) {
            \App\Models\JobActivity::log($job, 'updated', 
                "Customer name changed from '{$oldCustomerName}' to '{$job->customer_name}'",
                ['field' => 'customer_name', 'old' => $oldCustomerName, 'new' => $job->customer_name]
            );
        }

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Job updated successfully.');
    }

    /**
     * Remove the specified job from the database.
     * 
     * Requires admin role. Permanently deletes the job.
     *
     * @param Job $job The job to delete
     * @return RedirectResponse Redirects to jobs index with success message
     */
    public function destroy(Job $job): RedirectResponse
    {
        $job->delete();

        return redirect()->route('jobs.index')
            ->with('success', 'Job deleted successfully.');
    }

    /**
     * Add a remark/comment to the specified job.
     * 
     * Creates a timestamped remark with user attribution.
     * Logs activity and notifies assigned users.
     * Supports both regular form submission and AJAX requests.
     *
     * @param Request $request HTTP request containing remark_text
     * @param Job $job The job to add remark to
     * @return JsonResponse|RedirectResponse JSON for AJAX or redirect for form submission
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 if not authorized
     */
    public function addRemark(Request $request, Job $job): JsonResponse|RedirectResponse
    {
        $user = auth()->user();
        
        // Check if user can add remark to this specific job
        if (!$user->canAddRemarkToJob($job)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to add remarks to this job.',
                ], 403);
            }
            abort(403, 'You do not have permission to add remarks to this job.');
        }

        $validated = $request->validate([
            'remark_text' => 'required|string',
            'parent_id' => 'nullable|exists:remarks,id',
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpeg,png,gif,webp|max:10240',
        ]);

        // Process and store images if uploaded
        $imagePaths = [];
        if ($request->hasFile('images')) {
            $imageService = new \App\Services\ImageService();
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $imageService->processAndStore($image, "remarks/{$job->id}");
            }
        }

        // Parse @mentions from text
        $mentionedUserIds = Remark::parseMentions($validated['remark_text']);
        
        // Handle nested replies - force max 1 level of nesting
        $parentId = $validated['parent_id'] ?? null;
        if ($parentId) {
            $parentRemark = Remark::find($parentId);
            // If parent is already a reply, use its parent instead
            if ($parentRemark && $parentRemark->parent_id) {
                $parentId = $parentRemark->parent_id;
            }
        }
        
        // Create the remark
        $remark = $job->remarks()->create([
            'remark_text' => $validated['remark_text'],
            'parent_id' => $parentId,
            'user_id' => $user?->id,
            'created_by' => $user?->name,
            'images' => !empty($imagePaths) ? $imagePaths : null,
            'mentions' => !empty($mentionedUserIds) ? $mentionedUserIds : null,
        ]);
        
        // Update job's latest_remark (for top-level comments only)
        // Wrapped to prevent broadcast failures from breaking response
        if (!$remark->parent_id) {
            try {
                $job->update([
                    'latest_remark' => $validated['remark_text'],
                    'latest_remark_at' => now(),
                ]);
            } catch (\Exception $e) {
                \Log::debug("Job update broadcast failed: " . $e->getMessage());
            }
        }
        
        // Send notifications (all wrapped to prevent broadcast failures from breaking response)
        try {
            // Notify @mentions
            foreach ($mentionedUserIds as $mentionedUserId) {
                if ($mentionedUserId != $user?->id) { // Prevents self-notification
                    Notification::notify(
                        $mentionedUserId,
                        Notification::TYPE_MENTION,
                        "You were mentioned in WIP {$job->job_number}",
                        "@{$user->name}: " . \Illuminate\Support\Str::limit($validated['remark_text'], 80),
                        route('jobs.show', $job) . "#comment-{$remark->id}",
                        'at',
                        'primary'
                    );
                }
            }
            
            // Notify reply parent author
            if ($remark->parent_id) {
                $parentRemark = Remark::find($remark->parent_id);
                // Allow self-notification for reply testing
                if ($parentRemark && $parentRemark->user_id && $parentRemark->user_id != $user?->id) {
                    Notification::notify(
                        $parentRemark->user_id,
                        Notification::TYPE_REPLY,
                        "Someone replied to your comment on WIP {$job->job_number}",
                        "@{$user->name}: " . \Illuminate\Support\Str::limit($validated['remark_text'], 80),
                        route('jobs.show', $job) . "#comment-{$remark->id}",
                        'reply-fill',
                        'info'
                    );
                }
            }
            
            // Notify assigned SA/Foreman
            $job->notifyAssignedUsersPublic($validated['remark_text'], $user?->name, $user?->id);
        } catch (\Exception $e) {
            \Log::debug("Notification failed for remark {$remark->id}: " . $e->getMessage());
        }
        
        // Log activity
        $activityMessage = "Comment added: \"" . \Illuminate\Support\Str::limit($validated['remark_text'], 50) . "\"";
        if (!empty($imagePaths)) {
            $activityMessage .= " (with " . count($imagePaths) . " image(s))";
        }
        if ($remark->parent_id) {
            $activityMessage = "Reply added: \"" . \Illuminate\Support\Str::limit($validated['remark_text'], 50) . "\"";
        }
        \App\Models\JobActivity::log($job, 'remark_added', $activityMessage);
        
        // Load relationships for response
        $remark->load(['user', 'replies']);

        // Check if this is an AJAX request
        if ($request->expectsJson()) {
            $roleColor = match($user?->role) {
                'admin' => 'danger',
                'manager' => 'primary',
                'control_tower' => 'info',
                'sparepart' => 'warning',
                default => 'secondary',
            };

            return response()->json([
                'success' => true,
                'unread_count' => $user->unreadNotifications()->count(),
                'remark' => [
                    'id' => $remark->id,
                    'parent_id' => $remark->parent_id,
                    'text' => $remark->remark_text,
                    'formatted_text' => $remark->formatted_text,
                    'commenter_name' => $remark->commenter_name,
                    'initials' => $remark->commenter_initials,
                    'avatar_color' => sprintf('#%06X', crc32($remark->commenter_name) & 0xFFFFFF),
                    'role_display' => $user?->getRoleDisplayName() ?? 'User',
                    'role_color' => $roleColor,
                    'time_ago' => 'just now',
                    'images' => $remark->image_urls,
                    'can_delete' => $user?->hasRole('admin'),
                ],
            ]);
        }

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Comment added successfully.');
    }

    public function deleteRemark(Remark $remark): RedirectResponse|JsonResponse
    {
        // Authorization: Admin only
        if (!auth()->user()->hasRole('admin')) {
            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            abort(403, 'Unauthorized action.');
        }

        // Delete images from storage
        if ($remark->images) {
            foreach ($remark->images as $path) {
                // Remove 'public/' prefix if exists in path to avoid double prefixing if Storage::delete expects relative to disk root
                // But Laravel Storage::delete('public/foo') works if default disk is local and root is storage/app
                // Let's assume path is like "remarks/1/file.jpg"
                if (Storage::exists("public/{$path}")) {
                    Storage::delete("public/{$path}");
                }
            }
        }

        // Delete the remark
        $remark->delete();

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Comment deleted.');
    }

    /**
     * Mark the specified job as invoiced.
     * 
     * Updates job status, creates invoice record, and adds remark.
     * Requires mark invoiced permission.
     *
     * @param Request $request HTTP request with invoice_number and remark
     * @param Job $job The job to mark as invoiced
     * @param MarkAsInvoiced $action Invoice marking action handler
     * @return RedirectResponse Redirects to job detail with success message
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 if not authorized
     */
    public function markInvoiced(Request $request, Job $job, MarkAsInvoiced $action): RedirectResponse
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

        // Log activity
        \App\Models\JobActivity::log($job, 'invoiced', 
            "Marked as invoiced with invoice #{$validated['invoice_number']}",
            ['invoice_number' => $validated['invoice_number']]
        );

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Job marked as invoiced.');
    }

    /**
     * Update Order & Parts section only.
     * 
     * Designed for Sparepart role to update RQ, Order Part MBINA, and Lain-lain fields.
     * Only allowed on jobs where need_part = true.
     *
     * @param Request $request HTTP request with parts fields (rq, no_order_part_mbina, lain_lain)
     * @param Job $job The job to update parts for
     * @return RedirectResponse Redirects to job detail with success/error message
     */
    public function updateOrderParts(Request $request, Job $job): RedirectResponse
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

        // Track changes for activity log
        $changes = [];
        if ($job->rq !== ($validated['rq'] ?? null)) {
            $changes[] = 'RQ';
        }
        if ($job->no_order_part_mbina !== ($validated['no_order_part_mbina'] ?? null)) {
            $changes[] = 'Order Part MBINA';
        }
        if ($job->lain_lain !== ($validated['lain_lain'] ?? null)) {
            $changes[] = 'Lain-lain';
        }

        $job->update($validated);

        // Log activity
        if (!empty($changes)) {
            \App\Models\JobActivity::log($job, 'parts_updated', 
                "Order & Parts updated: " . implode(', ', $changes),
                $validated
            );
        }

        return redirect()->route('jobs.show', $job)
            ->with('success', 'Order & Parts updated successfully.');
    }

    /**
     * Toggle or set need_part flag for a job via AJAX.
     * Used by job list and job detail for quick "needs parts" toggle.
     * Job will appear in Part Tracking Kanban Pending column.
     *
     * @param Request $request HTTP request with optional need_part value
     * @param Job $job The job to update
     * @return JsonResponse JSON response with success status
     */
    public function updateNeedPart(Request $request, Job $job): JsonResponse
    {
        // Toggle or set to specific value
        $newValue = $request->has('need_part') 
            ? (bool) $request->need_part 
            : !$job->need_part;
        
        $oldValue = $job->need_part;
        
        $job->update(['need_part' => $newValue]);
        
        // Note: We no longer auto-create PartOrder here.
        // The job will appear in Part Tracking Kanban Pending column.
        // User drags to "Buka RQ" column to create RQ with the RQ number.
        
        // Log activity
        $action = $newValue ? 'marked as needing parts' : 'marked as not needing parts';
        \App\Models\JobActivity::log($job, 'need_part_changed', 
            "Job {$action}",
            ['need_part' => $newValue]
        );
        
        return response()->json([
            'success' => true,
            'need_part' => $newValue,
            'message' => $newValue 
                ? 'Job marked as needing parts. It will now appear in Part Tracking Kanban.' 
                : 'Job marked as not needing parts',
        ]);
    }

    /**
     * Bulk update multiple jobs at once.
     * 
     * Supports two actions:
     * - work_status: Update work status for selected jobs
     * - remark: Add same remark to all selected jobs
     *
     * @param Request $request HTTP request with job_ids array, action type, and action-specific data
     * @return RedirectResponse Redirects back with success/error message
     */
    public function bulkUpdate(Request $request): RedirectResponse
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
     * Display Kanban board view for jobs.
     * 
     * Shows uninvoiced jobs organized by work status columns.
     * Supports drag-and-drop status updates. Applies role-based
     * filtering for SA/Foreman roles.
     *
     * @param Request $request HTTP request with optional filters (franchise, service_advisor)
     * @return View The Kanban board view with jobs grouped by status
     */
    public function kanban(Request $request): View
    {
        $user = auth()->user();
        $isFinance = $user->isFinance();
        
        // Get work status options from Job model (hardcoded)
        $workStatuses = Job::getWorkStatusOptions();
        
        // Finance role sees only 3 payment-related columns
        $financeStatuses = ['proses_invoice', 'menunggu_pembayaran', 'sudah_dibayar'];
        if ($isFinance) {
            $workStatuses = $workStatuses->filter(function($status) use ($financeStatuses) {
                return in_array($status->value, $financeStatuses);
            });
        }
        
        // Base query - Finance sees invoiced jobs, others see uninvoiced
        if ($isFinance) {
            $query = Job::where('status', 'invoiced');
        } else {
            $query = Job::uninvoiced();
        }
        
        // Apply role-based restrictions
        if ($user->hasRole('sa')) {
            $saName = $user->serviceAdvisor?->name;
            if ($saName) {
                $query->where('service_advisor', $saName);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->hasRole('foreman')) {
            $foremanNames = $user->foremen()->pluck('name')->toArray();
            if (!empty($foremanNames)) {
                $query->whereIn('foreman', $foremanNames);
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
        if ($request->filled('foreman')) {
            $query->where('foreman', $request->foreman);
        }
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('job_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('job_date', '<=', $request->date_to);
        }
        
        // Get jobs grouped by work status
        $jobs = $query->orderBy('job_date', 'desc')->get();
        
        $jobsByStatus = [];
        $totalsByStatus = [];
        
        // Get the first status value as default for NULL work_status
        $firstStatusValue = $workStatuses->first()?->value;
        
        foreach ($workStatuses as $status) {
            $isFirstColumn = ($status->value === $firstStatusValue);
            
            // Filter jobs for this status
            // Jobs with NULL work_status go to the FIRST column
            // Use normalizeWorkStatus to handle legacy status values
            $statusJobs = $jobs->filter(function($job) use ($status, $isFirstColumn) {
                $jobStatus = $job->work_status;
                
                // If job has no status, put in first column
                if (empty($jobStatus)) {
                    return $isFirstColumn;
                }
                
                // Normalize the job's work status to handle legacy values
                $normalizedJobStatus = Job::normalizeWorkStatus($jobStatus);
                
                return strtolower($normalizedJobStatus) === strtolower($status->value);
            });
            
            $totalsByStatus[$status->value] = $statusJobs->count();
            $jobsByStatus[$status->value] = $statusJobs->take(100); // Show up to 100 per column
        }
        
        // Filter options - get from uninvoiced jobs
        $baseQuery = $isFinance ? Job::invoiced() : Job::uninvoiced();
        $filterOptions = [
            'service_advisor' => (clone $baseQuery)->whereNotNull('service_advisor')
                ->distinct()->pluck('service_advisor')->sort()->values(),
            'foreman' => (clone $baseQuery)->whereNotNull('foreman')
                ->distinct()->pluck('foreman')->sort()->values(),
            'franchise' => ['PC', 'CV'],
            'department' => ['W', 'B'], // Workshop, Body & Paint
        ];
        
        // Check if user can edit Kanban (drag/drop)
        $canEditKanban = $user->canEditKanban();
        
        // Get restricted statuses for this user's role (for visual feedback)
        $restrictedStatuses = Job::getRestrictedStatusesForRole($user->role);
        
        return view('jobs.kanban', compact('workStatuses', 'jobsByStatus', 'totalsByStatus', 'filterOptions', 'canEditKanban', 'isFinance', 'restrictedStatuses'));
    }

    /**
     * Update job work status via AJAX.
     * 
     * Used by Kanban board for drag-and-drop status changes.
     * Logs activity for audit trail.
     *
     * @param Request $request HTTP request with work_status field
     * @param Job $job The job to update
     * @return JsonResponse JSON response with success status and message
     */
    public function updateWorkStatus(Request $request, Job $job): JsonResponse
    {
        $user = auth()->user();
        
        // Check if user can edit Kanban in general
        if (!$user->canEditKanban()) {
            return response()->json([
                'success' => false,
                'message' => 'Your role does not have permission to update work status.',
            ], 403);
        }
        
        // Check if user can update THIS specific job
        $authCheck = $user->canUpdateJobWorkStatus($job);
        if (!$authCheck['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $authCheck['reason'],
            ], 403);
        }
        
        $validated = $request->validate([
            'work_status' => 'required|string',
            'remark' => 'nullable|string|max:1000',
        ]);
        
        $newStatus = $validated['work_status'];
        $remark = $validated['remark'] ?? null;
        
        // Check if this user's role can change to the target status
        if (!Job::canRoleChangeToStatus($user->role, $newStatus)) {
            $reason = Job::getStatusRestrictionReason($user->role, $newStatus);
            return response()->json([
                'success' => false,
                'message' => "Cannot change to this status. {$reason}",
            ], 403);
        }
        
        // Finance role can only change between 3 payment-related statuses (handled via Part Tracking / Finance Kanban)
        if ($user->isFinance()) {
            $allowedStatuses = ['proses_invoice', 'menunggu_pembayaran', 'sudah_dibayar'];
            if (!in_array($newStatus, $allowedStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Finance role can only change status between: ' . implode(', ', $allowedStatuses),
                ], 403);
            }
        }
        
        $oldStatus = $job->work_status;
        $job->update(['work_status' => $newStatus]);
        
        // Get status labels for activity log
        $oldLabel = Job::getWorkStatusMeta($oldStatus)['label'] ?? $oldStatus ?? 'None';
        $newLabel = Job::getWorkStatusMeta($newStatus)['label'] ?? $newStatus;
        
        // Log activity with status change
        $activityMessage = "Work status changed from '{$oldLabel}' to '{$newLabel}'";
        if ($remark) {
            $activityMessage .= " - Remark: {$remark}";
        }
        \App\Models\JobActivity::log($job, 'work_status_changed', $activityMessage, [
            'old' => $oldStatus, 
            'new' => $newStatus,
            'remark' => $remark
        ]);
        
        // If remark provided, also add it as a job remark
        if ($remark) {
            $job->remarks()->create([
                'content' => "[Status Change: {$oldLabel} → {$newLabel}] " . $remark,
                'user_id' => $user->id,
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => "Job {$job->job_number} moved to {$newLabel}",
        ]);
    }

    /**
     * Export job details to PDF/HTML format.
     * 
     * Generates a printable version of the job with all details,
     * remarks, invoices, and activity timeline.
     *
     * @param Job $job The job to export
     * @return \Illuminate\Http\Response HTML response for printing/saving
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 if not authorized
     */
    public function exportPdf(Job $job): \Illuminate\Http\Response
    {
        $this->checkAssignmentAuthorization($job);
        
        $job->load(['vehicle', 'remarks.user', 'invoices', 'activities']);
        
        // Use simple HTML to PDF conversion
        $html = view('exports.job-pdf', compact('job'))->render();
        
        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'inline; filename="job-' . $job->job_number . '.html"');
    }

    /**
     * Search users for @mention autocomplete
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        
        if (strlen($query) < 1) {
            return response()->json([]);
        }
        
        $users = \App\Models\User::where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'role']);
        
        return response()->json($users->map(fn($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'role' => $u->getRoleDisplayName(),
            'initials' => strtoupper(substr($u->name, 0, 2)),
        ]));
    }
}
