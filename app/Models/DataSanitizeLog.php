<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataSanitizeLog extends Model
{
    protected $fillable = [
        'type',
        'records_affected',
        'details',
        'run_by',
    ];

    protected $casts = [
        'details' => 'array',
    ];
}
