<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools;

use Pterodactyl\Exceptions\Http\Server\ServerStateConflictException;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;

/** A tool that works on one server the user can access, with the same checks as the Client API. */
abstract class ServerTool implements Tool
{
    public function kind(): Kind
    {
        return Kind::Read;
    }

    /**
     * Find the server and check every permission given. Unless $anyState is set, a server that is suspended,
     * installing, restoring a backup or being transferred is refused, as the Client API does.
     */
    protected function server(User $user, Arguments $arguments, array $permissions = [], bool $anyState = false): Server
    {
        $identifier = $arguments->string('server', '', 64);
        $server = $identifier === '' ? null : Server::query()
            ->where(static fn ($query) => $query->where('uuidShort', $identifier)->orWhere('uuid', $identifier))
            ->first();

        if ($server === null || ! self::isMember($user, $server)) {
            throw new ToolError("There is no server \"{$identifier}\" you can access. Use list_servers to see yours.");
        }

        foreach ($permissions as $permission) {
            if (! $user->can($permission, $server)) {
                throw new ToolError("You do not have the {$permission} permission on {$server->name}.");
            }
        }

        if (! $anyState) {
            try {
                $server->validateCurrentState();
            } catch (ServerStateConflictException) {
                throw new ToolError("{$server->name} is {$this->status($server)} right now. Try again when it is done.");
            }
        }

        return $server;
    }

    private static function isMember(User $user, Server $server): bool
    {
        return $user->root_admin || $server->owner_id === $user->id || $server->subusers()->where('user_id', $user->id)->exists();
    }

    private function status(Server $server): string
    {
        return match (true) {
            $server->isSuspended() => 'suspended',
            $server->node->isUnderMaintenance() => 'on a node under maintenance',
            ! $server->isInstalled() => 'installing or its install failed',
            $server->status === Server::STATUS_RESTORING_BACKUP => 'restoring a backup',
            default => 'being transferred',
        };
    }
}
