<?php

declare(strict_types=1);

namespace App\Services\Installer;

use Illuminate\Support\Facades\URL;

/**
 * Derives install and public base paths from the current request so the standalone
 * installer works in a subdirectory (e.g. http://localhost/wagwizi/install/...) and at domain root.
 */
final class InstallerRequestContext
{
    public static function applyUrlGenerator(): void
    {
        URL::forceRootUrl(rtrim(self::publicRootUrl(), '/'));
    }

    /**
     * URL prefix for install steps, e.g. /wagwizi/install or /install.
     */
    public static function installPathPrefix(): string
    {
        $script = self::normalizedScriptName();
        if ($script === '') {
            return '/install';
        }

        $dir = dirname($script);
        $dir = str_replace('\\', '/', $dir);
        $prefix = rtrim($dir, '/');

        return $prefix !== '' ? $prefix : '/install';
    }

    /**
     * Path segment for the app web root (parent of /install), e.g. /wagwizi or '' for site root.
     */
    public static function publicBasePath(): string
    {
        $installPrefix = self::installPathPrefix();
        $base = dirname($installPrefix);
        $base = str_replace('\\', '/', (string) $base);

        if ($base === '/' || $base === '.' || $base === '') {
            return '';
        }

        return rtrim($base, '/');
    }

    public static function publicRootUrl(): string
    {
        return self::requestScheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . self::publicBasePath();
    }

    public static function installUrl(string $step): string
    {
        $step = trim($step, '/');
        $prefix = self::installPathPrefix();

        return $step === '' ? $prefix . '/' : $prefix . '/' . $step;
    }

    private static function normalizedScriptName(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

        return str_replace('\\', '/', $script);
    }

    private static function requestScheme(): string
    {
        if (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }

        $xf = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (is_string($xf) && strtolower($xf) === 'https') {
            return 'https';
        }

        return 'http';
    }
}
