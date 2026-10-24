<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Servers;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Servers;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class ReadActivity extends ServerTool
{
    public function __construct(private readonly Servers $servers) {}

    public function name(): string
    {
        return 'read_activity';
    }

    public function title(): string
    {
        return 'Read activity log';
    }

    public function description(): string
    {
        return 'Read the activity log of a server, newest first: who ran which command, power action or file change, and when.';
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'limit' => Schema::integer('How many entries to return.', 1, 100, 25),
            'event' => Schema::string('Only return events starting with this, such as "server:file" or "server:power.start".', ['maxLength' => 100]),
        ], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['activity.read']);

        return ['activity' => $this->servers->activity(
            $server,
            $user,
            $arguments->int('limit', 25, 1, 100),
            $arguments->optionalString('event', 100),
        )];
    }
}
