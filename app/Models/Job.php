<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\Auditable; // Add this line

class Job extends Model
{
    use HasFactory, Auditable; // Add this trait

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

    protected $casts = [
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

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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
     * Get all invoices for this job
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(JobInvoice::class)->orderBy('invoice_date', 'desc');
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
        $this->notifyAssignedUsers($remarkText, $createdBy, $userId);

        return $remark;
    }

    /**
     * Notify SA and Foreman about activity on their job
     */
    protected function notifyAssignedUsers(string $remarkText, ?string $createdBy, ?int $creatorUserId): void
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
    }
}
