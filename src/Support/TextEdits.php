<?php

declare(strict_types=1);

namespace PterodactylMcp\Support;

use InvalidArgumentException;

/** Exact text replacements applied one after another, each to the result of the previous one. */
final class TextEdits
{
    private const int QUOTE_LENGTH = 60;

    /**
     * @param  list<array{old_text: string, new_text: string, replace_all?: bool}>  $edits
     * @return array{text: string, replacements: int}
     *
     * @throws InvalidArgumentException when an old_text is empty, missing or not unique
     */
    public static function apply(string $text, array $edits): array
    {
        $replacements = 0;

        foreach ($edits as $number => $edit) {
            $edit += ['replace_all' => false];
            $label = 'Edit '.($number + 1);
            $old = $edit['old_text'];

            if ($old === '') {
                throw new InvalidArgumentException("{$label} has an empty old_text. Give the exact text to replace.");
            }

            $count = mb_substr_count($text, $old, '8bit');

            if ($count === 0) {
                throw new InvalidArgumentException("{$label}: the old_text \"".self::quote($old).'" was not found. Read the file again and copy the text exactly, including whitespace.');
            }

            if ($count > 1 && ! $edit['replace_all']) {
                throw new InvalidArgumentException("{$label}: the old_text occurs {$count} times. Add surrounding lines to make it unique, or set replace_all to replace every occurrence.");
            }

            $text = $edit['replace_all']
                ? str_replace($old, $edit['new_text'], $text)
                : substr_replace($text, $edit['new_text'], (int) mb_strpos($text, $old, 0, '8bit'), mb_strlen($old, '8bit'));
            $replacements += $count;
        }

        return ['text' => $text, 'replacements' => $replacements];
    }

    private static function quote(string $text): string
    {
        $short = mb_strlen($text) > self::QUOTE_LENGTH ? mb_substr($text, 0, self::QUOTE_LENGTH).'…' : $text;

        return str_replace(["\r", "\n", "\t"], ['\r', '\n', '\t'], $short);
    }
}
