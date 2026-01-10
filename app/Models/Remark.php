<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\Auditable;

class Remark extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'job_id',
        'parent_id',
        'user_id',
        'remark_text',
        'images',
        'mentions',
        'created_by',
    ];

    protected $casts = [
        'images' => 'array',
        'mentions' => 'array',
    ];

    /**
     * Get the parent comment (if this is a reply)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Remark::class, 'parent_id');
    }

    /**
     * Get replies to this comment
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Remark::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    /**
     * Check if this is a reply
     */
    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Check if this remark has replies
     */
    public function hasReplies(): bool
    {
        return $this->replies()->count() > 0;
    }

    /**
     * Check if this remark has images attached
     */
    public function hasImages(): bool
    {
        return !empty($this->images) && is_array($this->images);
    }

    /**
     * Get full URLs for all images
     */
    public function getImageUrlsAttribute(): array
    {
        if (!$this->hasImages()) {
            return [];
        }

        return array_map(fn($path) => asset("storage/{$path}"), $this->images);
    }

    /**
     * Get mentioned users from the mentions array
     */
    public function getMentionedUsersAttribute()
    {
        if (empty($this->mentions)) {
            return collect();
        }
        return User::whereIn('id', $this->mentions)->get();
    }

    /**
     * Parse @mentions from text and return user IDs
     * Supports: @username, @"Full Name", @'Full Name'
     */
    public static function parseMentions(string $text): array
    {
        $userIds = [];
        
        // Match @"Full Name" or @'Full Name'
        preg_match_all('/@["\']([^"\']+)["\']/', $text, $quotedMatches);
        foreach ($quotedMatches[1] as $name) {
            $user = User::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            if ($user) {
                $userIds[] = $user->id;
            }
        }
        
        // Match @username (single word, no spaces)
        preg_match_all('/@(\w+)/', $text, $simpleMatches);
        foreach ($simpleMatches[1] as $name) {
            $user = User::whereRaw('LOWER(name) LIKE ?', [strtolower($name) . '%'])->first();
            if ($user && !in_array($user->id, $userIds)) {
                $userIds[] = $user->id;
            }
        }
        
        return array_unique($userIds);
    }

    /**
     * Format text with highlighted mentions
     */
    public function getFormattedTextAttribute(): string
    {
        $text = e($this->remark_text);
        
        // Highlight @"Full Name" mentions
        $text = preg_replace('/@["\']([^"\']+)["\']/', '<span class="mention text-primary fw-semibold">@$1</span>', $text);
        
        // Highlight @username mentions  
        $text = preg_replace('/@(\w+)/', '<span class="mention text-primary fw-semibold">@$1</span>', $text);
        
        return $text;
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function containsOrderKeyword(): bool
    {
        return stripos($this->remark_text, 'ORDER') !== false;
    }

    /**
     * Get human-readable relative time (e.g., "2 hours ago")
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the display name for the commenter
     */
    public function getCommenterNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->name;
        }
        return $this->created_by ?? 'System';
    }

    /**
     * Get initials for avatar display
     */
    public function getCommenterInitialsAttribute(): string
    {
        $name = $this->commenter_name;
        $words = explode(' ', $name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }
}

