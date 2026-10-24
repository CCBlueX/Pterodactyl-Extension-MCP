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

final class DeleteSchedule extends ServerTool
{
    public function __construct(private readonly Schedules $schedules) {}

    public function name(): string
    {
        return 'delete_schedule';
    }

    public function title(): string
    {
        return 'Delete schedule';
    }

    public function description(): string
    {
        return 'Delete a schedule with all its tasks, or only one task when "task" is given (the tasks after it move up one place).';
    }

    public function kind(): Kind
    {
        return Kind::Destroy;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'schedule' => Schema::integer('The schedule id from list_schedules.', 1),
            'task' => Schema::integer('The id of the one task to delete, from list_schedules.', 1),
        ], ['server', 'schedule']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $taskId = $arguments->optionalInt('task');
        $server = $this->server($user, $arguments, [$taskId === null ? 'schedule.delete' : 'schedule.update']);
        $schedule = $this->schedules->find($server, $arguments->optionalInt('schedule') ?? throw new ToolError('The argument "schedule" is required.'));

        if ($taskId !== null) {
            $this->schedules->deleteTask($schedule, $this->schedules->task($schedule, $taskId));

            return ['deleted_task' => $taskId, 'schedule' => $this->schedules->describe($schedule->refresh())];
        }

        $this->schedules->delete($schedule);

        return ['deleted_schedule' => $schedule->id, 'name' => $schedule->name];
    }
}
