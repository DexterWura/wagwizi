<?php

return [

    'twitter' => [
        'enabled'       => (bool) env('TWITTER_CLIENT_ID'),
        'client_id'     => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect_uri'  => env('TWITTER_REDIRECT_URI'),
        'scopes'        => ['tweet.read', 'tweet.write', 'users.read', 'offline.access', 'media.write'],
        'min_publish_interval_seconds' => (int) env('TWITTER_MIN_PUBLISH_INTERVAL_SECONDS', 90),
        'disable_first_comment_replies' => filter_var(env('TWITTER_DISABLE_FIRST_COMMENT_REPLIES', true), FILTER_VALIDATE_BOOL),
        'max_content_length' => 280,
        'supports_images'    => true,
        'supports_video'     => true,
        'supports_carousel'  => false,
        'rate_limit'         => 200,
    ],

    'facebook' => [
        'enabled'       => (bool) env('FACEBOOK_CLIENT_ID'),
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect_uri'  => env('FACEBOOK_REDIRECT_URI'),
        'scopes'        => ['public_profile'],
        'max_content_length' => 63206,
        'supports_images'    => true,
        'supports_video'     => true,
        'supports_carousel'  => false,
        'rate_limit'         => 60,
    ],

    'facebook_pages' => [
        'enabled'       => (bool) env('FACEBOOK_PAGES_CLIENT_ID', env('FACEBOOK_CLIENT_ID')),
        'client_id'     => env('FACEBOOK_PAGES_CLIENT_ID', env('FACEBOOK_CLIENT_ID')),
        'client_secret' => env('FACEBOOK_PAGES_CLIENT_SECRET', env('FACEBOOK_CLIENT_SECRET')),
        'redirect_uri'  => env('FACEBOOK_PAGES_REDIRECT_URI', env('FACEBOOK_REDIRECT_URI')),
        'scopes'        => ['pages_manage_posts', 'pages_read_engagement', 'pages_show_list'],
        'max_content_length' => 63206,
        'supports_images'    => true,
        'supports_video'     => true,
        'supports_carousel'  => false,
        'rate_limit'         => 60,
    ],

    'instagram' => [
        'enabled'       => (bool) env('INSTAGRAM_CLIENT_ID'),
        'client_id'     => env('INSTAGRAM_CLIENT_ID'),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET'),
        'redirect_uri'  => env('INSTAGRAM_REDIRECT_URI'),
        'scopes'        => ['instagram_basic', 'instagram_content_publish', 'pages_show_list'],
        'max_content_length' => 2200,
        'supports_images'    => true,
        'supports_video'     => true,
        'supports_carousel'  => true,
        'rate_limit'         => 25,
    ],

    'linkedin' => [
        'enabled'       => (bool) env('LINKEDIN_CLIENT_ID'),
        'client_id'     => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect_uri'  => env('LINKEDIN_REDIRECT_URI'),
        'api_version'   => env('LINKEDIN_API_VERSION', '202504'),
        'scopes'        => array_values(array_filter(array_merge(
            ['openid', 'profile', 'w_member_social'],
            filter_var(env('LINKEDIN_ENABLE_ORG_SCOPES', false), FILTER_VALIDATE_BOOL)
                ? ['w_organization_social', 'r_organization_social', 'rw_organization_admin']
                : []
        ))),
        'max_content_length' => 3000,
        'supports_images'    => true,
        'supports_video'     => true,
        'supports_carousel'  => false,
        'rate_limit'         => 100,
    ],

    'linkedin_pages' => [
        'enabled'       => (bool) env('LINKEDIN_PAGES_CLIENT_ID', env('LINKEDIN_CLIENT_ID')),
        'client_id'     => env('LINKEDIN_PAGES_CLIENT_ID', env('LINKEDIN_CLIENT_ID')),
        'client_secret' => env('LINKEDIN_PAGES_CLIENT_SECRET', env('LINKEDIN_CLIENT_SECRET')),
        'redirect_uri'  => env('LINKEDIN_PAGES_REDIRECT_URI', env('LINKEDIN_REDIRECT_URI')),
        'api_version'   => env('LINKEDIN_PAGES_API_VERSION', env('LINKEDIN_API_VERSION', '202504')),
        'scopes'        => [
            'openid',
            'profile',
            'w_member_social',
            'w_organization_social',
            'r_organization_social',
            'rw_organization_admin',
        ],
        'max_content_length' => 3000,
        'supports_images'    => true,
        'supports_video'     => true,
        'supports_carousel'  => false,
        'rate_limit'         => 100,
    ],

    'tiktok' => [
        // TikTok names this value "client_key". Accept legacy TIKTOK_CLIENT_ID too.
        'enabled'       => (bool) env('TIKTOK_CLIENT_KEY', env('TIKTOK_CLIENT_ID')),
        'client_id'     => env('TIKTOK_CLIENT_KEY', env('TIKTOK_CLIENT_ID')),
        'client_key'    => env('TIKTOK_CLIENT_KEY', env('TIKTOK_CLIENT_ID')),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect_uri'  => env('TIKTOK_REDIRECT_URI'),
        'scopes'        => ['user.info.basic', 'video.publish', 'video.upload'],
        'max_content_length' => 2200,
        'supports_images'    => true,
        'supports_video'     => true,
        'supports_carousel'  => false,
        'rate_limit'         => 6,
    ],

    'youtube' => [
        'enabled'       => (bool) env('YOUTUBE_CLIENT_ID'),
        'client_id'     => env('YOUTUBE_CLIENT_ID'),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
        'redirect_uri'  => env('YOUTUBE_REDIRECT_URI'),
        'scopes'        => ['https://www.googleapis.com/auth/youtube', 'https://www.googleapis.com/auth/youtube.upload'],
        'max_content_length' => 5000,
        'supports_images'    => false,
        'supports_video'     => true,
        'supports_carousel'  => false,
        'rate_limit'         => 60,
    ],

    'telegram' => [
        'enabled'            => true,
        'max_content_length' => 4096,
        'supports_images'    => true,
        'supports_video'     => true,
        'supports_carousel'  => false,
        'rate_limit'         => 30,
    ],

    'pinterest' => [
        'enabled'       => (bool) env('PINTEREST_CLIENT_ID'),
        'client_id'     => env('PINTEREST_CLIENT_ID'),
        'client_secret' => env('PINTEREST_CLIENT_SECRET'),
        'redirect_uri'  => env('PINTEREST_REDIRECT_URI'),
        'scopes'        => ['boards:read', 'pins:read', 'pins:write'],
        'max_content_length' => 500,
        'supports_images'    => true,
        'supports_video'     => false,
        'supports_carousel'  => false,
        'rate_limit'         => 50,
    ],

    'threads' => [
        'enabled'       => (bool) env('THREADS_CLIENT_ID'),
        'client_id'     => env('THREADS_CLIENT_ID'),
        'client_secret' => env('THREADS_CLIENT_SECRET'),
        'redirect_uri'  => env('THREADS_REDIRECT_URI'),
        'scopes'        => ['threads_basic', 'threads_content_publish', 'threads_manage_replies'],
        'max_content_length' => 500,
        'supports_images'    => true,
        'supports_video'     => true,
        'supports_carousel'  => true,
        'rate_limit'         => 25,
    ],

    'reddit' => [
        'enabled'       => (bool) env('REDDIT_CLIENT_ID'),
        'client_id'     => env('REDDIT_CLIENT_ID'),
        'client_secret' => env('REDDIT_CLIENT_SECRET'),
        'redirect_uri'  => env('REDDIT_REDIRECT_URI'),
        'scopes'        => ['identity', 'submit', 'read'],
        'max_content_length' => 40000,
        'supports_images'    => true,
        'supports_video'     => false,
        'supports_carousel'  => false,
        'rate_limit'         => 10,
    ],

    'wordpress' => [
        'enabled'            => true,
        'max_content_length' => 65535,
        'supports_images'    => true,
        'supports_video'     => false,
        'supports_carousel'  => false,
        'rate_limit'         => 60,
    ],

    'google_business' => [
        'enabled'       => (bool) env('GOOGLE_BUSINESS_CLIENT_ID'),
        'client_id'     => env('GOOGLE_BUSINESS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_BUSINESS_CLIENT_SECRET'),
        'redirect_uri'  => env('GOOGLE_BUSINESS_REDIRECT_URI'),
        'scopes'        => ['https://www.googleapis.com/auth/business.manage'],
        'max_content_length' => 1500,
        'supports_images'    => true,
        'supports_video'     => true,
        'supports_carousel'  => false,
        'rate_limit'         => 60,
    ],

    'discord' => [
        'enabled'            => true,
        'max_content_length' => 2000,
        'supports_images'    => true,
        'supports_video'     => false,
        'supports_carousel'  => false,
        'rate_limit'         => 30,
    ],

    'bluesky' => [
        'enabled'            => true,
        'service_host'       => env('BLUESKY_SERVICE_HOST', 'https://bsky.social'),
        // Current production mode uses app passwords via createSession/refreshSession.
        // OAuth fields are kept for staged migration and can be enabled in a future rollout.
        'auth_mode'          => env('BLUESKY_AUTH_MODE', 'app_password'),
        'oauth_enabled'      => filter_var(env('BLUESKY_OAUTH_ENABLED', false), FILTER_VALIDATE_BOOL),
        'oauth_client_id'    => env('BLUESKY_OAUTH_CLIENT_ID'),
        'oauth_client_secret'=> env('BLUESKY_OAUTH_CLIENT_SECRET'),
        'oauth_redirect_uri' => env('BLUESKY_OAUTH_REDIRECT_URI'),
        'max_content_length' => 300,
        'supports_images'    => true,
        'supports_video'     => false,
        'supports_carousel'  => true,
        'rate_limit'         => 50,
    ],

    'devto' => [
        'enabled'            => true,
        'max_content_length' => 65535,
        'supports_images'    => true,
        'supports_video'     => false,
        'supports_carousel'  => false,
        'rate_limit'         => 30,
    ],

    'whatsapp_channels' => [
        'enabled'              => true,
        'graph_api_version'    => env('WHATSAPP_GRAPH_API_VERSION', 'v21.0'),
        'embedded_signup_app_id' => env('WHATSAPP_EMBEDDED_SIGNUP_APP_ID'),
        'embedded_signup_app_secret' => env('WHATSAPP_EMBEDDED_SIGNUP_APP_SECRET'),
        'embedded_signup_config_id' => env('WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'),
        'max_content_length'   => 4096,
        'max_caption_length'   => 1024,
        'supports_images'      => true,
        'supports_video'       => true,
        'supports_carousel'    => false,
        'rate_limit'           => 80,
    ],

];
