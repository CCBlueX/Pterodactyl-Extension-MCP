<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Startup;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Startup;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class SetStartupVariable extends ServerTool
{
    public function __construct(private readonly Startup $startup) {}

    public function name(): string
    {
        return 'set_startup_variable';
    }

    public function title(): string
    {
        return 'Set startup variable';
    }

    public function description(): string
    {
        return 'Change one editable startup variable; the value must meet the variable\'s rules from get_startup. Takes effect on the next start.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'key' => Schema::string('The environment variable name from get_startup, such as "SERVER_JARFILE".'),
            'value' => Schema::string('The new value. An empty string clears it.'),
        ], ['server', 'key', 'value']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['startup.update']);

        return $this->startup->setVariable($server, $arguments->string('key', '', 191), $arguments->string('value'));
    }
}
