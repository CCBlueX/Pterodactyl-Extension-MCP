<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Servers;

use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Console;
use PterodactylMcp\Panel\Servers;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class GetServer extends ServerTool
{
    private const int MIB = 1024 * 1024;

    public function __construct(private readonly Console $console) {}

    public function name(): string
    {
        return 'get_server';
    }

    public function title(): string
    {
        return 'Get server';
    }

    public function description(): string
    {
        return 'Show one server: live state and resource usage, limits, feature limits, allocations, SFTP login and your permissions on it. The live reading can be up to 20 seconds old.';
    }

    public function schema(): array
    {
        return Schema::object(['server' => Schema::server()], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, anyState: true);
        $server->loadMissing(['egg', 'node', 'allocation', 'transfer']);

        return [
            'identifier' => $server->uuidShort,
            'uuid' => $server->uuid,
            'name' => $server->name,
            'description' => $server->description,
            'game' => $server->egg?->name,
            'docker_image' => $server->image,
            'node' => $server->node->name,
            'status' => Servers::status($server),
            ...$this->live($server),
            'limits' => [
                'memory_mib' => $server->memory,
                'swap_mib' => $server->swap,
                'disk_mib' => $server->disk,
                'cpu_percent' => $server->cpu,
                'io' => $server->io,
                'threads' => $server->threads,
                'oom_disabled' => $server->oom_disabled,
            ],
            'feature_limits' => [
                'databases' => ['used' => $server->databases()->count(), 'limit' => $server->database_limit],
                'allocations' => ['used' => $server->allocations()->count(), 'limit' => $server->allocation_limit],
                'backups' => ['used' => $server->backups()->nonFailed()->count(), 'limit' => $server->backup_limit],
            ],
            'allocations' => $this->allocations($user, $server),
            'sftp' => $user->can('file.sftp', $server) ? [
                'host' => $server->node->fqdn,
                'port' => $server->node->daemonSFTP,
                'username' => "{$user->username}.{$server->uuidShort}",
            ] : null,
            'permissions' => array_values($user->permissionsOn($server)),
        ];
    }

    /** @return array<string, mixed> */
    private function live(Server $server): array
    {
        try {
            $state = $this->console->state($server);
        } catch (DaemonConnectionException $exception) {
            return ['state' => 'unknown', 'state_error' => $exception->getMessage(), 'usage' => null];
        }

        return [
            'state' => $state->state,
            'usage' => [
                'cpu_percent' => round((float) $state->cpuAbsolute, 1),
                'memory_mib' => round($state->memoryBytes / self::MIB, 1),
                'disk_mib' => round($state->diskBytes / self::MIB, 1),
                'network_rx_bytes' => $state->networkRxBytes,
                'network_tx_bytes' => $state->networkTxBytes,
                'uptime_seconds' => intdiv($state->uptime, 1000),
            ],
        ];
    }

    /**
     * Without allocation.read the Client API only shows the primary allocation, without its notes.
     *
     * @return list<array<string, mixed>>
     */
    private function allocations(User $user, Server $server): array
    {
        $canRead = $user->can('allocation.read', $server);
        $allocations = $canRead ? $server->allocations()->orderBy('ip')->orderBy('port')->get()->all() : array_filter([$server->allocation]);

        return array_values(array_map(static fn (Allocation $allocation): array => [
            'id' => $allocation->id,
            'address' => $allocation->alias,
            'port' => $allocation->port,
            'notes' => $canRead ? $allocation->notes : null,
            'primary' => $allocation->id === $server->allocation_id,
        ], $allocations));
    }
}
