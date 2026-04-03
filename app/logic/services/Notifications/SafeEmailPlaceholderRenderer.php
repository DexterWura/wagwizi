<?php

namespace App\Services\Notifications;

use InvalidArgumentException;

/**
 * Renders email templates from the database without executing Blade/PHP.
 * Only {{ $varName }} placeholders are replaced; values are escaped except rawHtmlKeys.
 */
final class SafeEmailPlaceholderRenderer
{
    private const PLACEHOLDER = '/\{\{\s*\$(\w+)\s*\}\}/';

    /**
     * @param  array<string, mixed>  $vars
     * @param  list<string>  $rawHtmlKeys  Variable names inserted as HTML (already composed), not entity-escaped
     */
    public function render(string $template, array $vars, array $rawHtmlKeys = []): string
    {
        $this->assertNoDangerousBlade($template);

        return preg_replace_callback(self::PLACEHOLDER, function (array $m) use ($vars, $rawHtmlKeys): string {
            $key = $m[1];
            if (! array_key_exists($key, $vars)) {
                return '';
            }

            $val = $vars[$key];
            if ($val === null) {
                return '';
            }

            $str = is_scalar($val) || $val instanceof \Stringable
                ? (string) $val
                : '';

            if (in_array($key, $rawHtmlKeys, true)) {
                return $str;
            }

            return e($str);
        }, $template) ?? '';
    }

    private function assertNoDangerousBlade(string $template): void
    {
        if (preg_match('/\{!!/s', $template)) {
            throw new InvalidArgumentException('Unescaped Blade ({!!) is not allowed in email templates.');
        }

        if (preg_match('/@\s*(php|include|extends|section|component|props|aware)\b/is', $template)) {
            throw new InvalidArgumentException('Blade directives (@php, @include, etc.) are not allowed in email templates.');
        }
    }
}
