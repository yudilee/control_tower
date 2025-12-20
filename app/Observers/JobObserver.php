<?php

namespace App\Observers;

use App\Models\Job;
use App\Models\Notification;

class JobObserver
{
    /**
     * Handle the Job "creating" event.
     */
    public function creating(Job $job): void
    {
        // Auto-set status to uninvoiced for new jobs
        if (!$job->status) {
            $job->status = 'uninvoiced';
        }
        
        // Auto-set work_status to 'pending' if not set
        if (!$job->work_status) {
            $job->work_status = 'pending';
        }
    }

    /**
     * Handle the Job "updating" event.
     */
    public function updating(Job $job): void
    {
        // Auto-transition: When parts are ordered, change to "Waiting Parts"
        if ($job->isDirty('no_order_part_mbina') && $job->no_order_part_mbina && $job->need_part) {
            if ($job->work_status !== 'selesai' && $job->work_status !== 'completed') {
                $job->work_status = 'menunggu_part';
            }
        }
        
        // Auto-transition: When RQ is filled, parts may be arriving
        if ($job->isDirty('rq') && $job->rq && $job->need_part) {
            if ($job->work_status === 'pending') {
                $job->work_status = 'dalam_proses';
            }
        }
        
        // Auto-transition: When need_part is checked, set to waiting if not already progressing
        if ($job->isDirty('need_part') && $job->need_part) {
            if ($job->work_status === 'pending') {
                $job->work_status = 'menunggu_part';
            }
        }
        
        // Auto-transition: When need_part unchecked and was waiting, move to in progress
        if ($job->isDirty('need_part') && !$job->need_part) {
            if ($job->work_status === 'menunggu_part') {
                $job->work_status = 'dalam_proses';
            }
        }
        
        // Auto-transition: When invoice number is set, mark as invoiced
        if ($job->isDirty('invoice_number') && $job->invoice_number && $job->status !== 'invoiced') {
            $job->status = 'invoiced';
            $job->invoiced_at = $job->invoiced_at ?? now();
            $job->work_status = 'selesai';
        }
    }

    /**
     * Handle the Job "updated" event.
     */
    public function updated(Job $job): void
    {
        // Notify when SA/Foreman is assigned to job
        if ($job->wasChanged('service_advisor') && $job->service_advisor) {
            $this->notifyJobAssignment($job, 'service_advisor');
        }
        
        if ($job->wasChanged('foreman') && $job->foreman) {
            $this->notifyJobAssignment($job, 'foreman');
        }
        
        // Notify when work_status changes
        if ($job->wasChanged('work_status')) {
            $this->notifyStatusChange($job);
        }
    }

    /**
     * Notify when a job is assigned to SA or Foreman
     */
    protected function notifyJobAssignment(Job $job, string $field): void
    {
        $assigneeName = $job->$field;
        
        // Find user linked to this SA/Foreman
        if ($field === 'service_advisor') {
            $user = \App\Models\User::whereHas('serviceAdvisor', function($q) use ($assigneeName) {
                $q->where('name', $assigneeName);
            })->first();
        } else {
            $user = \App\Models\User::whereHas('foreman', function($q) use ($assigneeName) {
                $q->where('name', $assigneeName);
            })->first();
        }
        
        if ($user) {
            Notification::notify(
                $user->id,
                Notification::TYPE_JOB_ASSIGNED,
                "Job assigned: {$job->job_number}",
                "You've been assigned to job {$job->job_number} ({$job->plate_number})",
                route('jobs.show', $job->id),
                'person-plus-fill',
                'success'
            );
        }
    }

    /**
     * Notify relevant parties when work status changes
     */
    protected function notifyStatusChange(Job $job): void
    {
        $newStatus = $job->work_status;
        $oldStatus = $job->getOriginal('work_status');
        
        // Only notify for significant status changes
        $significantStatuses = ['selesai', 'completed', 'menunggu_part', 'waiting_parts'];
        
        if (!in_array($newStatus, $significantStatuses)) {
            return;
        }
        
        // Notify admins/managers about completed jobs
        if (in_array($newStatus, ['selesai', 'completed'])) {
            $adminUsers = \App\Models\User::whereIn('role', ['admin', 'manager'])->get();
            
            foreach ($adminUsers as $admin) {
                Notification::notify(
                    $admin->id,
                    Notification::TYPE_SYSTEM,
                    "Job completed: {$job->job_number}",
                    "Work completed on {$job->plate_number}. Ready for invoicing.",
                    route('jobs.show', $job->id),
                    'check-circle-fill',
                    'success'
                );
            }
        }
    }
}
