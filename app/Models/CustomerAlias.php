<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'alias_name',
        'created_by',
    ];

    /**
     * The customer this alias belongs to
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The user who created this alias
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Find customer by name or alias
     */
    public static function findCustomerByName(string $name): ?Customer
    {
        $normalizedName = strtoupper(trim($name));
        
        // First try exact match on customers table
        $customer = Customer::whereRaw('UPPER(name) = ?', [$normalizedName])->first();
        if ($customer) {
            return $customer;
        }
        
        // Then try alias table
        $alias = self::whereRaw('UPPER(alias_name) = ?', [$normalizedName])->first();
        if ($alias) {
            return $alias->customer;
        }
        
        return null;
    }

    /**
     * Get unmatched customer names from jobs
     */
    public static function getUnmatchedNames(): \Illuminate\Support\Collection
    {
        return \DB::table('jobs')
            ->whereNull('customer_id')
            ->whereNotNull('customer_name')
            ->select('customer_name')
            ->distinct()
            ->orderBy('customer_name')
            ->pluck('customer_name');
    }
}
