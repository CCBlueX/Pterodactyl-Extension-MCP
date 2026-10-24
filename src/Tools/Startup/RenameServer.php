<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Startup;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Startup;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class RenameServer extends ServerTool
{
    public function __construct(private readonly Startup $startup) {}

    public function name(): string
    {
        return 'rename_server';
    }

    public function title(): string
    {
        return 'Rename server';
    }

    public function description(): string
    {
        return 'Change the name of a server and, when given, its description. An empty description clears it; leaving it out keeps the current one.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'name' => Schema::string('The new name.', ['minLength' => 1, 'maxLength' => 191]),
            'description' => Schema::string('The new description.'),
        ], ['server', 'name']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['settings.rename']);
        $server = $this->startup->rename($server, $arguments->string('name'), $arguments->optionalString('description'));

        return ['identifier' => $server->uuidShort, 'name' => $server->name, 'description' => $server->description];
    }
}
