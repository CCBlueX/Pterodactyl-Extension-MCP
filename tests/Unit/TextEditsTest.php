<?php

declare(strict_types=1);

namespace PterodactylMcp\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PterodactylMcp\Support\TextEdits;

final class TextEditsTest extends TestCase
{
    public function test_replaces_a_unique_occurrence(): void
    {
        $this->assertSame(
            ['text' => "motd=Hello\nmax-players=50\n", 'replacements' => 1],
            TextEdits::apply("motd=Hello\nmax-players=20\n", [['old_text' => 'max-players=20', 'new_text' => 'max-players=50']]),
        );
    }

    public function test_refuses_missing_text_quoting_a_short_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Edit 1: the old_text "line one\nline two" was not found.');

        TextEdits::apply('nothing here', [['old_text' => "line one\nline two", 'new_text' => 'x']]);
    }

    public function test_cuts_long_quotes(): void
    {
        try {
            TextEdits::apply('nothing here', [['old_text' => str_repeat('a', 100), 'new_text' => 'x']]);
            $this->fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('"'.str_repeat('a', 60).'…"', $exception->getMessage());
        }
    }

    public function test_refuses_multiple_occurrences_with_the_count(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Edit 1: the old_text occurs 3 times. Add surrounding lines to make it unique, or set replace_all');

        TextEdits::apply('a=1 a=1 a=1', [['old_text' => 'a=1', 'new_text' => 'a=2']]);
    }

    public function test_replaces_every_occurrence_with_replace_all(): void
    {
        $this->assertSame(
            ['text' => 'a=2 a=2 a=2', 'replacements' => 3],
            TextEdits::apply('a=1 a=1 a=1', [['old_text' => 'a=1', 'new_text' => 'a=2', 'replace_all' => true]]),
        );
    }

    public function test_applies_edits_in_order_to_the_previous_result(): void
    {
        $this->assertSame(
            ['text' => 'gamma beta', 'replacements' => 2],
            TextEdits::apply('alpha beta', [
                ['old_text' => 'alpha', 'new_text' => 'gamma alpha'],
                ['old_text' => 'gamma alpha', 'new_text' => 'gamma'],
            ]),
        );
    }

    public function test_names_the_failing_edit_in_a_sequence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Edit 2: the old_text "alpha" was not found.');

        TextEdits::apply('alpha beta', [
            ['old_text' => 'alpha', 'new_text' => 'gamma'],
            ['old_text' => 'alpha', 'new_text' => 'delta'],
        ]);
    }

    public function test_refuses_an_empty_old_text(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Edit 1 has an empty old_text.');

        TextEdits::apply('text', [['old_text' => '', 'new_text' => 'x']]);
    }
}
