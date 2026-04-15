<?php

/**
 * Approximate media limits for composer-time checks (APIs change; tune as needed).
 * Keys are optional — only set constraints are enforced. Null/omitted = no check.
 *
 * aspect_ratio_min / aspect_ratio_max use width ÷ height (e.g. 0.8 ≈ 4:5 portrait, 1.91 ≈ 1.91:1 landscape).
 */
return [

    'twitter' => [
        'image' => [
            'max_width'        => 8192,
            'max_height'       => 8192,
            'max_long_edge'    => 8192,
            'max_file_mb'      => 5,
        ],
        'video' => [
            'max_width'         => 1920,
            'max_height'        => 1200,
            'max_long_edge'     => 1920,
            'max_duration_sec'  => 140,
            'max_file_mb'       => 512,
            'min_short_edge'    => 32,
        ],
    ],

    'facebook' => [
        'image' => [
            'max_width'     => 8192,
            'max_height'    => 8192,
            'max_long_edge' => 8192,
            'max_file_mb'   => 10,
        ],
        'video' => [
            'max_width'        => 1920,
            'max_height'       => 1080,
            'max_long_edge'    => 1920,
            'max_duration_sec' => 14400,
            'max_file_mb'      => 4096,
        ],
    ],

    'instagram' => [
        'image' => [
            'min_width'          => 320,
            'max_width'          => 1440,
            'max_height'         => 1800,
            'max_long_edge'      => 1920,
            'aspect_ratio_min'   => 0.8,
            'aspect_ratio_max'   => 1.91,
            'max_file_mb'        => 8,
        ],
        'video' => [
            'min_width'          => 320,
            'max_long_edge'      => 1920,
            'max_duration_sec'   => 900,
            'min_duration_sec'   => 3,
            'max_file_mb'        => 300,
        ],
    ],

    'linkedin' => [
        'image' => [
            'max_long_edge' => 5528,
            'max_file_mb'   => 100,
        ],
        'video' => [
            'max_long_edge'    => 4096,
            'max_duration_sec' => 600,
            'max_file_mb'      => 200,
        ],
    ],

    'tiktok' => [
        'image' => [
            'max_width'     => 1080,
            'max_height'    => 1920,
            'max_long_edge' => 1920,
            'max_file_mb'   => 20,
        ],
        'video' => [
            'min_width'          => 720,
            'min_height'         => 720,
            'min_short_edge'     => 720,
            'max_long_edge'      => 1920,
            'max_duration_sec'   => 600,
            'min_duration_sec'   => 1,
            'max_file_mb'        => 4096,
        ],
    ],

    'youtube' => [
        'image' => [
            'max_long_edge' => 7680,
            'max_file_mb'   => 20,
        ],
        'video' => [
            'max_long_edge'    => 7680,
            'max_duration_sec' => 43200,
            'max_file_mb'      => 262144,
        ],
    ],

    'telegram' => [
        'image' => [
            'max_width'     => 10000,
            'max_height'    => 10000,
            'max_long_edge' => 10000,
            'max_file_mb'   => 10,
        ],
        'video' => [
            'max_long_edge'    => 4096,
            'max_duration_sec' => 3600,
            'max_file_mb'      => 2000,
        ],
    ],

    'pinterest' => [
        'image' => [
            'min_width'     => 100,
            'min_height'    => 100,
            'max_long_edge' => 10000,
            'max_file_mb'   => 32,
        ],
    ],

    'threads' => [
        'image' => [
            'max_long_edge' => 8192,
            'max_file_mb'   => 8,
        ],
        'video' => [
            'max_long_edge'    => 1920,
            'max_duration_sec' => 300,
            'max_file_mb'      => 1000,
        ],
    ],

    'reddit' => [
        'image' => [
            'max_long_edge' => 20000,
            'max_file_mb'   => 20,
        ],
    ],

    'wordpress' => [
        'image' => [
            'max_long_edge' => 10000,
            'max_file_mb'   => 64,
        ],
    ],

    'google_business' => [
        'image' => [
            'min_width'     => 250,
            'min_height'    => 250,
            'max_long_edge' => 5000,
            'max_file_mb'   => 20,
        ],
        'video' => [
            'max_long_edge'    => 3840,
            'max_duration_sec' => 3600,
            'max_file_mb'      => 75000,
        ],
    ],

    'discord' => [
        'image' => [
            'max_long_edge' => 8192,
            'max_file_mb'   => 8,
        ],
    ],

    'bluesky' => [
        'image' => [
            'max_long_edge' => 2000,
            'max_file_mb'   => 1,
        ],
    ],

    'devto' => [
        'image' => [
            'max_long_edge' => 5000,
            'max_file_mb'   => 10,
        ],
    ],

    'whatsapp_channels' => [
        'image' => [
            'max_long_edge' => 5000,
            'max_file_mb'   => 5,
        ],
        'video' => [
            'max_long_edge'    => 1920,
            'max_duration_sec' => 600,
            'max_file_mb'      => 16,
        ],
    ],
];
