<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Admin;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Admin;
use PterodactylMcp\Tools\AdminTool;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ToolError;

final class CreateServer extends AdminTool
{
    public function __construct(private readonly Admin $admin) {}

    public function name(): string
    {
        return 'admin_create_server';
    }

    public function title(): string
    {
        return 'Create server';
    }

    public function description(): string
    {
        return 'Create a server from an egg on a free allocation and start its install. Without node it goes to the public node with the most free memory; image, startup and variables default to the egg\'s (see admin_list_eggs). Memory, disk and CPU 0 mean unlimited, swap -1 unlimited.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'name' => Schema::string('The server name.', ['maxLength' => 191]),
            'egg' => Schema::string('The egg id or exact name from admin_list_eggs.'),
            'node' => Schema::string('The node id or exact name from admin_list_nodes.'),
            'owner_email' => Schema::string('Email of the user who owns the server; you by default.'),
            'memory_mib' => Schema::integer('Memory limit in MiB, 0 for unlimited.', 0),
            'disk_mib' => Schema::integer('Disk limit in MiB, 0 for unlimited.', 0),
            'cpu_percent' => Schema::integer('CPU limit in percent of one core, 0 for unlimited.', 0, null, 0),
            'swap_mib' => Schema::integer('Swap in MiB, 0 for none, -1 for unlimited.', -1, null, 0),
            'io' => Schema::integer('Block IO weight.', 10, 1000, 500),
            'docker_image' => Schema::string('The Docker image; the egg\'s first image by default.', ['maxLength' => 191]),
            'startup' => Schema::string('The startup command; the egg\'s by default.'),
            'environment' => ['type' => 'object', 'description' => 'Egg variable values by env name; variables left out keep the egg default.', 'additionalProperties' => ['type' => ['string', 'number', 'boolean']]],
            'databases' => Schema::integer('How many databases the server may create.', 0, null, 0),
            'allocations' => Schema::integer('How many allocations the server may add.', 0, null, 0),
            'backups' => Schema::integer('How many backups the server may keep.', 0, null, 0),
            'description' => Schema::string('The server description.'),
            'start_on_completion' => Schema::boolean('Start the server once the install finished.', false),
        ], ['name', 'egg', 'memory_mib', 'disk_mib']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $egg = $this->admin->egg($arguments->string('egg', null, 191));
        $memory = $this->limit($arguments, 'memory_mib', null);
        $disk = $this->limit($arguments, 'disk_mib', null);
        $node = $arguments->has('node') ? $this->admin->node($arguments->string('node', null, 191)) : $this->admin->roomiestNode($egg, $memory, $disk);
        $owner = $arguments->has('owner_email') ? $this->admin->user($arguments->string('owner_email', null, 191)) : $user;
        $allocation = $this->admin->freeAllocation($node);

        $server = $this->admin->create([
            'name' => $arguments->string('name', null, 191),
            'description' => $arguments->optionalString('description') ?? '',
            'owner_id' => $owner->id,
            'egg_id' => $egg->id,
            'image' => $arguments->optionalString('docker_image', 191) ?? $this->image($egg),
            'startup' => $arguments->optionalString('startup') ?? $this->startup($egg),
            'environment' => $this->environment($egg, $arguments->object('environment')),
            'memory' => $memory,
            'swap' => $this->limit($arguments, 'swap_mib', 0),
            'disk' => $disk,
            'io' => $this->limit($arguments, 'io', 500),
            'cpu' => $this->limit($arguments, 'cpu_percent', 0),
            'database_limit' => $this->limit($arguments, 'databases', 0),
            'allocation_limit' => $this->limit($arguments, 'allocations', 0),
            'backup_limit' => $this->limit($arguments, 'backups', 0),
            'allocation_id' => $allocation->id,
            'start_on_completion' => $arguments->bool('start_on_completion'),
        ]);

        return [
            'identifier' => $server->uuidShort,
            'name' => $server->name,
            'status' => $server->status,
            'node' => $node->name,
            'address' => $allocation->alias.':'.$allocation->port,
        ];
    }

    private function limit(Arguments $arguments, string $key, ?int $default): int
    {
        return $arguments->optionalInt($key) ?? $default ?? throw new ToolError("The argument \"{$key}\" is required.");
    }

    private function image(Egg $egg): string
    {
        $images = array_values($egg->docker_images ?? []);

        return $images[0] ?? throw new ToolError("The egg {$egg->name} has no Docker image. Pass docker_image.");
    }

    private function startup(Egg $egg): string
    {
        $startup = (string) $egg->startup;

        return $startup !== '' ? $startup : throw new ToolError("The egg {$egg->name} has no startup command. Pass startup.");
    }

    /**
     * Every variable of the egg with its default, overridden by the given values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function environment(Egg $egg, array $values): array
    {
        $environment = $egg->variables()->get()
            ->mapWithKeys(static fn ($variable): array => [$variable->env_variable => (string) ($variable->default_value ?? '')])
            ->all();

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $environment)) {
                $known = $environment === [] ? 'It has no variables.' : 'Its variables are: '.implode(', ', array_keys($environment)).'.';

                throw new ToolError("The egg {$egg->name} has no variable \"{$key}\". {$known}");
            }

            if (! is_scalar($value)) {
                throw new ToolError("The value of the variable \"{$key}\" must be a string, number or boolean.");
            }

            $environment[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        return $environment;
    }
}
