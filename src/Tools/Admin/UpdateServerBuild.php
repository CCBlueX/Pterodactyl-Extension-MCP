<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Admin;

use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Admin;
use PterodactylMcp\Tools\AdminTool;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ToolError;

final class UpdateServerBuild extends AdminTool
{
    /** Tool argument => UpdatesServerBuild field, for the resource limits. */
    private const array LIMITS = ['memory_mib' => 'memory', 'swap_mib' => 'swap', 'disk_mib' => 'disk', 'cpu_percent' => 'cpu', 'io' => 'io'];

    /** Tool argument => UpdatesServerBuild field, for the feature limits. */
    private const array FEATURE_LIMITS = ['databases' => 'database_limit', 'allocations' => 'allocation_limit', 'backups' => 'backup_limit'];

    public function __construct(private readonly Admin $admin) {}

    public function name(): string
    {
        return 'admin_update_server';
    }

    public function title(): string
    {
        return 'Update server limits';
    }

    public function description(): string
    {
        return 'Change the resource and feature limits of any server, or give it more ports; limits left out keep their value. Wings gets the new limits right away, a running server may need a restart for some of them.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'memory_mib' => Schema::integer('Memory limit in MiB, 0 for unlimited.', 0),
            'swap_mib' => Schema::integer('Swap in MiB, 0 for none, -1 for unlimited.', -1),
            'disk_mib' => Schema::integer('Disk limit in MiB, 0 for unlimited.', 0),
            'cpu_percent' => Schema::integer('CPU limit in percent of one core, 0 for unlimited.', 0),
            'io' => Schema::integer('Block IO weight.', 10, 1000),
            'threads' => Schema::string('CPU cores the server may use, such as "0-1,3"; an empty string allows all.', ['pattern' => '^[0-9,-]*$', 'maxLength' => 191]),
            'oom_disabled' => Schema::boolean('Whether the OOM killer is disabled.'),
            'databases' => Schema::integer('How many databases the server may create.', 0),
            'allocations' => Schema::integer('How many allocations the server may add.', 0),
            'backups' => Schema::integer('How many backups the server may keep.', 0),
            'add_ports' => Schema::integer('Assign this many free allocations of the server\'s node, lowest ports first.', 1, 20),
            'remove_allocations' => Schema::list(['type' => 'integer'], 'Allocation ids from get_server to take away; never the primary one.', 20),
        ], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($arguments);
        $input = [];

        foreach (self::LIMITS as $argument => $field) {
            if ($arguments->has($argument)) {
                $input[$field] = $arguments->optionalInt($argument);
            }
        }

        if ($arguments->has('threads')) {
            $threads = $arguments->string('threads', null, 191);
            $input['threads'] = $threads === '' ? null : $threads;
        }

        if ($arguments->has('oom_disabled')) {
            $input['oom_disabled'] = $arguments->bool('oom_disabled');
        }

        $changes = $input !== [];

        // UpdatesServerBuild clears the feature limits left out, so the current ones are sent along.
        foreach (self::FEATURE_LIMITS as $argument => $field) {
            $changes = $changes || $arguments->has($argument);
            $input[$field] = $arguments->optionalInt($argument) ?? $server->{$field};
        }

        if ($arguments->has('add_ports')) {
            $input['add_allocations'] = $this->admin->freeAllocationIds($server->node, $arguments->int('add_ports', 1, 1, 20));
        }

        if ($arguments->has('remove_allocations')) {
            $input['remove_allocations'] = array_map(static fn (mixed $id): int => is_int($id) ? $id : throw new ToolError('Every item of "remove_allocations" must be an allocation id.'), $arguments->list('remove_allocations', 20));
        }

        if (! $changes && ! isset($input['add_allocations']) && ! isset($input['remove_allocations'])) {
            throw new ToolError('Pass at least one limit or allocation to change.');
        }

        $server = $this->admin->updateBuild($server, $input);

        return [
            'identifier' => $server->uuidShort,
            'name' => $server->name,
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
                'databases' => $server->database_limit,
                'allocations' => $server->allocation_limit,
                'backups' => $server->backup_limit,
            ],
            'allocations' => $server->allocations()->get()->map(static fn (Allocation $allocation): array => [
                'id' => $allocation->id,
                'address' => $allocation->ip_alias ?? $allocation->ip,
                'port' => $allocation->port,
                'primary' => $allocation->id === $server->allocation_id,
            ])->all(),
        ];
    }
}
