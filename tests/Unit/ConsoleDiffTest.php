<?php

declare(strict_types=1);

namespace PterodactylMcp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PterodactylMcp\Support\ConsoleDiff;
use PterodactylMcp\Support\Pattern;

final class ConsoleDiffTest extends TestCase
{
    public function test_fresh_console_returns_the_appended_lines(): void
    {
        $this->assertSame(['lines' => ['c', 'd'], 'complete' => true], ConsoleDiff::newLines(['a', 'b'], ['a', 'b', 'c', 'd']));
        $this->assertSame(['lines' => [], 'complete' => true], ConsoleDiff::newLines(['a', 'b'], ['a', 'b']));
    }

    public function test_sliding_full_window(): void
    {
        $before = array_map(static fn (int $i): string => "line {$i}", range(1, 100));
        $after = array_map(static fn (int $i): string => "line {$i}", range(4, 103));

        $this->assertSame(['lines' => ['line 101', 'line 102', 'line 103'], 'complete' => true], ConsoleDiff::newLines($before, $after));
        $this->assertSame(['lines' => [], 'complete' => true], ConsoleDiff::newLines($before, $before));
    }

    public function test_repeated_identical_lines(): void
    {
        $this->assertSame(['lines' => ['a'], 'complete' => true], ConsoleDiff::newLines(['x', 'a', 'a'], ['a', 'a', 'a']));
        $this->assertSame(['lines' => ['b'], 'complete' => true], ConsoleDiff::newLines(['a', 'a', 'a'], ['a', 'a', 'a', 'b']));
        $this->assertSame(['lines' => [], 'complete' => true], ConsoleDiff::newLines(['a', 'a', 'a'], ['a', 'a', 'a']));
    }

    public function test_no_overlap_is_incomplete(): void
    {
        $this->assertSame(['lines' => ['x', 'y'], 'complete' => false], ConsoleDiff::newLines(['a', 'b'], ['x', 'y']));
        $this->assertSame(['lines' => ['b', 'a'], 'complete' => false], ConsoleDiff::newLines(['a', 'b', 'c'], ['b', 'a']));
    }

    public function test_empty_before_returns_everything(): void
    {
        $this->assertSame(['lines' => ['a', 'b'], 'complete' => true], ConsoleDiff::newLines([], ['a', 'b']));
        $this->assertSame(['lines' => [], 'complete' => true], ConsoleDiff::newLines([], []));
    }

    public function test_filter_keeps_grep_matches_and_drops_ignored_lines(): void
    {
        $lines = ['[INFO] Done', '[WARN] Slow tick', '[INFO] Saved', 'plain'];

        $this->assertSame($lines, ConsoleDiff::filter($lines, null, null));
        $this->assertSame(['[INFO] Done', '[INFO] Saved'], ConsoleDiff::filter($lines, Pattern::compile('^\[INFO\]'), null));
        $this->assertSame(['[INFO] Done', 'plain'], ConsoleDiff::filter($lines, null, Pattern::compile('(?i)slow|saved')));
        $this->assertSame(['[INFO] Done'], ConsoleDiff::filter($lines, Pattern::compile('INFO'), Pattern::compile('Saved')));
    }
}
