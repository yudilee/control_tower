<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use Notifiable;
    
    protected $guard = 'customer';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'password',
        'account_no',
        'verified',
        'verification_token',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_token',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    /**
     * Vehicles owned by this customer
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'customer_vehicles')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get jobs for all customer's vehicles
     */
    public function jobs()
    {
        $vehicleIds = $this->vehicles()->pluck('vehicles.id');
        return Job::whereIn('vehicle_id', $vehicleIds);
    }

    /**
     * Check if customer can view a specific job
     */
    public function canViewJob(Job $job): bool
    {
        if (!$job->vehicle_id) {
            // Match by plate number
            return $this->vehicles()
                ->where('plate_number', $job->plate_number)
                ->exists();
        }
        
        return $this->vehicles()
            ->where('vehicles.id', $job->vehicle_id)
            ->exists();
    }

    /**
     * Get jobs grouped by status
     */
    public function getJobsByStatus()
    {
        return $this->jobs()
            ->orderBy('job_date', 'desc')
            ->get()
            ->groupBy('status');
    }

    /**
     * Generate verification token
     */
    public function generateVerificationToken(): string
    {
        $token = \Illuminate\Support\Str::random(64);
        $this->update(['verification_token' => $token]);
        return $token;
    }
}
