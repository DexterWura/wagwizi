<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'platform_user_id',
        'username',
        'display_name',
        'avatar_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'metadata',
        'status',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'scopes'           => 'array',
            'metadata'         => 'array',
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postPlatforms(): HasMany
    {
        return $this->hasMany(PostPlatform::class);
    }

    public function isTokenExpired(): bool
    {
        if ($this->token_expires_at === null) {
            return false;
        }
        return $this->token_expires_at->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Remote profile image URL from the platform (OAuth / API), for composer previews only.
     */
    public function composerPreviewAvatarUrl(): ?string
    {
        $url = trim((string) ($this->avatar_url ?? ''));
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return $url;
    }
}
