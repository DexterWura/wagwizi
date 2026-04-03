<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MinifyAssets extends Command
{
    protected $signature = 'assets:minify';

    protected $description = 'Minify CSS and JS assets for production';

    public function handle(): int
    {
        $publicPath = $this->laravel->publicPath();

        $assets = [
            ['src' => $publicPath . '/assets/css/style.css', 'dest' => $publicPath . '/assets/css/style.min.css', 'type' => 'css'],
            ['src' => $publicPath . '/assets/js/app.js', 'dest' => $publicPath . '/assets/js/app.min.js', 'type' => 'js'],
        ];

        foreach ($assets as $asset) {
            if (! is_file($asset['src'])) {
                $this->warn("Skipped: {$asset['src']} not found.");
                continue;
            }

            $content = file_get_contents($asset['src']);
            $original = strlen($content);

            $minified = $asset['type'] === 'css'
                ? $this->minifyCss($content)
                : $this->minifyJs($content);

            file_put_contents($asset['dest'], $minified);
            $final = strlen($minified);
            $pct = round(100 - ($final / max($original, 1) * 100), 1);

            $this->info(basename($asset['dest']) . ": {$original} → {$final} bytes ({$pct}% smaller)");
        }

        $this->newLine();
        $this->info('Done. Update your layout to reference .min.css / .min.js in production.');

        return self::SUCCESS;
    }

    private function minifyCss(string $css): string
    {
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);
        $css = preg_replace('/;}/', '}', $css);
        $css = preg_replace('/\s+/', ' ', $css);

        return trim($css);
    }

    private function minifyJs(string $js): string
    {
        $js = preg_replace('#/\*.*?\*/#s', '', $js);
        $js = preg_replace('#(?<!:)//(?![\'"]).+$#m', '', $js);
        $js = preg_replace('/\n\s*\n/', "\n", $js);
        $js = preg_replace('/^\s+/m', '', $js);
        $js = preg_replace('/\s+$/', '', $js);

        return trim($js);
    }
}
