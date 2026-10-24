<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Illuminate\Support\Facades\RateLimiter;
use Pterodactyl\Enum\ResourceLimit;
use Pterodactyl\Models\Server;
use PterodactylMcp\Tools\ToolError;

/** Counts against the same bucket as the Client API route throttle of the resource. */
final class Throttle
{
    public static function hit(Server $server, ResourceLimit $resource): void
    {
        $limit = $resource->limit();
        $key = md5($resource->throttleKey().$server->uuid);

        if (RateLimiter::tooManyAttempts($key, $limit->maxAttempts)) {
            throw new ToolError(sprintf('%s reached the panel\'s limit for this action. Try again in %d seconds.', $server->name, RateLimiter::availableIn($key)));
        }

        RateLimiter::hit($key, $limit->decaySeconds);
    }
}
