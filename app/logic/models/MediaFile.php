<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'file_name',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'type',
        'is_premium',
        'price_cents',
        'alt_text',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'is_premium' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_media')
                    ->withPivot('sort_order', 'created_at');
    }

    public function scopeImages($query)
    {
        return $query->where('type', 'image');
    }

    public function scopeVideos($query)
    {
        return $query->where('type', 'video');
    }

    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->path === null || trim($this->path) === '') {
            return null;
        }

        $base = rtrim((string) config('app.url'), '/');
        $path = ltrim($this->path, '/');

        return $base . '/' . $path;
    }

    public function getPriceDollars(): ?float
    {
        return $this->price_cents !== null
            ? $this->price_cents / 100
            : null;
    }
}
