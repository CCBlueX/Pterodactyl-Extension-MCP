<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Pterodactyl\Contracts\Schedules\CreatesSchedules;
use Pterodactyl\Contracts\Schedules\CreatesScheduleTasks;
use Pterodactyl\Contracts\Schedules\DeletesSchedules;
use Pterodactyl\Contracts\Schedules\DeletesScheduleTasks;
use Pterodactyl\Contracts\Schedules\ProcessesSchedules;
use Pterodactyl\Contracts\Schedules\UpdatesSchedules;
use Pterodactyl\Contracts\Schedules\UpdatesScheduleTasks;
use Pterodactyl\Enum\ResourceLimit;
use Pterodactyl\Models\Schedule;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Task;
use PterodactylMcp\Tools\ToolError;

/**
 * Panel contracts for schedules and their tasks. The contracts validate the input and log their own activity.
 */
final readonly class Schedules
{
    public function __construct(
        private CreatesSchedules $creates,
        private UpdatesSchedules $updates,
        private DeletesSchedules $deletes,
        private ProcessesSchedules $processes,
        private CreatesScheduleTasks $createsTasks,
        private UpdatesScheduleTasks $updatesTasks,
        private DeletesScheduleTasks $deletesTasks,
    ) {}

    /** @return list<Schedule> */
    public function all(Server $server): array
    {
        return $server->schedules()->with('tasks')->orderBy('id')->get()->all();
    }

    public function find(Server $server, int $id): Schedule
    {
        return $server->schedules()->with('tasks')->whereKey($id)->first()
            ?? throw new ToolError("There is no schedule {$id} on {$server->name}. Use list_schedules to see its schedules.");
    }

    public function task(Schedule $schedule, int $id): Task
    {
        return $schedule->tasks()->whereKey($id)->first()
            ?? throw new ToolError("Schedule {$schedule->id} has no task {$id}. Use list_schedules to see its tasks.");
    }

    /** @param array<string, mixed> $input */
    public function create(Server $server, array $input): Schedule
    {
        Throttle::hit($server, ResourceLimit::Schedule);

        return $this->creates->create($server, $input);
    }

    /** @param array<string, mixed> $input */
    public function update(Schedule $schedule, array $input): Schedule
    {
        return $this->updates->update($schedule, $input);
    }

    public function delete(Schedule $schedule): void
    {
        $this->deletes->delete($schedule);
    }

    public function run(Schedule $schedule): void
    {
        $this->processes->process($schedule, true);
    }

    /** @param array<string, mixed> $input */
    public function createTask(Schedule $schedule, array $input): Task
    {
        return $this->createsTasks->create($schedule, $input);
    }

    /** @param array<string, mixed> $input */
    public function updateTask(Schedule $schedule, Task $task, array $input): Task
    {
        return $this->updatesTasks->update($schedule, $task, $input);
    }

    public function deleteTask(Schedule $schedule, Task $task): void
    {
        $this->deletesTasks->delete($schedule, $task);
    }

    /** @return array<string, mixed> */
    public function describe(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'cron' => [
                'minute' => $schedule->cron_minute,
                'hour' => $schedule->cron_hour,
                'day_of_month' => $schedule->cron_day_of_month,
                'month' => $schedule->cron_month,
                'day_of_week' => $schedule->cron_day_of_week,
            ],
            'is_active' => $schedule->is_active,
            'only_when_online' => $schedule->only_when_online,
            'is_processing' => $schedule->is_processing,
            'last_run_at' => $schedule->last_run_at?->toAtomString(),
            'next_run_at' => $schedule->next_run_at?->toAtomString(),
            'tasks' => $schedule->tasks->sortBy('sequence_id')->values()->map($this->describeTask(...))->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function describeTask(Task $task): array
    {
        return [
            'id' => $task->id,
            'sequence_id' => $task->sequence_id,
            'action' => $task->action,
            'payload' => $task->payload,
            'time_offset' => $task->time_offset,
            'continue_on_failure' => $task->continue_on_failure,
        ];
    }
}
