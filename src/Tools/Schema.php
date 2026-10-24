<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools;

/** JSON schema builders for tool arguments. */
final class Schema
{
    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    public static function object(array $properties = [], array $required = []): array
    {
        $schema = ['type' => 'object', 'properties' => $properties === [] ? (object) [] : $properties, 'additionalProperties' => false];

        return $required === [] ? $schema : $schema + ['required' => $required];
    }

    /**
     * @param  array<string, mixed>  $extra  such as maxLength or default
     * @return array<string, mixed>
     */
    public static function string(string $description, array $extra = []): array
    {
        return ['type' => 'string', 'description' => $description] + $extra;
    }

    /** @return array<string, mixed> */
    public static function integer(string $description, ?int $min = null, ?int $max = null, ?int $default = null): array
    {
        return array_filter(['type' => 'integer', 'description' => $description, 'minimum' => $min, 'maximum' => $max, 'default' => $default], static fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    public static function boolean(string $description, ?bool $default = null): array
    {
        return array_filter(['type' => 'boolean', 'description' => $description, 'default' => $default], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  list<string>  $values
     * @return array<string, mixed>
     */
    public static function enum(array $values, string $description, ?string $default = null): array
    {
        return self::string($description, array_filter(['enum' => $values, 'default' => $default], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, mixed>
     */
    public static function list(array $items, string $description, ?int $maxItems = null): array
    {
        return array_filter(['type' => 'array', 'items' => $items, 'description' => $description, 'maxItems' => $maxItems], static fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    public static function server(): array
    {
        return self::string('The server identifier from list_servers, such as "3b01dadb".');
    }

    /** @return array<string, mixed> */
    public static function pattern(string $description): array
    {
        return self::string($description.' A PCRE regular expression without delimiters; start with (?i) to ignore case.', ['maxLength' => 500]);
    }
}
