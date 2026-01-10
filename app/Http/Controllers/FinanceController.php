<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    /**
     * Display Finance Kanban board (Steps 10-13)
     */
    public function kanban()
    {
        // Define Finance specific statuses (Indices 9, 10, 11, 12 from WORK_STATUSES)
        // 9: 10. Proses Close Job
        // 10: 11. Proses Invoice
        // 11: 12. Menunggu Pembayaran
        // 12: 13. Sudah Dibayar
        $financeIndices = [9, 10, 11, 12];
        $statuses = [];
        
        foreach ($financeIndices as $index) {
            if (isset(Job::WORK_STATUSES[$index])) {
                $statuses[] = Job::WORK_STATUSES[$index];
            }
        }

        // Fetch jobs in these statuses
        $jobs = Job::whereIn('work_status', $statuses)
            ->orderBy('updated_at', 'desc')
            ->get();

        // Group by status
        $jobsByStatus = [];
        foreach ($statuses as $status) {
            $jobsByStatus[$status] = $jobs->where('work_status', $status);
        }

        return view('finance.kanban', compact('statuses', 'jobsByStatus', 'jobs'));
    }

    /**
     * Update Job Work Status with Mandatory Remark
     */
    public function updateStatus(Request $request, Job $job): JsonResponse
    {
        $validated = $request->validate([
            'work_status' => 'required|string',
            'remark' => 'required|string|min:3',
        ]);

        $oldStatus = $job->work_status;
        $newStatus = $validated['work_status'];

        // Validate status is within Finance scope
        // (Indices 9-12 from Job::WORK_STATUSES)
        $allowedStatuses = [
            Job::WORK_STATUSES[9],
            Job::WORK_STATUSES[10],
            Job::WORK_STATUSES[11],
            Job::WORK_STATUSES[12],
        ];

        if (!in_array($newStatus, $allowedStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status for Finance Kanban',
            ], 422);
        }

        // Update Job
        $job->update(['work_status' => $newStatus]);

        // Add Remark
        $job->addRemark(
            "Finance Status Update ({$oldStatus} -> {$newStatus}): " . $validated['remark'],
            auth()->user()->name,
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => "Job moved to {$newStatus}",
            'job' => $job->fresh(),
        ]);
    }
}
