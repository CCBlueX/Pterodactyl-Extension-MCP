<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools;

use Pterodactyl\Models\Server;

/**
 * A panel administration tool. Only root administrators get it, and only when the administrator turns admin tools on.
 * The Registry enforces both before call() runs.
 */
abstract class AdminTool implements Tool
{
    public function kind(): Kind
    {
        return Kind::Read;
    }

    /** Any server on the panel by its identifier or uuid, whatever its state. */
    protected function server(Arguments $arguments): Server
    {
        $identifier = $arguments->string('server', '', 64);
        $server = $identifier === '' ? null : Server::query()->whereUuidOrShort($identifier)->first();

        if ($server === null) {
            throw new ToolError("There is no server \"{$identifier}\". Use list_servers with all: true to see every server.");
        }

        return $server;
    }
}
