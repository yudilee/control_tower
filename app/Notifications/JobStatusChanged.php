<?php

namespace App\Notifications;

use App\Models\Job;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    protected Job $job;
    protected string $changeType;
    protected ?string $oldValue;
    protected ?string $newValue;
    protected ?string $changedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(Job $job, string $changeType, ?string $oldValue = null, ?string $newValue = null, ?string $changedBy = null)
    {
        $this->job = $job;
        $this->changeType = $changeType;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->changedBy = $changedBy ?? auth()->user()?->name ?? 'System';
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = "Job #{$this->job->job_number} - {$this->getChangeTitle()}";
        
        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($this->getChangeDescription())
            ->line("**Job Details:**")
            ->line("• WIP: {$this->job->job_number}")
            ->line("• Plate: {$this->job->plate_number}")
            ->line("• Customer: {$this->job->customer_name}")
            ->line("• Changed by: {$this->changedBy}")
            ->action('View Job Details', route('jobs.show', $this->job))
            ->line('Thank you for using Control Tower!');

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'job_id' => $this->job->id,
            'job_number' => $this->job->job_number,
            'change_type' => $this->changeType,
            'old_value' => $this->oldValue,
            'new_value' => $this->newValue,
            'changed_by' => $this->changedBy,
            'message' => $this->getChangeDescription(),
        ];
    }

    /**
     * Get the title for the change type.
     */
    protected function getChangeTitle(): string
    {
        return match ($this->changeType) {
            'work_status' => 'Work Status Changed',
            'foreman_assigned' => 'Foreman Assigned',
            'invoiced' => 'Marked as Invoiced',
            'remark_added' => 'New Remark Added',
            default => 'Status Updated',
        };
    }

    /**
     * Get the description for the change.
     */
    protected function getChangeDescription(): string
    {
        return match ($this->changeType) {
            'work_status' => "Work status changed from '{$this->oldValue}' to '{$this->newValue}'.",
            'foreman_assigned' => "Foreman assigned: {$this->newValue}",
            'invoiced' => "Job has been marked as invoiced with invoice #{$this->newValue}.",
            'remark_added' => "A new remark was added to the job.",
            default => "Job status has been updated.",
        };
    }

    /**
     * Send notification to SA and Foreman for a job.
     */
    public static function notifyJobStakeholders(Job $job, string $changeType, ?string $oldValue = null, ?string $newValue = null): void
    {
        $notification = new self($job, $changeType, $oldValue, $newValue);
        
        // Notify Service Advisor
        if ($job->service_advisor) {
            $sa = \App\Models\ServiceAdvisor::where('name', $job->service_advisor)->first();
            if ($sa && $sa->user) {
                $sa->user->notify($notification);
            }
        }
        
        // Notify Foreman
        if ($job->foreman) {
            $foreman = \App\Models\Foreman::where('name', $job->foreman)->first();
            if ($foreman && $foreman->user) {
                $foreman->user->notify($notification);
            }
        }
    }
}
