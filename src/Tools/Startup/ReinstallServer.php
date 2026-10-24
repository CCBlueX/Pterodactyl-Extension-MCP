<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Startup;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Startup;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class ReinstallServer extends ServerTool
{
    public function __construct(private readonly Startup $startup) {}

    public function name(): string
    {
        return 'reinstall_server';
    }

    public function title(): string
    {
        return 'Reinstall server';
    }

    public function description(): string
    {
        return 'Stop the server and run its egg install script again, which can overwrite server files. The server is unavailable until the install finishes (watch get_server status).';
    }

    public function kind(): Kind
    {
        return Kind::Destroy;
    }

    public function schema(): array
    {
        return Schema::object(['server' => Schema::server()], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['settings.reinstall']);

        $this->startup->reinstall($server);

        return ['identifier' => $server->uuidShort, 'status' => $server->status];
    }
}
