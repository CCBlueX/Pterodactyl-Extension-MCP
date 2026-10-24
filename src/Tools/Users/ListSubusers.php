<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Users;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Subusers;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class ListSubusers extends ServerTool
{
    public function __construct(private readonly Subusers $subusers) {}

    public function name(): string
    {
        return 'list_subusers';
    }

    public function title(): string
    {
        return 'List subusers';
    }

    public function description(): string
    {
        return 'List the subusers of a server with their permission keys. The owner is not listed; they have every permission.';
    }

    public function schema(): array
    {
        return Schema::object(['server' => Schema::server()], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['user.read']);

        return ['subusers' => array_map($this->subusers->describe(...), $this->subusers->all($server))];
    }
}
