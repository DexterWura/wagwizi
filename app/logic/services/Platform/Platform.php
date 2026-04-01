<?php

namespace App\Services\Platform;

enum Platform: string
{
    case Twitter   = 'twitter';
    case Facebook  = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn  = 'linkedin';
    case TikTok    = 'tiktok';
    case YouTube   = 'youtube';
    case Telegram  = 'telegram';
    case Pinterest = 'pinterest';
    case Threads   = 'threads';
    case Reddit    = 'reddit';
    case WordPress      = 'wordpress';
    case GoogleBusiness = 'google_business';
    case Discord        = 'discord';

    public function label(): string
    {
        return match ($this) {
            self::Twitter        => 'X (Twitter)',
            self::Facebook       => 'Facebook',
            self::Instagram      => 'Instagram',
            self::LinkedIn       => 'LinkedIn',
            self::TikTok         => 'TikTok',
            self::YouTube        => 'YouTube',
            self::Telegram       => 'Telegram',
            self::Pinterest      => 'Pinterest',
            self::Threads        => 'Threads',
            self::Reddit         => 'Reddit',
            self::WordPress      => 'WordPress',
            self::GoogleBusiness => 'Google Business',
            self::Discord        => 'Discord',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Twitter        => 'fa-brands fa-x-twitter',
            self::Facebook       => 'fa-brands fa-facebook',
            self::Instagram      => 'fa-brands fa-instagram',
            self::LinkedIn       => 'fa-brands fa-linkedin',
            self::TikTok         => 'fa-brands fa-tiktok',
            self::YouTube        => 'fa-brands fa-youtube',
            self::Telegram       => 'fa-brands fa-telegram',
            self::Pinterest      => 'fa-brands fa-pinterest',
            self::Threads        => 'fa-brands fa-threads',
            self::Reddit         => 'fa-brands fa-reddit',
            self::WordPress      => 'fa-brands fa-wordpress',
            self::GoogleBusiness => 'fa-brands fa-google',
            self::Discord        => 'fa-brands fa-discord',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Twitter        => 'Post text, images, and threads to your profile.',
            self::Facebook       => 'Publish to Facebook Pages you administer.',
            self::Instagram      => 'Requires a Business or Creator account linked through Meta.',
            self::LinkedIn       => 'Publish to company pages and personal profiles you manage.',
            self::TikTok         => 'Share where the TikTok API allows for your account type.',
            self::YouTube        => 'Community tab posts and video metadata updates.',
            self::Telegram       => 'Use a bot token to post to channels you control.',
            self::Pinterest      => 'Pin images and links to your boards.',
            self::Threads        => 'Connect through Meta when Threads API access is enabled for your account.',
            self::Reddit         => 'Post to subreddits and communities you moderate.',
            self::WordPress      => 'Publish blog posts via the REST API using an Application Password.',
            self::GoogleBusiness => 'Post updates to your Google Business Profile listing.',
            self::Discord        => 'Send messages to a channel via a webhook URL.',
        };
    }

    public function usesOAuth(): bool
    {
        return !in_array($this, [self::Telegram, self::WordPress, self::Discord]);
    }
}
