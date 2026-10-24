<?php

declare(strict_types=1);

namespace PterodactylMcp\Support;

/** Finds the lines a console printed between two reads of its tail. */
final class ConsoleDiff
{
    /**
     * Both lists are tails of the same console stream, oldest line first. The largest k where the last k lines of
     * $before equal the first k lines of $after is taken as the overlap; everything after it is new. Without any
     * overlap, lines were missed (or the console was cleared), so all of $after is returned as incomplete.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return array{lines: list<string>, complete: bool}
     */
    public static function newLines(array $before, array $after): array
    {
        if ($before === [] || $after === []) {
            return ['lines' => $after, 'complete' => true];
        }

        $countBefore = count($before);

        for ($overlap = min($countBefore, count($after)); $overlap > 0; $overlap--) {
            if (array_slice($before, $countBefore - $overlap) === array_slice($after, 0, $overlap)) {
                return ['lines' => array_slice($after, $overlap), 'complete' => true];
            }
        }

        return ['lines' => $after, 'complete' => false];
    }

    /**
     * @param  list<string>  $lines
     * @return list<string> The lines matching $grep (when given) and not matching $ignore (when given).
     */
    public static function filter(array $lines, ?Pattern $grep, ?Pattern $ignore): array
    {
        return array_values(array_filter($lines, static fn (string $line): bool => ($grep === null || $grep->matches($line)) && ($ignore === null || ! $ignore->matches($line))));
    }
}
