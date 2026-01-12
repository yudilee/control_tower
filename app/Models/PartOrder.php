<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'part_name',
        'part_number',
        'quantity',
        'rq',
        'no_order_part',
        'notes',
        'order_date',
        'expected_date',
        'received_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
        'quantity' => 'integer',
    ];

    // Status constants - workflow order
    const STATUS_PENDING = 'pending';     // Job needs parts, waiting to open RQ
    const STATUS_BUKA_RQ = 'buka_rq';     // RQ opened, waiting to order from supplier
    const STATUS_ORDERED = 'ordered';     // Order placed with supplier (has no_order_part)
    const STATUS_CONFIRMED = 'confirmed'; // Supplier confirmed the order
    const STATUS_SHIPPED = 'shipped';     // Parts shipped
    const STATUS_RECEIVED = 'received';   // Parts received at workshop
    const STATUS_INSTALLED = 'installed'; // Parts installed on vehicle
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get all available statuses in workflow order
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => ['label' => 'Pending', 'color' => '#f59e0b', 'icon' => 'bi-hourglass-split'],
            self::STATUS_BUKA_RQ => ['label' => 'Buka RQ', 'color' => '#06b6d4', 'icon' => 'bi-file-plus'],
            self::STATUS_ORDERED => ['label' => 'Ordered', 'color' => '#6b7280', 'icon' => 'bi-cart'],
            self::STATUS_CONFIRMED => ['label' => 'Confirmed', 'color' => '#3b82f6', 'icon' => 'bi-check-circle'],
            self::STATUS_SHIPPED => ['label' => 'Shipped', 'color' => '#8b5cf6', 'icon' => 'bi-truck'],
            self::STATUS_RECEIVED => ['label' => 'Received', 'color' => '#22c55e', 'icon' => 'bi-box-seam'],
            self::STATUS_INSTALLED => ['label' => 'Installed', 'color' => '#10b981', 'icon' => 'bi-check2-all'],
            self::STATUS_CANCELLED => ['label' => 'Cancelled', 'color' => '#ef4444', 'icon' => 'bi-x-circle'],
        ];
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status]['label'] ?? $this->status;
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return self::getStatuses()[$this->status]['color'] ?? '#6b7280';
    }

    /**
     * Get status icon
     */
    public function getStatusIconAttribute(): string
    {
        return self::getStatuses()[$this->status]['icon'] ?? 'bi-circle';
    }

    /**
     * Calculate days until expected date
     */
    public function getDaysUntilExpectedAttribute(): int
    {
        if (!$this->expected_date) {
            return 0;
        }
        return now()->startOfDay()->diffInDays($this->expected_date, false);
    }

    /**
     * Check if overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->expected_date) {
            return false;
        }
        return $this->days_until_expected < 0 && 
               !in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_INSTALLED, self::STATUS_CANCELLED]);
    }

    /**
     * Check if due soon (within 7 days)
     */
    public function getIsDueSoonAttribute(): bool
    {
        $days = $this->days_until_expected;
        return $days >= 0 && $days <= 7 && 
               !in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_INSTALLED, self::STATUS_CANCELLED]);
    }

    // Relationships

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->whereNotIn('status', [self::STATUS_INSTALLED, self::STATUS_CANCELLED]);
    }

    public function scopeOverdue($query)
    {
        return $query->pending()
            ->where('expected_date', '<', now()->startOfDay());
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->pending()
            ->where('expected_date', '>=', now()->startOfDay())
            ->where('expected_date', '<=', now()->addDays($days)->endOfDay());
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
