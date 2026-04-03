<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\File;

class PublicSeoFilesService
{
    /**
     * Build and write sitemap.xml next to the front controller (project root; see bootstrap usePublicPath).
     *
     * @return string Absolute path written
     */
    public function writeSitemap(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $lastmod = now()->toAtomString();

        $entries = [
            ['loc' => $base . '/', 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => $base . '/signup', 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => $base . '/login', 'changefreq' => 'monthly', 'priority' => '0.4'],
        ];

        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $e) {
            $loc = htmlspecialchars($e['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . $loc . '</loc>';
            $lines[] = '    <lastmod>' . $lastmod . '</lastmod>';
            $lines[] = '    <changefreq>' . $e['changefreq'] . '</changefreq>';
            $lines[] = '    <priority>' . $e['priority'] . '</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';
        $xml = implode("\n", $lines) . "\n";

        $path = public_path('sitemap.xml');
        File::put($path, $xml);

        return $path;
    }

    /**
     * Write robots.txt with common Disallow rules for the app and a Sitemap line.
     *
     * @return string Absolute path written
     */
    public function writeRobotsTxt(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $sitemapUrl = $base . '/sitemap.xml';

        $disallowPrefixes = [
            '/admin',
            '/api',
            '/install',
            '/dashboard',
            '/composer',
            '/calendar',
            '/media-library',
            '/accounts',
            '/insights',
            '/plans',
            '/plan-history',
            '/profile',
            '/settings',
            '/support-tickets',
            '/notifications',
            '/media',
        ];

        $lines = ['User-agent: *', 'Allow: /', ''];

        foreach ($disallowPrefixes as $prefix) {
            $lines[] = 'Disallow: ' . $prefix;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . $sitemapUrl;
        $lines[] = '';

        $path = public_path('robots.txt');
        File::put($path, implode("\n", $lines));

        return $path;
    }
}
