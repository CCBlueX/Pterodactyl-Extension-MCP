<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Schedules;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Schedules;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class SaveSchedule extends ServerTool
{
    private const array CRON = ['minute', 'hour', 'day_of_month', 'month', 'day_of_week'];

    public function __construct(private readonly Schedules $schedules) {}

    public function name(): string
    {
        return 'save_schedule';
    }

    public function title(): string
    {
        return 'Save schedule';
    }

    public function description(): string
    {
        return 'Create a schedule, or update one when "schedule" is given; on update, fields left out keep their value. The cron fields use standard cron syntax in the panel timezone; add tasks with save_schedule_task.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'schedule' => Schema::integer('The id of the schedule to update, from list_schedules. Leave out to create one.', 1),
            'name' => Schema::string('The schedule name. Required when creating.', ['maxLength' => 191]),
            'minute' => Schema::string('Cron minute field, such as "*/5".', ['default' => '*']),
            'hour' => Schema::string('Cron hour field.', ['default' => '*']),
            'day_of_month' => Schema::string('Cron day-of-month field.', ['default' => '*']),
            'month' => Schema::string('Cron month field.', ['default' => '*']),
            'day_of_week' => Schema::string('Cron day-of-week field, 0-6 with 0 = Sunday.', ['default' => '*']),
            'is_active' => Schema::boolean('Whether the schedule runs on its timer.', true),
            'only_when_online' => Schema::boolean('Only run while the server is running.', false),
        ], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $id = $arguments->optionalInt('schedule');

        if ($id === null) {
            $server = $this->server($user, $arguments, ['schedule.create']);
            $input = ['name' => $arguments->string('name', null, 191)];
            foreach (self::CRON as $field) {
                $input[$field] = $arguments->string($field, '*', 191);
            }
            $input['is_active'] = $arguments->bool('is_active', true);
            $input['only_when_online'] = $arguments->bool('only_when_online');

            return $this->schedules->describe($this->schedules->create($server, $input));
        }

        $server = $this->server($user, $arguments, ['schedule.update']);
        $schedule = $this->schedules->find($server, $id);
        $input = ['name' => $arguments->string('name', $schedule->name, 191)];
        foreach (self::CRON as $field) {
            $input[$field] = $arguments->string($field, $schedule->getAttribute("cron_{$field}"), 191);
        }
        $input['is_active'] = $arguments->bool('is_active', $schedule->is_active);
        $input['only_when_online'] = $arguments->bool('only_when_online', $schedule->only_when_online);

        return $this->schedules->describe($this->schedules->update($schedule, $input));
    }
}
