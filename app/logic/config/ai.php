<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform (admin) AI — used when the user selects "{{ app name }} (platform)"
    | and has a paid active/trialing subscription. Keys stay on the server only.
    |--------------------------------------------------------------------------
    */
    'platform' => [
        'provider' => env('AI_PLATFORM_PROVIDER', 'openai'),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'openai_base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
        'anthropic_base_url' => rtrim((string) env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'), '/'),
        'anthropic_model' => env('ANTHROPIC_MODEL', 'claude-3-5-haiku-20241022'),
        'gemini_api_key' => env('GEMINI_API_KEY'),
        'gemini_base_url' => rtrim((string) env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        /** Upper bound subtracted from balance before each request (concurrency-safe with finalize). */
        'max_reserve_tokens_per_request' => max(1, (int) env('AI_PLATFORM_MAX_RESERVE_TOKENS', 16000)),
        /** Hard cap on completion length for OpenAI-compatible platform calls. */
        'max_completion_tokens' => max(256, (int) env('AI_PLATFORM_MAX_COMPLETION_TOKENS', 2048)),
        /** Anthropic platform max_tokens (input still billed separately). */
        'anthropic_max_tokens' => max(256, (int) env('AI_PLATFORM_ANTHROPIC_MAX_TOKENS', 2048)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults for BYOK (user's own key) when they do not pick a custom base URL.
    |--------------------------------------------------------------------------
    */
    'byok' => [
        'openai_base_url' => rtrim((string) env('AI_BYOK_OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'openai_model' => env('AI_BYOK_OPENAI_MODEL', 'gpt-4o-mini'),
        'anthropic_base_url' => rtrim((string) env('AI_BYOK_ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'), '/'),
        'anthropic_model' => env('AI_BYOK_ANTHROPIC_MODEL', 'claude-3-5-haiku-20241022'),
        'gemini_base_url' => rtrim((string) env('AI_BYOK_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        'gemini_model' => env('AI_BYOK_GEMINI_MODEL', 'gemini-2.0-flash'),
    ],
];
