<?php

namespace App\Messaging;

/**
 * Fills `{{ key }}` and `{{ key | fallback }}` merge tags from contact data.
 */
class TemplateRenderer
{
    protected const TAG = '/\{\{\s*([a-zA-Z0-9_.]+)\s*(?:\|\s*([^}]*?))?\s*\}\}/';

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(?string $content, array $data, bool $escape = false): ?string
    {
        if ($content === null) {
            return null;
        }

        return preg_replace_callback(self::TAG, function (array $match) use ($data, $escape) {
            $value = data_get($data, $match[1]);
            $value = blank($value) ? ($match[2] ?? '') : (string) $value;

            return $escape ? e($value) : $value;
        }, $content);
    }

    /**
     * Merge tag names used in the content.
     *
     * @return array<int, string>
     */
    public function tags(?string $content): array
    {
        preg_match_all(self::TAG, (string) $content, $matches);

        return array_values(array_unique($matches[1]));
    }
}
