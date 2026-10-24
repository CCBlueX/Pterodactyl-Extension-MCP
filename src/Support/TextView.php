<?php

declare(strict_types=1);

namespace PterodactylMcp\Support;

/**
 * The part of a text file one answer shows: a line range or the last lines, optionally filtered like
 * `grep -n -C`, cut at a byte limit on a line boundary.
 */
final readonly class TextView
{
    /** @param list<string> $lines */
    private function __construct(private array $lines) {}

    public static function of(string $text): self
    {
        if ($text === '') {
            return new self([]);
        }

        $lines = explode("\n", $text);

        if (str_ends_with($text, "\n")) {
            array_pop($lines);
        }

        return new self(array_map(static fn (string $line): string => str_ends_with($line, "\r") ? mb_substr($line, 0, -1) : $line, $lines));
    }

    public function totalLines(): int
    {
        return count($this->lines);
    }

    /**
     * The whole text, `$limit` lines from the 1-based line `$offset`, or the last `$tail` lines, without the lines
     * matching `$ignore`. from_line and to_line give the range covered.
     *
     * @return array{content: string, from_line: int, to_line: int, truncated: bool, next_offset?: int}
     */
    public function lines(?int $offset, ?int $limit, ?int $tail, ?Pattern $ignore, int $maxBytes): array
    {
        [$start, $end] = $this->window($offset, $limit, $tail);
        $shown = [];

        for ($index = $start; $index < $end; $index++) {
            if ($ignore === null || ! $ignore->matches($this->lines[$index])) {
                $shown[$index] = $this->lines[$index];
            }
        }

        $page = $this->page($shown, $maxBytes);
        $to = $page['next_offset'] === null ? $end : $page['next_offset'] - 1;

        return ['content' => $page['text'], 'from_line' => $start + 1, 'to_line' => $to, ...$this->truncation($page['next_offset'])];
    }

    /**
     * The lines matching `$grep` but not `$ignore` within the selected range, as `12:match` and `11-context`
     * with `--` between groups that are not next to each other.
     *
     * @return array{matches: string, match_count: int, truncated: bool, next_offset?: int}
     */
    public function grep(Pattern $grep, ?Pattern $ignore, int $context, ?int $offset, ?int $limit, ?int $tail, int $maxBytes): array
    {
        [$start, $end] = $this->window($offset, $limit, $tail);
        $hits = [];

        for ($index = $start; $index < $end; $index++) {
            $line = $this->lines[$index];

            if ($grep->matches($line) && ($ignore === null || ! $ignore->matches($line))) {
                $hits[] = $index;
            }
        }

        $matched = [];

        foreach ($hits as $hit) {
            for ($index = max($start, $hit - $context); $index <= min($end - 1, $hit + $context); $index++) {
                if ($index === $hit) {
                    $matched[$index] = true;
                } elseif (! isset($matched[$index]) && ($ignore === null || ! $ignore->matches($this->lines[$index]))) {
                    $matched[$index] = false;
                }
            }
        }

        ksort($matched);
        $shown = [];
        $previous = null;

        foreach ($matched as $index => $isMatch) {
            $separator = $context > 0 && $previous !== null && $index > $previous + 1 ? "--\n" : '';
            $shown[$index] = $separator.($index + 1).($isMatch ? ':' : '-').$this->lines[$index];
            $previous = $index;
        }

        $page = $this->page($shown, $maxBytes);

        return ['matches' => $page['text'], 'match_count' => count($hits), ...$this->truncation($page['next_offset'])];
    }

    /**
     * Join the lines (keyed by their 0-based index) until the next one would pass `$maxBytes`. A first line that
     * is too long on its own is cut so every page makes progress.
     *
     * @param  array<int, string>  $shown
     * @return array{text: string, next_offset: ?int}
     */
    private function page(array $shown, int $maxBytes): array
    {
        $text = '';

        foreach ($shown as $index => $line) {
            $size = mb_strlen($line, '8bit') + ($text === '' ? 0 : 1);

            if (mb_strlen($text, '8bit') + $size > $maxBytes) {
                if ($text === '') {
                    return ['text' => mb_strcut($line, 0, $maxBytes), 'next_offset' => $index + 2];
                }

                return ['text' => $text, 'next_offset' => $index + 1];
            }

            $text .= ($text === '' ? '' : "\n").$line;
        }

        return ['text' => $text, 'next_offset' => null];
    }

    /** @return array{truncated: bool, next_offset?: int} */
    private function truncation(?int $nextOffset): array
    {
        return $nextOffset === null ? ['truncated' => false] : ['truncated' => true, 'next_offset' => $nextOffset];
    }

    /** @return array{int, int} 0-based start (inclusive) and end (exclusive) of the selected lines. */
    private function window(?int $offset, ?int $limit, ?int $tail): array
    {
        $total = count($this->lines);

        if ($tail !== null) {
            return [max(0, $total - max(0, $tail)), $total];
        }

        $start = min($total, max(1, $offset ?? 1) - 1);

        return [$start, $limit === null ? $total : min($total, $start + max(0, $limit))];
    }
}
