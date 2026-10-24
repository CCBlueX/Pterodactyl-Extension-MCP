<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Pterodactyl\Models\ActivityLog;

/**
 * Marks every activity log entry written while a tool runs as coming from MCP. The Panel's own actions log what
 * they do, so this tags their entries instead of logging a second one.
 */
final class ActivityTag
{
    private static bool $listening = false;

    private static bool $active = false;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function during(callable $callback): mixed
    {
        if (! self::$listening) {
            ActivityLog::creating(static function (ActivityLog $log): void {
                if (self::$active) {
                    $log->properties = $log->properties->put('via', 'mcp');
                }
            });
            self::$listening = true;
        }

        self::$active = true;

        try {
            return $callback();
        } finally {
            self::$active = false;
        }
    }
}
