<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools;

/**
 * Typed access to the arguments of a tool call. Wrong types and missing required values become a ToolError the
 * model can correct; numbers outside their range are clamped.
 */
final readonly class Arguments
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values) {}

    /** Arguments of a nested object, such as one item of a list. */
    public static function of(mixed $value, string $key): self
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new ToolError("Every item of \"{$key}\" must be an object.");
        }

        return new self($value);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values) && $this->values[$key] !== null;
    }

    public function string(string $key, ?string $default = null, int $maxLength = 0): string
    {
        $value = $this->optionalString($key, $maxLength) ?? $default;

        if ($value === null) {
            throw new ToolError("The argument \"{$key}\" is required.");
        }

        return $value;
    }

    public function optionalString(string $key, int $maxLength = 0): ?string
    {
        if (! $this->has($key)) {
            return null;
        }

        $value = $this->values[$key];

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            throw new ToolError("The argument \"{$key}\" must be a string.");
        }

        if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
            throw new ToolError("The argument \"{$key}\" can be at most {$maxLength} characters long.");
        }

        return $value;
    }

    public function int(string $key, int $default, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        return max($min, min($max, $this->optionalInt($key) ?? $default));
    }

    public function optionalInt(string $key): ?int
    {
        if (! $this->has($key)) {
            return null;
        }

        $value = $this->values[$key];

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $value = (int) $value;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        if (! is_int($value)) {
            throw new ToolError("The argument \"{$key}\" must be a whole number.");
        }

        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        if (! $this->has($key)) {
            return $default;
        }

        $value = $this->values[$key];

        return match (true) {
            is_bool($value) => $value,
            $value === 'true', $value === 1 => true,
            $value === 'false', $value === 0 => false,
            default => throw new ToolError("The argument \"{$key}\" must be true or false."),
        };
    }

    /** @return list<mixed> */
    public function list(string $key, int $maxItems = 100): array
    {
        if (! $this->has($key)) {
            return [];
        }

        $value = $this->values[$key];

        if (! is_array($value) || ! array_is_list($value)) {
            throw new ToolError("The argument \"{$key}\" must be a list.");
        }

        if (count($value) > $maxItems) {
            throw new ToolError("The argument \"{$key}\" can hold at most {$maxItems} items.");
        }

        return $value;
    }

    /** @return list<string> */
    public function strings(string $key, int $maxItems = 100): array
    {
        return array_map(static function (mixed $item) use ($key): string {
            if (! is_string($item)) {
                throw new ToolError("Every item of \"{$key}\" must be a string.");
            }

            return $item;
        }, $this->list($key, $maxItems));
    }

    /** @return array<string, mixed> */
    public function object(string $key): array
    {
        if (! $this->has($key)) {
            return [];
        }

        $value = $this->values[$key];

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new ToolError("The argument \"{$key}\" must be an object.");
        }

        return $value;
    }
}
