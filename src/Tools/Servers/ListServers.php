<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Servers;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\Tool;

final class ListServers implements Tool
{
    public function __construct(private readonly Servers $servers) {}

    public function name(): string
    {
        return 'list_servers';
    }

    public function title(): string
    {
        return 'List servers';
    }

    public function description(): string
    {
        return 'List the servers you own or are a subuser of, with the identifier every other tool takes. Root admins can pass all to list every server on the panel.';
    }

    public function schema(): array
    {
        return Schema::object([
            'search' => Schema::string('Only list servers whose name, identifier or description contains this text, ignoring case.', ['maxLength' => 100]),
            'all' => Schema::boolean('List every server on the panel. Only works for root admins and is ignored otherwise.', false),
        ]);
    }

    public function kind(): Kind
    {
        return Kind::Read;
    }

    public function call(User $user, Arguments $arguments): array
    {
        $servers = $this->servers->accessible($user, $arguments->bool('all'), $arguments->optionalString('search', 100));

        return ['servers' => $servers->map(static fn (Server $server): array => [
            'identifier' => $server->uuidShort,
            'name' => $server->name,
            'description' => $server->description,
            'game' => $server->egg?->name,
            'node' => $server->node->name,
            'owner' => $server->owner_id === $user->id,
            'status' => Servers::status($server),
            'address' => $server->allocation === null ? null : "{$server->allocation->alias}:{$server->allocation->port}",
        ])->values()->all()];
    }
}
