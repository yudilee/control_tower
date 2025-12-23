<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Job;

class DashboardUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $stats;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        // Calculate fresh stats
        $this->stats = [
            'uninvoiced' => Job::uninvoiced()->count(),
            'invoiced' => Job::invoiced()->count(),
            'needs_parts' => Job::where('need_part', true)->uninvoiced()->count(),
            'in_workshop' => Job::whereNotNull('date_in')->whereNull('date_out')->count(),
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('dashboard'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'stats-updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'stats' => $this->stats,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
