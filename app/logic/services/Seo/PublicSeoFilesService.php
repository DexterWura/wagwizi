<?php

namespace App\Services\Seo;

use App\Models\Faq;
use App\Models\Plan;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PublicSeoFilesService
{
    public function writeSitemap(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $lastmod = now();

        $entries = [
            ['loc' => $base . '/', 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => $base . '/terms', 'changefreq' => 'yearly', 'priority' => '0.2'],
            ['loc' => $base . '/privacy', 'changefreq' => 'yearly', 'priority' => '0.2'],
        ];

        if (Schema::hasTable('site_settings')) {
            $updatedAt = SiteSetting::query()->max('updated_at');
            if ($updatedAt !== null) {
                $candidate = \Illuminate\Support\Carbon::parse((string) $updatedAt);
                if ($candidate->greaterThan($lastmod)) {
                    $lastmod = $candidate;
                }
            }
        }
        if (Schema::hasTable('plans')) {
            $updatedAt = Plan::query()->max('updated_at');
            if ($updatedAt !== null) {
                $candidate = \Illuminate\Support\Carbon::parse((string) $updatedAt);
                if ($candidate->greaterThan($lastmod)) {
                    $lastmod = $candidate;
                }
            }
        }
        if (Schema::hasTable('faqs')) {
            $updatedAt = Faq::query()->max('updated_at');
            if ($updatedAt !== null) {
                $candidate = \Illuminate\Support\Carbon::parse((string) $updatedAt);
                if ($candidate->greaterThan($lastmod)) {
                    $lastmod = $candidate;
                }
            }
        }
        if (Schema::hasTable('testimonials')) {
            $updatedAt = Testimonial::query()->max('updated_at');
            if ($updatedAt !== null) {
                $candidate = \Illuminate\Support\Carbon::parse((string) $updatedAt);
                if ($candidate->greaterThan($lastmod)) {
                    $lastmod = $candidate;
                }
            }
        }

        $lastmodAtom = $lastmod->toAtomString();

        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $e) {
            $loc = htmlspecialchars($e['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . $loc . '</loc>';
            $lines[] = '    <lastmod>' . $lastmodAtom . '</lastmod>';
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
