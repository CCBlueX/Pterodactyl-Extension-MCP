<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Schedules;

use Pterodactyl\Enum\Permissions;
use Pterodactyl\Models\Task;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Schedules;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class SaveScheduleTask extends ServerTool
{
    public function __construct(private readonly Schedules $schedules) {}

    public function name(): string
    {
        return 'save_schedule_task';
    }

    public function title(): string
    {
        return 'Save schedule task';
    }

    public function description(): string
    {
        return 'Add a task to a schedule, or update one when "task" is given; on update, payload, time_offset and continue_on_failure left out keep their value. A schedule holds at most 10 tasks, and you need the permission the task uses (control.console, a power permission or backup.create).';
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
            'task' => Schema::integer('The id of the task to update, from list_schedules. Leave out to add a task.', 1),
            'action' => Schema::enum([Task::ACTION_COMMAND, Task::ACTION_POWER, Task::ACTION_BACKUP], 'What the task does.'),
            'payload' => Schema::string('command: the console command. power: start, stop, restart or kill. backup: files to leave out of the backup, one per line (may be empty).'),
            'time_offset' => Schema::integer('Seconds to wait after the previous task before running this one.', 0, 900, 0),
            'continue_on_failure' => Schema::boolean('Run the following tasks even when this one fails.', false),
            'sequence_id' => Schema::integer('The position in the schedule, starting at 1. New tasks go last when left out.', 1),
        ], ['server', 'schedule', 'action']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['schedule.update']);
        $schedule = $this->schedules->find($server, $arguments->optionalInt('schedule') ?? throw new ToolError('The argument "schedule" is required.'));
        $taskId = $arguments->optionalInt('task');
        $task = $taskId === null ? null : $this->schedules->task($schedule, $taskId);

        $action = $arguments->string('action', null, 16);
        $input = [
            'action' => $action,
            'payload' => $arguments->optionalString('payload') ?? $task?->payload ?? ($action === Task::ACTION_BACKUP ? '' : null),
            'time_offset' => $arguments->optionalInt('time_offset') ?? $task?->time_offset ?? 0,
            'continue_on_failure' => $arguments->bool('continue_on_failure', $task?->continue_on_failure ?? false),
        ];
        if ($arguments->has('sequence_id')) {
            $input['sequence_id'] = $arguments->optionalInt('sequence_id');
        }

        $permission = Task::permissionForAction($action, $input['payload']);
        if ($permission instanceof Permissions && ! $user->can($permission->value, $server)) {
            throw new ToolError("A {$action} task needs the {$permission->value} permission, which you do not have on {$server->name}.");
        }

        $saved = $task === null ? $this->schedules->createTask($schedule, $input) : $this->schedules->updateTask($schedule, $task, $input);

        return $this->schedules->describeTask($saved);
    }
}
