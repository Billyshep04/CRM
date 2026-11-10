<?php

namespace App\Services\EmailMarketing;

/**
 * Replaces {{tag}} and {{tag|fallback}} placeholders with plain string replacement.
 * Campaign HTML is never passed through Blade, so nothing else in it is touched.
 */
class MergeTags
{
    public const SUPPORTED = ['first_name', 'last_name', 'email', 'unsubscribe_url', 'view_in_browser_url'];

    private const PATTERN = '/\{\{\s*([a-zA-Z0-9_]+)\s*(?:\|([^}]*))?\}\}/';

    /**
     * @param  array<string, ?string>  $values
     * @param  bool  $escape  HTML-escape values (true for the HTML part, false for plain text).
     */
    public static function render(string $content, array $values, bool $escape = true): string
    {
        return preg_replace_callback(self::PATTERN, function (array $match) use ($values, $escape): string {
            $tag = strtolower($match[1]);
            if (!in_array($tag, self::SUPPORTED, true)) {
                return $match[0];
            }

            $value = trim((string) ($values[$tag] ?? ''));
            if ($value === '' && isset($match[2])) {
                $value = trim($match[2]);
            }

            return $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false) : $value;
        }, $content);
    }

    public static function contains(string $content, string $tag): bool
    {
        return preg_match_all(self::PATTERN, $content, $matches) > 0
            && in_array($tag, array_map('strtolower', $matches[1]), true);
    }

    /**
     * @return list<string> Tags in the content that this system doesn't know how to fill.
     */
    public static function unknown(string $content): array
    {
        preg_match_all(self::PATTERN, $content, $matches);

        return array_values(array_unique(array_filter(
            array_map('strtolower', $matches[1]),
            fn ($tag) => !in_array($tag, self::SUPPORTED, true),
        )));
    }
}
