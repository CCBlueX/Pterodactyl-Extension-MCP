<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Startup;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Startup;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class GetStartup extends ServerTool
{
    public function __construct(private readonly Startup $startup) {}

    public function name(): string
    {
        return 'get_startup';
    }

    public function title(): string
    {
        return 'Get startup settings';
    }

    public function description(): string
    {
        return 'Show the startup command (raw and with variables filled in), the Docker image and the images the egg offers, and the startup variables you can see. A null value means the variable uses its default.';
    }

    public function schema(): array
    {
        return Schema::object(['server' => Schema::server()], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        return $this->startup->view($this->server($user, $arguments, ['startup.read']));
    }
}
