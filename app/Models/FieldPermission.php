<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldPermission extends Model
{
    protected $fillable = [
        'role_id',
        'doctype',
        'field',
        'can_read',
        'can_write',
    ];

    protected $casts = [
        'can_read' => 'boolean',
        'can_write' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get editable fields for each DocType
     */
    public static function getFieldsForDocType(string $doctype): array
    {
        $fields = [
            'Job' => [
                'wip' => 'WIP Number',
                'customer_name' => 'Customer Name',
                'vehicle_id' => 'Vehicle',
                'service_advisor_id' => 'Service Advisor',
                'foreman_id' => 'Foreman',
                'unit' => 'Unit Price',
                'labour' => 'Labour',
                'part' => 'Part',
                'total' => 'Total',
                'rq_no' => 'RQ Number',
                'order_part' => 'Order Part MBINA',
                'other_part' => 'Lain-lain',
                'need_part' => 'Need Part',
                'status' => 'Status',
                'invoiced' => 'Invoiced',
                'invoice_no' => 'Invoice No',
                'invoice_date' => 'Invoice Date',
            ],
            'Vehicle' => [
                'plate_no' => 'Plate Number',
                'vin' => 'VIN',
                'model' => 'Model',
                'year' => 'Year',
                'color' => 'Color',
            ],
            'Booking' => [
                'booking_code' => 'Booking Code',
                'customer_name' => 'Customer Name',
                'booking_date' => 'Booking Date',
                'status' => 'Status',
            ],
            'PdiRecord' => [
                'pdi_code' => 'PDI Code',
                'vehicle_id' => 'Vehicle',
                'status' => 'Status',
            ],
            'TowingRecord' => [
                'towing_code' => 'Towing Code',
                'vehicle_id' => 'Vehicle',
                'status' => 'Status',
            ],
        ];

        return $fields[$doctype] ?? [];
    }
}
