<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Illuminate\Database\Eloquent\Builder;
use Pterodactyl\Contracts\Servers\CreatesServers;
use Pterodactyl\Contracts\Servers\DeletesServers;
use Pterodactyl\Contracts\Servers\TogglesServerSuspension;
use Pterodactyl\Contracts\Servers\UpdatesServerBuild;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use PterodactylMcp\Tools\ToolError;

/**
 * Panel contracts and lookups for the admin tools. The contracts do not log admin activity, so this logs the
 * same admin:server.* events the admin API controllers do.
 */
final readonly class Admin
{
    public function __construct(
        private CreatesServers $creator,
        private UpdatesServerBuild $builder,
        private TogglesServerSuspension $suspension,
        private DeletesServers $deleter,
    ) {}

    /** @return list<array<string, mixed>> */
    public function nodes(): array
    {
        return Node::query()
            ->with('location')
            ->withCount(['servers', 'allocations', 'allocations as free_allocations' => static fn (Builder $query) => $query->whereNull('server_id')])
            ->withSum('servers', 'memory')
            ->withSum('servers', 'disk')
            ->orderBy('name')
            ->get()
            ->map(static fn (Node $node): array => [
                'id' => $node->id,
                'name' => $node->name,
                'location' => $node->location?->short,
                'fqdn' => $node->fqdn,
                'public' => $node->public,
                'maintenance' => $node->maintenance_mode,
                'memory_mib' => ['total' => $node->memory, 'overallocate_percent' => $node->memory_overallocate, 'allocated' => (int) $node->servers_sum_memory],
                'disk_mib' => ['total' => $node->disk, 'overallocate_percent' => $node->disk_overallocate, 'allocated' => (int) $node->servers_sum_disk],
                'servers' => (int) $node->servers_count,
                'allocations' => ['total' => (int) $node->allocations_count, 'free' => (int) $node->free_allocations],
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function eggs(?string $search): array
    {
        return Egg::query()
            ->with('variables')
            ->when($search !== null && $search !== '', static fn (Builder $query) => $query->where(
                static fn (Builder $inner) => $inner->where('name', 'like', '%'.addcslashes((string) $search, '%_\\').'%')
                    ->orWhere('description', 'like', '%'.addcslashes((string) $search, '%_\\').'%'),
            ))
            ->orderBy('name')
            ->get()
            ->map(static fn (Egg $egg): array => [
                'id' => $egg->id,
                'name' => $egg->name,
                'description' => $egg->description,
                'docker_images' => $egg->docker_images ?? [],
                'startup' => $egg->startup,
                'variables' => $egg->variables->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values()->map(static fn (EggVariable $variable): array => [
                    'env' => $variable->env_variable,
                    'name' => $variable->name,
                    'default' => (string) ($variable->default_value ?? ''),
                    'rules' => (string) $variable->rules,
                    'user_editable' => $variable->user_editable,
                ])->all(),
            ])
            ->all();
    }

    /** An egg by its id or its exact name, ignoring case. */
    public function egg(string $reference): Egg
    {
        $eggs = ctype_digit($reference)
            ? Egg::query()->whereKey((int) $reference)->get()
            : Egg::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($reference)])->get();

        if ($eggs->count() > 1) {
            throw new ToolError("More than one egg is called \"{$reference}\" (ids ".$eggs->pluck('id')->implode(', ').'). Pass the egg id instead.');
        }

        return $eggs->first() ?? throw new ToolError("There is no egg \"{$reference}\". Use admin_list_eggs to see them.");
    }

    /** A node by its id or its exact name, ignoring case. */
    public function node(string $reference): Node
    {
        $node = ctype_digit($reference)
            ? Node::query()->whereKey((int) $reference)->first()
            : Node::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($reference)])->first();

        return $node ?? throw new ToolError("There is no node \"{$reference}\". Use admin_list_nodes to see them.");
    }

    public function user(string $email): User
    {
        return User::query()->where('email', $email)->first()
            ?? throw new ToolError("There is no user with the email \"{$email}\". Leave owner_email out to own the server yourself.");
    }

    /**
     * The public node the panel's own deployment would accept for this egg and size, with a free allocation and
     * not under maintenance, that has the most memory left.
     */
    public function roomiestNode(Egg $egg, int $memory, int $disk): Node
    {
        $nodes = Node::query()
            ->viableFor($memory, $disk)
            ->forEggTags($egg->tagSlugs())
            ->forDeployTags([])
            ->where('nodes.maintenance_mode', false)
            ->whereHas('allocations', static fn (Builder $query) => $query->whereNull('server_id'))
            ->get();

        return $nodes->sortByDesc(static fn (Node $node): float => $node->memory * (1 + $node->memory_overallocate / 100) - $node->allocatedResources()['memory'])->first()
            ?? throw new ToolError("No public node accepts {$egg->name} with room for {$memory} MiB memory and {$disk} MiB disk and a free allocation. Pick a node with the node argument (see admin_list_nodes).");
    }

    public function freeAllocation(Node $node): Allocation
    {
        return Allocation::query()->unassigned()->onNodes([$node->id])->inRandomOrder()->first()
            ?? throw new ToolError("Node {$node->name} has no free allocation. Add allocations to it in the admin panel or pick another node.");
    }

    /** @return list<int> The ids of $count free allocations on the node, lowest ports first. */
    public function freeAllocationIds(Node $node, int $count): array
    {
        $ids = Allocation::query()->unassigned()->onNodes([$node->id])->orderBy('port')->limit($count)->pluck('id')->all();

        if (count($ids) < $count) {
            throw new ToolError("Node {$node->name} has only ".count($ids).' free allocations. Add allocations to it in the admin panel.');
        }

        return $ids;
    }

    /** @param array<string, mixed> $input  CreatesServers input */
    public function create(array $input): Server
    {
        $server = $this->creator->create($input);

        Activity::event('admin:server.create')->subject($server)->property('name', $server->name)->log();

        return $server;
    }

    /** @param array<string, mixed> $input  UpdatesServerBuild input */
    public function updateBuild(Server $server, array $input): Server
    {
        $server = $this->builder->update($server, $input);

        Activity::event('admin:server.build')->subject($server)->property('name', $server->name)->log();

        return $server;
    }

    public function suspend(Server $server, bool $suspended): void
    {
        $this->suspension->toggle($server, $suspended ? TogglesServerSuspension::ACTION_SUSPEND : TogglesServerSuspension::ACTION_UNSUSPEND);

        Activity::event($suspended ? 'admin:server.suspend' : 'admin:server.unsuspend')->subject($server)->property('name', $server->name)->log();
    }

    public function delete(Server $server, bool $force): void
    {
        $this->deleter->withForce($force)->delete($server);

        Activity::event('admin:server.delete')->subject($server)->property('name', $server->name)->log();
    }
}
