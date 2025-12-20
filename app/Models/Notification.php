<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'icon',
        'color',
        'link',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    // Notification types
    const TYPE_STALE_JOB = 'stale_job';
    const TYPE_PARTS_ARRIVED = 'parts_arrived';
    const TYPE_REMARK_ADDED = 'remark_added';
    const TYPE_JOB_ASSIGNED = 'job_assigned';
    const TYPE_SYSTEM = 'system';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Create a notification for a user
     */
    public static function notify(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        ?string $icon = null,
        string $color = 'primary',
        ?array $data = null
    ): self {
        // Default icons per type
        $defaultIcons = [
            self::TYPE_STALE_JOB => 'clock-history',
            self::TYPE_PARTS_ARRIVED => 'box-seam-fill',
            self::TYPE_REMARK_ADDED => 'chat-text-fill',
            self::TYPE_JOB_ASSIGNED => 'person-plus-fill',
            self::TYPE_SYSTEM => 'info-circle-fill',
        ];

        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'icon' => $icon ?? ($defaultIcons[$type] ?? 'bell-fill'),
            'color' => $color,
            'data' => $data,
        ]);
    }

    /**
     * Notify all users with a specific role
     */
    public static function notifyRole(
        string $role,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        ?string $icon = null,
        string $color = 'primary',
        ?array $data = null
    ): int {
        $users = User::where('role', $role)->get();
        $count = 0;
        
        foreach ($users as $user) {
            self::notify($user->id, $type, $title, $message, $link, $icon, $color, $data);
            $count++;
        }
        
        return $count;
    }

    /**
     * Notify all admin users
     */
    public static function notifyAdmins(
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        ?string $icon = null,
        string $color = 'primary',
        ?array $data = null
    ): int {
        return self::notifyRole('admin', $type, $title, $message, $link, $icon, $color, $data);
    }
}
