<?php

namespace App\Services\Landing;

use App\Models\SiteSetting;

class LandingHowItWorksService
{
    public const STORAGE_KEY = 'landing_how_it_works';

    /**
     * @return list<array{title:string,body:string,icon_classes:string}>
     */
    public function defaultSteps(): array
    {
        return [
            [
                'title' => 'Connect & Configure',
                'body'  => 'Link your LinkedIn account and tell us about your industry, tone, and goals. Takes 3 minutes.',
                'icon_classes' => 'fa-solid fa-paper-plane',
            ],
            [
                'title' => 'Get Ideas & Write',
                'body'  => 'Choose from trending topics or generate custom ideas. Our AI will write your content, and you can post it directly.',
                'icon_classes' => 'fa-solid fa-wand-magic-sparkles',
            ],
            [
                'title' => 'Schedule & Dominate',
                'body'  => 'Make quick tweaks, copy your content, and post it directly to LinkedIn.',
                'icon_classes' => 'fa-solid fa-calendar-check',
            ],
        ];
    }

    /**
     * @return list<array{title:string,body:string,icon_classes:string}>
     */
    public function resolvedSteps(): array
    {
        $stored = SiteSetting::getJson(self::STORAGE_KEY, []);
        $defs   = $this->defaultSteps();

        $out = [];
        for ($i = 0; $i < 3; $i++) {
            $over = is_array($stored[$i] ?? null) ? $stored[$i] : [];
            $out[] = $this->mergeStep($defs[$i], $over);
        }

        return $out;
    }

    /**
     * @param  array{title:string,body:string,icon_classes:string}  $def
     * @param  array<string, mixed>  $over
     * @return array{title:string,body:string,icon_classes:string}
     */
    public function mergeStep(array $def, array $over): array
    {
        $out = $def;

        foreach (['title', 'body', 'icon_classes'] as $k) {
            if (array_key_exists($k, $over)) {
                $out[$k] = $over[$k];
            }
        }

        $out['title'] = trim((string) ($out['title'] ?? ''));
        $out['body'] = trim((string) ($out['body'] ?? ''));
        $parsedIcon = $this->parseIconClasses((string) ($out['icon_classes'] ?? ''));
        $out['icon_classes'] = $parsedIcon !== '' ? $parsedIcon : (string) ($def['icon_classes'] ?? '');

        return $out;
    }

    public function parseIconClasses(string $raw): string
    {
        // Keep this strict to avoid injecting arbitrary classes/markup.
        $s = trim(strip_tags($raw));
        if ($s === '') return '';

        $parts = preg_split('/\s+/', $s) ?: [];
        $safe  = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;

            // Font Awesome classes typically start with "fa-".
            if (preg_match('/^fa-[a-z0-9-]+$/i', $p) === 1) {
                $safe[] = strtolower($p);
            }
        }

        // If the admin entered something non-standard, fall back to the default.
        if (count($safe) === 0) return '';

        return implode(' ', array_slice($safe, 0, 8));
    }
}

