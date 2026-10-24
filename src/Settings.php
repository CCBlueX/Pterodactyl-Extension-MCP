<?php

declare(strict_types=1);

namespace PterodactylMcp;

use Pterodactyl\Services\Extensions\ExtensionSettingsRegistry;

final readonly class Settings
{
    public function __construct(private ExtensionSettingsRegistry $registry) {}

    /** The most text read_file returns in one answer. */
    public function maxReadBytes(): int
    {
        return max(1, (int) $this->get('max_read_kb', 256)) * 1024;
    }

    /** The largest file read in full for searching, paging or base64. */
    public function maxTransferBytes(): int
    {
        return max(1, (int) $this->get('max_transfer_mb', 16)) * 1024 * 1024;
    }

    private function get(string $key, mixed $default): mixed
    {
        return $this->registry->get('mcp')?->get($key) ?? $default;
    }
}
