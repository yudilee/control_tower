<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\Auditable;

class Vehicle extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'plate_number',
        'model',
        'year',
        'vin',
        'customer_name',
        'customer_phone',
        'customer_id',
        'is_in_workshop',
        'import_id',
        // DMS fields
        'dms_magic', 'customer_dms_magic',
        'franchise', 'variant', 'description',
        'mhl_number', 'engine_number',
        'registration_date', 'last_service_date',
        'dms_imported_at',
    ];

    protected function casts(): array
    {
        return [
            'is_in_workshop' => 'boolean',
        ];
    }

    /**
     * The customer who owns this vehicle (via customer_id FK)
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'plate_number', 'plate_number');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
