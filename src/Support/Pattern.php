<?php

declare(strict_types=1);

namespace PterodactylMcp\Support;

use InvalidArgumentException;

/** A regular expression the model wrote, compiled once and checked before use. */
final readonly class Pattern
{
    private function __construct(private string $regex) {}

    /** @throws InvalidArgumentException when the expression does not compile */
    public static function compile(string $pattern): self
    {
        $regex = '~'.str_replace('~', '\~', $pattern).'~u';
        $error = null;

        set_error_handler(static function (int $level, string $message) use (&$error): bool {
            $error = preg_replace('/^preg_match\(\): (Compilation failed: )?/', '', $message);

            return true;
        });

        try {
            $valid = preg_match($regex, '') !== false;
        } finally {
            restore_error_handler();
        }

        if (! $valid) {
            throw new InvalidArgumentException("\"{$pattern}\" is not a valid regular expression: ".($error ?? preg_last_error_msg()).'.');
        }

        return new self($regex);
    }

    public static function optional(?string $pattern): ?self
    {
        return $pattern === null || $pattern === '' ? null : self::compile($pattern);
    }

    public function matches(string $subject): bool
    {
        return preg_match($this->regex, $subject) === 1;
    }
}
