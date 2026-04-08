<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Global helper loader placeholder
|--------------------------------------------------------------------------
|
| Composer autoload expects this file (see vendor/composer/autoload_files.php).
| Keep it present in deployments even if no custom global helper functions
| are currently required.
|
*/

/**
 * Public path to the main app stylesheet (minified in production when the file exists).
 */
function app_bundle_css_path(): string
{
    if (config('app.debug')) {
        return 'assets/css/style.css';
    }

    $min = 'assets/css/style.min.css';

    return is_file(public_path($min)) ? $min : 'assets/css/style.css';
}

/**
 * Public path to the main app script bundle (minified in production when the file exists).
 */
function app_bundle_js_path(): string
{
    if (config('app.debug')) {
        return 'assets/js/app.js';
    }

    $min = 'assets/js/app.min.js';

    return is_file(public_path($min)) ? $min : 'assets/js/app.js';
}

/**
 * Cache-busting query value for a path under the project public root.
 */
function app_bundle_asset_version(string $relativePath): int
{
    $full = public_path($relativePath);
    if (! is_file($full)) {
        return time();
    }
    $m = filemtime($full);

    return $m !== false ? (int) $m : time();
}

