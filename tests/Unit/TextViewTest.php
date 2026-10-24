<?php

declare(strict_types=1);

namespace PterodactylMcp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PterodactylMcp\Support\Pattern;
use PterodactylMcp\Support\TextView;

final class TextViewTest extends TestCase
{
    private const string TEXT = "one\ntwo\r\nthree\nfour\nfive\n";

    public function test_splits_lines_without_the_final_newline_or_carriage_returns(): void
    {
        $this->assertSame(5, TextView::of(self::TEXT)->totalLines());
        $this->assertSame(2, TextView::of("a\nb")->totalLines());
        $this->assertSame(0, TextView::of('')->totalLines());
        $this->assertSame(
            ['content' => "one\ntwo\nthree\nfour\nfive", 'from_line' => 1, 'to_line' => 5, 'truncated' => false],
            TextView::of(self::TEXT)->lines(null, null, null, null, 1000),
        );
    }

    public function test_selects_by_offset_and_limit(): void
    {
        $view = TextView::of(self::TEXT);

        $this->assertSame(['content' => "two\nthree", 'from_line' => 2, 'to_line' => 3, 'truncated' => false], $view->lines(2, 2, null, null, 1000));
        $this->assertSame(['content' => "four\nfive", 'from_line' => 4, 'to_line' => 5, 'truncated' => false], $view->lines(4, null, null, null, 1000));
        $this->assertSame(['content' => '', 'from_line' => 6, 'to_line' => 5, 'truncated' => false], $view->lines(9, 3, null, null, 1000));
    }

    public function test_selects_the_tail(): void
    {
        $view = TextView::of(self::TEXT);

        $this->assertSame(['content' => "four\nfive", 'from_line' => 4, 'to_line' => 5, 'truncated' => false], $view->lines(null, null, 2, null, 1000));
        $this->assertSame(['content' => "one\ntwo\nthree\nfour\nfive", 'from_line' => 1, 'to_line' => 5, 'truncated' => false], $view->lines(null, null, 50, null, 1000));
    }

    public function test_drops_ignored_lines(): void
    {
        $this->assertSame(
            ['content' => "one\nthree\nfive", 'from_line' => 1, 'to_line' => 5, 'truncated' => false],
            TextView::of(self::TEXT)->lines(null, null, null, Pattern::compile('^(two|four)$'), 1000),
        );
    }

    public function test_caps_bytes_on_a_line_boundary(): void
    {
        $view = TextView::of(self::TEXT);

        $this->assertSame(['content' => "one\ntwo", 'from_line' => 1, 'to_line' => 2, 'truncated' => true, 'next_offset' => 3], $view->lines(null, null, null, null, 10));
        $this->assertSame(['content' => "three\nfour", 'from_line' => 3, 'to_line' => 4, 'truncated' => true, 'next_offset' => 5], $view->lines(3, null, null, null, 10));
        $this->assertSame(['content' => 'thr', 'from_line' => 3, 'to_line' => 3, 'truncated' => true, 'next_offset' => 4], $view->lines(3, null, null, null, 3));
    }

    public function test_greps_with_line_numbers(): void
    {
        $this->assertSame(
            ['matches' => "2:two\n3:three", 'match_count' => 2, 'truncated' => false],
            TextView::of(self::TEXT)->grep(Pattern::compile('^t'), null, 0, null, null, null, 1000),
        );
    }

    public function test_greps_with_context_and_group_separators(): void
    {
        $view = TextView::of("a\nhit\nb\nc\nd\ne\nhit\nf\n");

        $this->assertSame(
            ['matches' => "1-a\n2:hit\n3-b\n--\n6-e\n7:hit\n8-f", 'match_count' => 2, 'truncated' => false],
            $view->grep(Pattern::compile('hit'), null, 1, null, null, null, 1000),
        );
        $this->assertSame(
            ['matches' => "1-a\n2:hit\n3-b\n4-c\n5-d\n6-e\n7:hit\n8-f", 'match_count' => 2, 'truncated' => false],
            $view->grep(Pattern::compile('hit'), null, 2, null, null, null, 1000),
        );
    }

    public function test_greps_within_the_selected_range_and_ignores_lines(): void
    {
        $view = TextView::of("x 1\nx 2\nx skip\nx 4\n");

        $this->assertSame(
            ['matches' => "2:x 2\n4:x 4", 'match_count' => 2, 'truncated' => false],
            $view->grep(Pattern::compile('^x'), Pattern::compile('skip'), 0, 2, null, null, 1000),
        );
        $this->assertSame(
            ['matches' => '4:x 4', 'match_count' => 1, 'truncated' => false],
            $view->grep(Pattern::compile('^x'), null, 0, null, null, 1, 1000),
        );
    }

    public function test_caps_grep_output_and_points_at_the_next_line(): void
    {
        $view = TextView::of("hit 1\nhit 2\nhit 3\n");

        $this->assertSame(
            ['matches' => "1:hit 1\n2:hit 2", 'match_count' => 3, 'truncated' => true, 'next_offset' => 3],
            $view->grep(Pattern::compile('hit'), null, 0, null, null, null, 16),
        );
    }
}
