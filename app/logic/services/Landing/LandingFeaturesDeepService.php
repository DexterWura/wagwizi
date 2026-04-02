<?php

namespace App\Services\Landing;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\URL;

class LandingFeaturesDeepService
{
    public const VISUAL_GLASS_CARD = 'glass_card';

    public const VISUAL_GLASS_MONO = 'glass_mono';

    public const VISUAL_ICONS = 'icons';

    public const VISUAL_IMAGE = 'image';

    public const VISUAL_GRID = 'grid';

    /** @return list<string> */
    public static function visualOptions(): array
    {
        return [
            self::VISUAL_GLASS_CARD,
            self::VISUAL_GLASS_MONO,
            self::VISUAL_ICONS,
            self::VISUAL_IMAGE,
            self::VISUAL_GRID,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function defaultBlocks(): array
    {
        return [
            [
                'reverse'       => false,
                'title'         => 'Everything for growth in one place',
                'body'          => 'Dashboard, composer, calendar, and accounts — same shell, dark or light, with a theme toggle that persists.',
                'cta_label'     => 'Explore dashboard',
                'cta_href'      => '',
                'visual'        => self::VISUAL_GLASS_CARD,
                'glass_eyebrow' => 'Omnichannel',
                'glass_body'    => 'Reach, engagement, and queue — without leaving the page.',
                'glass_mono'    => '',
                'icon_classes'  => '',
                'image'         => '',
            ],
            [
                'reverse'       => true,
                'title'         => 'AI content assistant',
                'body'          => 'Cursor-style chat beside your draft; suggested edits appear inline in the feed preview.',
                'cta_label'     => 'Try composer',
                'cta_href'      => '',
                'visual'        => self::VISUAL_GLASS_MONO,
                'glass_eyebrow' => '',
                'glass_body'    => '',
                'glass_mono'    => '+ One workspace. Every channel.',
                'icon_classes'  => '',
                'image'         => '',
            ],
            [
                'reverse'       => false,
                'title'         => 'Post preview across networks',
                'body'          => 'Stacked previews for X, LinkedIn, YouTube, and more — edit once or per tab.',
                'cta_label'     => '',
                'cta_href'      => '',
                'visual'        => self::VISUAL_ICONS,
                'glass_eyebrow' => '',
                'glass_body'    => '',
                'glass_mono'    => '',
                'icon_classes'  => 'fa-brands fa-x-twitter fa-2x,fa-brands fa-linkedin fa-2x,fa-brands fa-instagram fa-2x',
                'image'         => '',
            ],
            [
                'reverse'       => true,
                'title'         => 'Drag-and-drop scheduling',
                'body'          => 'Move posts between days or back to the unscheduled queue — persistence is yours to wire.',
                'cta_label'     => 'Open calendar',
                'cta_href'      => '',
                'visual'        => self::VISUAL_GRID,
                'glass_eyebrow' => '',
                'glass_body'    => '',
                'glass_mono'    => '',
                'icon_classes'  => '',
                'image'         => '',
            ],
        ];
    }

    /**
     * Merged blocks for admin form and landing (always 4 rows).
     *
     * @return list<array<string, mixed>>
     */
    public function resolvedBlocks(): array
    {
        $stored   = SiteSetting::getJson('landing_features_deep', []);
        $defaults = $this->defaultBlocks();
        $out      = [];
        for ($i = 0; $i < 4; $i++) {
            $out[] = $this->mergeBlock($defaults[$i], is_array($stored[$i] ?? null) ? $stored[$i] : []);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    public function mergeBlock(array $def, array $over): array
    {
        $keys = array_keys($def);
        $b    = $def;
        foreach ($keys as $k) {
            if (array_key_exists($k, $over)) {
                $b[$k] = $over[$k];
            }
        }
        $b['reverse'] = filter_var($b['reverse'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $b['visual']  = in_array($b['visual'] ?? '', self::visualOptions(), true)
            ? $b['visual']
            : $def['visual'];

        return $b;
    }

    public function resolveCtaHref(string $href, string $fallbackRoute = 'signup'): string
    {
        $h = trim($href);
        if ($h === '') {
            return route($fallbackRoute);
        }
        if (str_starts_with($h, 'http://') || str_starts_with($h, 'https://')) {
            return $h;
        }
        if (str_starts_with($h, '/')) {
            return URL::to($h);
        }

        return route($fallbackRoute);
    }

    /**
     * @return list<string>
     */
    public function parseIconClasses(string $raw): array
    {
        $parts = array_map('trim', explode(',', $raw));

        return array_values(array_filter($parts, fn (string $s) => $s !== ''));
    }
}
