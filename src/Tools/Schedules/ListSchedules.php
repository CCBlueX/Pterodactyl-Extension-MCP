<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Schedules;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Schedules;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class ListSchedules extends ServerTool
{
    public function __construct(private readonly Schedules $schedules) {}

    public function name(): string
    {
        return 'list_schedules';
    }

    public function title(): string
    {
        return 'List schedules';
    }

    public function description(): string
    {
        return 'List the schedules of a server with their cron fields, state and tasks in run order.';
    }

    public function schema(): array
    {
        return Schema::object(['server' => Schema::server()], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['schedule.read']);

        return ['schedules' => array_map($this->schedules->describe(...), $this->schedules->all($server))];
    }
}
