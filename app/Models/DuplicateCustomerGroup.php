<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicateCustomerGroup extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_MERGED = 'merged';
    const STATUS_DISMISSED = 'dismissed';

    const CLASS_DMS_ISSUE = 'DMS_ISSUE';
    const CLASS_USER_MISTAKE = 'USER_MISTAKE';

    protected $fillable = [
        'group_hash',
        'names',
        'entries',
        'classification',
        'dms_count',
        'user_count',
        'status',
    ];

    protected $casts = [
        'names' => 'array',
        'entries' => 'array',
    ];

    /**
     * Scope for pending groups
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Generate hash from names for uniqueness
     */
    public static function generateHash(array $names): string
    {
        $sorted = array_map('strtolower', array_map('trim', $names));
        sort($sorted);
        return hash('sha256', implode('|', $sorted));
    }

    /**
     * Check if a group is dismissed
     */
    public static function isGroupDismissed(array $names): bool
    {
        $hash = self::generateHash($names);
        return self::where('group_hash', $hash)
            ->where('status', self::STATUS_DISMISSED)
            ->exists();
    }
}
