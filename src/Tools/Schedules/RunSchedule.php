<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Schedules;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Schedules;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class RunSchedule extends ServerTool
{
    public function __construct(private readonly Schedules $schedules) {}

    public function name(): string
    {
        return 'run_schedule';
    }

    public function title(): string
    {
        return 'Run schedule';
    }

    public function description(): string
    {
        return 'Run a schedule now, whether or not it is active. Its first task runs at once and the rest follow on the queue; a schedule that is already running or has no tasks is refused.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'schedule' => Schema::integer('The schedule id from list_schedules.', 1),
        ], ['server', 'schedule']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['schedule.update']);
        $schedule = $this->schedules->find($server, $arguments->optionalInt('schedule') ?? throw new ToolError('The argument "schedule" is required.'));
        $this->schedules->run($schedule);

        return ['started' => true, 'schedule' => $this->schedules->describe($schedule->refresh())];
    }
}
