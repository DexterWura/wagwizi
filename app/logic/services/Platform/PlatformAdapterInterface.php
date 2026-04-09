<?php

namespace App\Services\Platform;

use App\Models\SocialAccount;

interface PlatformAdapterInterface
{
    public function platform(): Platform;

    /**
     * Publish content to the platform.
     *
     * @param SocialAccount $account         The connected account with valid tokens
     * @param string        $content         Master post content
     * @param string[]      $mediaUrls       Absolute URLs to media files
     * @param string|null   $platformContent Per-platform content override (if user customized)
     * @param string|null   $audience        Audience selection from composer, if supported by platform
     */
    public function publish(
        SocialAccount $account,
        string        $content,
        array         $mediaUrls = [],
        ?string       $platformContent = null,
        ?string       $audience = null,
    ): PublishResult;

    /**
     * Delete a previously published post from the platform.
     */
    public function deletePost(SocialAccount $account, string $platformPostId): bool;

    /**
     * Refresh an expired OAuth token. Returns new tokens without touching the DB.
     */
    public function refreshToken(string $refreshToken): TokenResult;

    /**
     * Optionally publish a follow-up comment on a platform post.
     * Return true when comment was published, false when unsupported/failed.
     */
    public function publishComment(SocialAccount $account, string $platformPostId, string $comment): bool;
}
