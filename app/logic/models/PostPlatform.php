<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostPlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'social_account_id',
        'platform',
        'platform_content',
        'audience',
        'first_comment',
        'comment_delay_minutes',
        'comment_status',
        'comment_error_message',
        'comment_published_at',
        'platform_post_id',
        'status',
        'error_message',
        'published_at',
        'likes_count',
        'reposts_count',
        'comments_count',
        'impressions_count',
        'metrics_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at'     => 'datetime',
            'metrics_synced_at' => 'datetime',
            'comment_published_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
}
