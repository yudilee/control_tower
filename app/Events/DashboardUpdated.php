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
        // Calculate fresh stats - must match DashboardController exactly
        $this->stats = [
            'uninvoiced' => Job::uninvoiced()->count(),
            'invoiced' => Job::invoiced()->count(),
            'needs_parts' => Job::uninvoiced()->needsParts()->count(),
            'in_workshop' => \App\Models\Vehicle::where('is_in_workshop', true)->count(),
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
