<?php

namespace App\Models;

use App\Events\JobStatusUpdated;
use App\Events\RemarkAdded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;

use App\Traits\Auditable;

/**
 * Workshop Service Job Model.
 * 
 * Represents a vehicle service job from creation through invoicing.
 * Central entity in the Control Tower system with relationships to
 * vehicles, remarks, invoices, activities, and imports.
 * 
 * @package App\Models
 * 
 * @property int $id
 * @property string|null $job_number WIP number - unique job identifier
 * @property string|null $work_order_number Work order reference
 * @property string|null $job_card Job card number
 * @property string|null $franchise PC (Passenger Car) or CV (Commercial Vehicle)
 * @property string|null $department W (Workshop) or B (Body Paint)
 * @property int|null $vehicle_id Related vehicle ID
 * @property string|null $plate_number Vehicle plate number
 * @property string|null $chassis_number Vehicle chassis/VIN
 * @property string|null $unit_type Vehicle unit type
 * @property string|null $type_unit Alternative unit type field
 * @property string|null $account_no Customer account number
 * @property Carbon|null $date_first_reg Vehicle first registration date
 * @property string|null $customer_name Customer name
 * @property string|null $customer_address Customer address
 * @property string|null $service_advisor Assigned Service Advisor name
 * @property string|null $technician Assigned technician name
 * @property string|null $foreman Assigned Foreman name
 * @property string|null $block Work block/bay assignment
 * @property string|null $job_type Type of service work
 * @property Carbon|null $job_date Job creation date
 * @property Carbon|null $date_in Vehicle entry date
 * @property Carbon|null $date_out Vehicle exit date
 * @property string|null $check_in_time Check-in time
 * @property string|null $payment_type Payment method
 * @property string|null $job_description Description of work
 * @property Carbon|null $deadline Job deadline
 * @property Carbon|null $promise_date Promised completion date
 * @property float|null $estimated_amount Estimated job value
 * @property float|null $labour_sales Labour charges
 * @property float|null $part_sales Parts charges
 * @property float|null $total_sales Total job value
 * @property string|null $rq Requisition number for parts
 * @property string|null $no_order_part_mbina MBINA parts order number
 * @property string|null $lain_lain Other notes
 * @property string $status Job status: uninvoiced, invoiced
 * @property string|null $work_status Current work status from dropdown
 * @property bool $need_part Whether job requires spare parts
 * @property string|null $description Additional description
 * @property string|null $latest_remark Latest remark text (cached)
 * @property Carbon|null $latest_remark_at Latest remark timestamp
 * @property string|null $update_remarks Update remarks
 * @property Carbon|null $update_at Update timestamp
 * @property string|null $invoice_number Invoice number when invoiced
 * @property Carbon|null $invoice_date Invoice date
 * @property string|null $type_sale Sale type: INT, WAR, CASH
 * @property float|null $inv_amount Invoice amount
 * @property float|null $inv_ppn PPN (tax) amount
 * @property float|null $inv_ppn_meterai Meterai stamp duty
 * @property Carbon|null $invoiced_at When job was invoiced
 * @property int|null $import_id Source import ID
 * @property bool $is_dummy_wip Whether this is a dummy WIP record
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property-read Vehicle|null $vehicle Related vehicle
 * @property-read Collection<Remark> $remarks Job remarks/comments
 * @property-read Collection<JobInvoice> $invoices Invoice records
 * @property-read Collection<JobActivity> $activities Activity timeline
 * @property-read Import|null $import Source import record
 * @property-read float $total_invoice_amount Computed sum of invoices
 * @property-read string $department_label Human-readable department
 * @property-read string $type_sale_label Human-readable type sale
 * @property-read Remark|null $first_remark Oldest remark
 * @property-read Remark|null $latest_remark_from_table Newest remark
 * @property-read string|null $first_remark_text First remark text
 * @property-read string|null $update_remark_text Latest remark text
 * @property-read Carbon|null $last_remark_updated Latest remark date
 */
class Job extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'job_number',
        'work_order_number',
        'job_card',
        'franchise',
        'department',
        'vehicle_id',
        'plate_number',
        'chassis_number',
        'unit_type',
        'type_unit',
        'account_no',
        'date_first_reg',
        'customer_name',
        'customer_id',
        'customer_address',
        'service_advisor',
        'technician',
        'foreman',
        'block',
        'job_type',
        'job_date',
        'date_in',
        'date_out',
        'check_in_time',
        'payment_type',
        'job_description',
        'deadline',
        'promise_date',
        'estimated_amount',
        'labour_sales',
        'part_sales',
        'total_sales',
        'rq',
        'no_order_part_mbina',
        'lain_lain',
        'status',
        'work_status',
        'need_part',
        'description',
        'latest_remark',
        'latest_remark_at',
        'update_remarks',
        'update_at',
        'invoice_number',
        'invoice_date',
        'type_sale',
        'inv_amount',
        'inv_ppn',
        'inv_ppn_meterai',
        'invoiced_at',
        'import_id',
        'is_dummy_wip',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'job_date' => 'date',
            'date_in' => 'date',
            'date_out' => 'date',
            'invoice_date' => 'date',
            'deadline' => 'date',
            'promise_date' => 'date',
            'date_first_reg' => 'date',
            'estimated_amount' => 'decimal:2',
            'labour_sales' => 'decimal:2',
            'part_sales' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'inv_amount' => 'decimal:2',
            'inv_ppn' => 'decimal:2',
            'inv_ppn_meterai' => 'decimal:2',
            'latest_remark_at' => 'datetime',
            'update_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'need_part' => 'boolean',
            'is_dummy_wip' => 'boolean',
        ];
    }

    // Work Statuses (13 Steps)
    const WORK_STATUSES = [
        '1. Belum diproses (Tunggu Antrian)',
        '2. Pengerjaan Diagnosa Awal',
        '3. Estimasi (Proses Warranty -> Tips case, Eskulab, Xsp)',
        '4. Acc Customer/Warranty',
        '5. Buka RQ (Qrder Parts)',
        '6. Parts Datang (Parts Received)',
        '7. Penjadwalan (Unit dibawa customer)',
        '8. Pengerjaan',
        '9. Pemberkasan (Body Paint/Cash/Warranty)',
        '10. Proses Close Job (Pengerjaan selesai)',
        '11. Proses Invoice',
        '12. Menunggu Pembayaran',
        '13. Sudah Dibayar',
    ];

    // Work Status Display Metadata (labels, icons, colors for UI)
    const WORK_STATUS_META = [
        '1. Belum diproses (Tunggu Antrian)' => ['label' => '1. Belum diproses', 'icon' => 'inbox', 'color' => 'secondary'],
        '2. Pengerjaan Diagnosa Awal' => ['label' => '2. Diagnosa Awal', 'icon' => 'search', 'color' => 'info'],
        '3. Estimasi (Proses Warranty -> Tips case, Eskulab, Xsp)' => ['label' => '3. Estimasi', 'icon' => 'calculator', 'color' => 'warning'],
        '4. Acc Customer/Warranty' => ['label' => '4. Acc Customer', 'icon' => 'hand-thumbs-up', 'color' => 'success'],
        '5. Buka RQ (Qrder Parts)' => ['label' => '5. Buka RQ', 'icon' => 'cart', 'color' => 'secondary'],
        '6. Parts Datang (Parts Received)' => ['label' => '6. Parts Datang', 'icon' => 'box-seam', 'color' => 'primary'],
        '7. Penjadwalan (Unit dibawa customer)' => ['label' => '7. Penjadwalan', 'icon' => 'calendar-date', 'color' => 'info'],
        '8. Pengerjaan' => ['label' => '8. Pengerjaan', 'icon' => 'tools', 'color' => 'primary'],
        '9. Pemberkasan (Body Paint/Cash/Warranty)' => ['label' => '9. Pemberkasan', 'icon' => 'folder', 'color' => 'secondary'],
        '10. Proses Close Job (Pengerjaan selesai)' => ['label' => '10. Proses Close', 'icon' => 'check-lg', 'color' => 'success'],
        '11. Proses Invoice' => ['label' => '11. Invoice', 'icon' => 'receipt', 'color' => 'info'],
        '12. Menunggu Pembayaran' => ['label' => '12. Tunggu Bayar', 'icon' => 'hourglass', 'color' => 'warning'],
        '13. Sudah Dibayar' => ['label' => '13. Lunas', 'icon' => 'cash-coin', 'color' => 'success'],
    ];

    // Work statuses that are auto-updated from other systems (cannot be manually changed on Kanban)
    // These statuses are controlled by: Part Tracking (5, 6) and Finance Kanban (11, 12, 13)
    const PROTECTED_WORK_STATUSES = [
        '5. Buka RQ (Qrder Parts)' => 'Auto-updated from Part Tracking when parts are ordered.',
        '6. Parts Datang (Parts Received)' => 'Auto-updated from Part Tracking when parts are received.',
        '11. Proses Invoice' => 'Auto-updated from Finance Kanban when invoice is created.',
        '12. Menunggu Pembayaran' => 'Auto-updated from Finance Kanban when invoice is pending payment.',
        '13. Sudah Dibayar' => 'Auto-updated from Finance Kanban when invoice is paid.',
    ];

    // Role-based work status restrictions
    // Key = role, Value = array of status indices that role CANNOT manually change to
    const ROLE_RESTRICTED_STATUSES = [
        'control_tower' => [5, 6, 11, 12, 13],  // Can't change to part tracking or finance statuses
        'foreman' => [5, 6, 11, 12, 13], // Can't change to part tracking or finance (Allowed: Pemberkasan, Close Job)
        'sa' => [5, 6, 9, 10, 11, 12, 13],      // Same as foreman
        'sparepart' => [1, 2, 3, 4, 7, 8, 9, 10, 11, 12, 13], // Can only work with 5 and 6 (handled by Part Tracking)
    ];

    /**
     * Check if a work status is protected (auto-updated by other systems)
     */
    public static function isProtectedWorkStatus(string $status): bool
    {
        return array_key_exists($status, self::PROTECTED_WORK_STATUSES);
    }

    /**
     * Get the reason why a status is protected
     */
    public static function getProtectedStatusReason(string $status): ?string
    {
        return self::PROTECTED_WORK_STATUSES[$status] ?? null;
    }

    /**
     * Get list of work statuses that a specific role is NOT allowed to manually change to
     * Returns array of full status strings
     */
    public static function getRestrictedStatusesForRole(string $role): array
    {
        $restrictedIndices = self::ROLE_RESTRICTED_STATUSES[$role] ?? [];
        
        if (empty($restrictedIndices)) {
            return []; // No restrictions (admin, manager have full access)
        }
        
        $restricted = [];
        foreach ($restrictedIndices as $index) {
            // WORK_STATUSES is 0-indexed, but we use 1-based indices for display
            $actualIndex = $index - 1;
            if (isset(self::WORK_STATUSES[$actualIndex])) {
                $restricted[] = self::WORK_STATUSES[$actualIndex];
            }
        }
        
        return $restricted;
    }

    /**
     * Check if a role is allowed to change to a specific work status
     */
    public static function canRoleChangeToStatus(string $role, string $status): bool
    {
        $restrictedStatuses = self::getRestrictedStatusesForRole($role);
        return !in_array($status, $restrictedStatuses);
    }

    /**
     * Get the reason why a role cannot change to a status
     */
    public static function getStatusRestrictionReason(string $role, string $status): ?string
    {
        if (self::canRoleChangeToStatus($role, $status)) {
            return null;
        }
        
        // Determine reason based on status
        if (in_array($status, [self::WORK_STATUSES[4], self::WORK_STATUSES[5]])) {
            return "Status is auto-updated from Part Tracking Kanban.";
        }
        if (in_array($status, [self::WORK_STATUSES[10], self::WORK_STATUSES[11], self::WORK_STATUSES[12]])) {
            return "Status is auto-updated from Finance Kanban.";
        }
        
        return "Your role does not have permission to change to this status.";
    }

    // Legacy status mapping - maps old database values to new status values
    // Used for counting jobs with old status values in dashboard/reports
    const LEGACY_STATUS_MAP = [
        // First status - various legacy names
        'belum_diproses' => '1. Belum diproses (Tunggu Antrian)',
        'pending' => '1. Belum diproses (Tunggu Antrian)',
        'new' => '1. Belum diproses (Tunggu Antrian)',
        'baru' => '1. Belum diproses (Tunggu Antrian)',
        'antrian' => '1. Belum diproses (Tunggu Antrian)',
        'tunggu_antrian' => '1. Belum diproses (Tunggu Antrian)',
        // Diagnosa
        'keluhan_awal' => '2. Pengerjaan Diagnosa Awal',
        'diagnosa' => '2. Pengerjaan Diagnosa Awal',
        'diagnosa_awal' => '2. Pengerjaan Diagnosa Awal',
        'inspection' => '2. Pengerjaan Diagnosa Awal',
        // Estimasi
        'estimasi' => '3. Estimasi (Proses Warranty -> Tips case, Eskulab, Xsp)',
        'estimate' => '3. Estimasi (Proses Warranty -> Tips case, Eskulab, Xsp)',
        'quotation' => '3. Estimasi (Proses Warranty -> Tips case, Eskulab, Xsp)',
        // Acc Customer
        'acc_customer' => '4. Acc Customer/Warranty',
        'approved' => '4. Acc Customer/Warranty',
        'customer_approved' => '4. Acc Customer/Warranty',
        // Order Parts / Buka RQ
        'order_parts' => '5. Buka RQ (Qrder Parts)',
        'buka_rq' => '5. Buka RQ (Qrder Parts)',
        'parts_order' => '5. Buka RQ (Qrder Parts)',
        'needs_attention' => '5. Buka RQ (Qrder Parts)',  // Flagged jobs needing parts/attention
        'need_parts' => '5. Buka RQ (Qrder Parts)',
        'waiting_parts' => '5. Buka RQ (Qrder Parts)',
        // Parts Received
        'parts_received' => '6. Parts Datang (Parts Received)',
        'parts_datang' => '6. Parts Datang (Parts Received)',
        'parts_arrived' => '6. Parts Datang (Parts Received)',
        // Penjadwalan
        'penjadwalan' => '7. Penjadwalan (Unit dibawa customer)',
        'scheduled' => '7. Penjadwalan (Unit dibawa customer)',
        'scheduling' => '7. Penjadwalan (Unit dibawa customer)',
        // Pengerjaan
        'pengerjaan' => '8. Pengerjaan',
        'in_progress' => '8. Pengerjaan',
        'working' => '8. Pengerjaan',
        'wip' => '8. Pengerjaan',
        // Pemberkasan
        'pemberkasan' => '9. Pemberkasan (Body Paint/Cash/Warranty)',
        'documentation' => '9. Pemberkasan (Body Paint/Cash/Warranty)',
        // Proses Close
        'proses_close' => '10. Proses Close Job (Pengerjaan selesai)',
        'close_job' => '10. Proses Close Job (Pengerjaan selesai)',
        'closing' => '10. Proses Close Job (Pengerjaan selesai)',
        'done' => '10. Proses Close Job (Pengerjaan selesai)',
        // Proses Invoice
        'proses_invoice' => '11. Proses Invoice',
        'invoicing' => '11. Proses Invoice',
        // Menunggu Pembayaran
        'menunggu_pembayaran' => '12. Menunggu Pembayaran',
        'waiting_payment' => '12. Menunggu Pembayaran',
        'unpaid' => '12. Menunggu Pembayaran',
        // Sudah Dibayar
        'sudah_dibayar' => '13. Sudah Dibayar',
        'completed' => '13. Sudah Dibayar',
        'paid' => '13. Sudah Dibayar',
        'lunas' => '13. Sudah Dibayar',
    ];

    /**
     * Normalize a work status value - maps legacy values to current standards
     */
    public static function normalizeWorkStatus(?string $status): ?string
    {
        if (empty($status)) {
            return null;
        }
        // If already in new format, return as-is
        if (in_array($status, self::WORK_STATUSES)) {
            return $status;
        }
        // Try legacy mapping
        return self::LEGACY_STATUS_MAP[$status] ?? $status;
    }

    /**
     * Get work status options for dropdowns/views (replaces DropdownOption::getOptions('work_status'))
     * Returns a collection of objects with id, value, label, icon, color, sort_order properties
     */
    public static function getWorkStatusOptions(): \Illuminate\Support\Collection
    {
        return collect(self::WORK_STATUSES)->map(function ($value, $index) {
            $meta = self::WORK_STATUS_META[$value] ?? ['label' => $value, 'icon' => 'circle', 'color' => 'secondary'];
            return (object) [
                'id' => $index + 1, // 1-based id for compatibility
                'value' => $value,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'color' => $meta['color'],
                'sort_order' => $index + 1,
            ];
        })->values();
    }

    /**
     * Get metadata for a specific work status
     */
    public static function getWorkStatusMeta(string $status): array
    {
        return self::WORK_STATUS_META[$status] ?? ['label' => $status, 'icon' => 'circle', 'color' => 'secondary'];
    }

    /**
     * Boot the model and register events.
     */
    protected static function booted(): void
    {
        // Clear dashboard cache and broadcast update when jobs change
        $handleDashboardUpdate = function () {
            // Clear dashboard cache immediately for fresh data
            \App\Http\Controllers\DashboardController::clearCache();
            
            // Debounce broadcast: only once per second to prevent spam
            $cacheKey = 'dashboard_broadcast_pending';
            if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 1);
                try {
                    event(new \App\Events\DashboardUpdated());
                } catch (\Exception $e) {
                    // Ignore broadcast errors (e.g. connection refused in local dev)
                    \Illuminate\Support\Facades\Log::warning('Dashboard broadcast failed: ' . $e->getMessage());
                }
            }
        };

        static::created($handleDashboardUpdate);
        static::updated(function ($job) use ($handleDashboardUpdate) {
            // Clear cache and broadcast for any job update
            $handleDashboardUpdate();
        });
        static::deleted($handleDashboardUpdate);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The customer associated with this job (via customer_id FK)
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(Remark::class)->orderBy('created_at', 'desc');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /**
     * Get all activities (timeline) for this job.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(JobActivity::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get all invoices for this job
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(JobInvoice::class)->orderBy('invoice_date', 'desc');
    }

    /**
     * Get all part orders for this job
     */
    public function partOrders(): HasMany
    {
        return $this->hasMany(PartOrder::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get pending part orders count
     */
    public function getPendingPartOrdersCountAttribute(): int
    {
        return $this->partOrders()->pending()->count();
    }

    /**
     * Get total invoice amount (sum of all invoices minus credit notes)
     */
    public function getTotalInvoiceAmountAttribute(): float
    {
        return $this->invoices->sum('effective_amount');
    }

    /**
     * Get department label
     */
    public function getDepartmentLabelAttribute(): string
    {
        return match(strtoupper($this->department ?? '')) {
            'W' => 'Workshop',
            'B' => 'Body Paint',
            default => $this->department ?? '-',
        };
    }

    /**
     * Get type sale label
     */
    public function getTypeSaleLabelAttribute(): string
    {
        return match(strtoupper($this->type_sale ?? '')) {
            'INT' => 'Internal',
            'WAR' => 'Warranty',
            'CASH' => 'Cash',
            default => $this->type_sale ?? '-',
        };
    }

    public function scopeUninvoiced($query)
    {
        return $query->where('status', 'uninvoiced');
    }

    public function scopeInvoiced($query)
    {
        return $query->where('status', 'invoiced');
    }

    public function scopeNeedsParts($query)
    {
        return $query->where('need_part', true);
    }

    // Get the first remark (oldest) from the remarks table
    public function getFirstRemarkAttribute()
    {
        return $this->remarks()->orderBy('created_at', 'asc')->first();
    }

    // Get the latest remark from the remarks table
    public function getLatestRemarkFromTableAttribute()
    {
        return $this->remarks()->orderBy('created_at', 'desc')->first();
    }

    // Get first remark text
    public function getFirstRemarkTextAttribute()
    {
        $remark = $this->firstRemark;
        return $remark ? $remark->remark_text : null;
    }

    // Get latest remark text
    public function getUpdateRemarkTextAttribute()
    {
        $remark = $this->latestRemarkFromTable;
        return $remark ? $remark->remark_text : null;
    }

    // Get latest remark date
    public function getLastRemarkUpdatedAttribute()
    {
        $remark = $this->latestRemarkFromTable;
        return $remark ? $remark->created_at : null;
    }

    public function addRemark(string $remarkText, ?string $createdBy = null, ?int $userId = null): Remark
    {
        $remark = $this->remarks()->create([
            'remark_text' => $remarkText,
            'created_by' => $createdBy,
            'user_id' => $userId,
        ]);

        $this->update([
            'latest_remark' => $remarkText,
            'latest_remark_at' => now(),
        ]);

        // Notify assigned SA/Foreman about new remark (if different from creator)
        // Wrapped to prevent notification failures from bubbling up
        try {
            $this->notifyAssignedUsersPublic($remarkText, $createdBy, $userId);
        } catch (\Exception $e) {
            \Log::debug("Notification failed for job {$this->job_number}: " . $e->getMessage());
        }

        // Broadcast real-time update (wrapped to prevent Pusher failures)
        try {
            event(new RemarkAdded($this, $remark));
        } catch (\Exception $e) {
            \Log::debug("Broadcast failed for job {$this->job_number}: " . $e->getMessage());
        }

        return $remark;
    }

    /**
     * Notify SA and Foreman about activity on their job
     */
    public function notifyAssignedUsersPublic(string $remarkText, ?string $createdBy, ?int $creatorUserId): void
    {
        $usersToNotify = [];
        
        // Find SA user
        if ($this->service_advisor) {
            $saUser = User::whereHas('serviceAdvisor', function($q) {
                $q->where('name', $this->service_advisor);
            })->first();
            
            if ($saUser && $saUser->id !== $creatorUserId) {
                $usersToNotify[] = $saUser;
            }
        }
        
        // Find Foreman user
        if ($this->foreman) {
            $foremanUser = User::whereHas('foreman', function($q) {
                $q->where('name', $this->foreman);
            })->first();
            
            if ($foremanUser && $foremanUser->id !== $creatorUserId) {
                $usersToNotify[] = $foremanUser;
            }
        }
        
        foreach ($usersToNotify as $user) {
            Notification::notify(
                $user->id,
                Notification::TYPE_REMARK_ADDED,
                "New remark on {$this->job_number}",
                \Illuminate\Support\Str::limit($remarkText, 100) . " — by {$createdBy}",
                route('jobs.show', $this->id),
                'chat-text-fill',
                'info'
            );
        }
    }

    public function markAsInvoiced(string $invoiceNumber): void
    {
        $this->update([
            'status' => 'invoiced',
            'invoice_number' => $invoiceNumber,
            'invoiced_at' => now(),
        ]);

        // Broadcast real-time update (wrapped to prevent Pusher failures)
        try {
            event(new JobStatusUpdated($this, 'invoiced', auth()->user()?->name));
        } catch (\Exception $e) {
            \Log::debug("Broadcast failed for job {$this->job_number} status update: " . $e->getMessage());
        }
    }
}
