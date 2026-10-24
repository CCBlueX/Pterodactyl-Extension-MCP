<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Console;

use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Task;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Console;
use PterodactylMcp\Support\ConsoleDiff;
use PterodactylMcp\Support\Pattern;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class Power extends ServerTool
{
    private const int MAX_WAIT = 60;

    private const int POLL_MICROSECONDS = 1_000_000;

    public function __construct(private readonly Console $console) {}

    public function name(): string
    {
        return 'power';
    }

    public function title(): string
    {
        return 'Send power action';
    }

    public function description(): string
    {
        return 'Start, stop, restart or kill a server. With wait_seconds it waits until the server is running (start, restart; plus a new console line matching until, if given) or offline (stop, kill).';
    }

    public function kind(): Kind
    {
        return Kind::Control;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'signal' => Schema::enum(Task::POWER_ACTIONS, 'The power action. kill ends the process at once without saving.'),
            'wait_seconds' => Schema::integer('How long to wait for the server to reach the new state.', 0, self::MAX_WAIT, 0),
            'until' => Schema::pattern('For start and restart: also wait for a new console line matching this expression, such as "Done \\(".'),
        ], ['server', 'signal']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $signal = $arguments->string('signal');
        $permission = in_array($signal, Task::POWER_ACTIONS, true) ? Task::permissionForAction(Task::ACTION_POWER, $signal) : null;
        if ($permission === null) {
            throw new ToolError('The signal must be one of '.implode(', ', Task::POWER_ACTIONS).'.');
        }

        $server = $this->server($user, $arguments, [$permission->value]);
        $wait = $arguments->int('wait_seconds', 0, 0, self::MAX_WAIT);
        $until = Pattern::optional($arguments->optionalString('until', 500));
        $starts = $signal === 'start' || $signal === 'restart';

        if ($until !== null && ! $starts) {
            throw new ToolError('until only works with start and restart. Leave it out for stop and kill.');
        }

        if ($until !== null && $wait === 0) {
            throw new ToolError('until needs wait_seconds above 0, such as 60, to have time to watch the console.');
        }

        if ($until !== null && ! $user->can('websocket.connect', $server)) {
            throw new ToolError("Waiting for a console line needs the websocket.connect permission on {$server->name}. Leave until out to only wait for the state.");
        }

        $previous = $until !== null ? $this->console->linesWhilePolling($server) ?? [] : [];

        set_time_limit($wait + 30);
        $this->console->power($server, $signal);

        $started = hrtime(true);
        $deadline = $started + $wait * 1_000_000_000;
        $target = $starts ? 'running' : 'offline';
        $wentDown = $signal !== 'restart';
        $matchedLine = null;

        while (true) {
            $state = $this->state($server);
            $wentDown = $wentDown || ($state !== 'running' && $state !== 'unknown');
            $stateReached = $wentDown && $state === $target;

            $current = $until !== null && $matchedLine === null ? $this->console->linesWhilePolling($server) : null;

            if ($current !== null) {
                $new = ConsoleDiff::newLines($previous, $current)['lines'];
                $previous = $current;

                if ($wentDown) {
                    $matchedLine = ConsoleDiff::filter($new, $until, null)[0] ?? null;
                }
            }

            $reached = $stateReached && ($until === null || $matchedLine !== null);
            $remaining = $deadline - hrtime(true);

            if ($reached || $remaining <= 0) {
                break;
            }

            usleep((int) min(self::POLL_MICROSECONDS, $remaining / 1000));
        }

        $result = ['signal' => $signal, 'state' => $state, 'reached' => $reached];

        if ($until !== null) {
            $result['matched_line'] = $matchedLine;
        }

        return $result + ['waited_seconds' => round((hrtime(true) - $started) / 1_000_000_000, 1)];
    }

    /** The live state, or "unknown" while Wings does not answer, so a hiccup does not hide that the signal was sent. */
    private function state(Server $server): string
    {
        try {
            return $this->console->freshState($server)->state;
        } catch (DaemonConnectionException) {
            return 'unknown';
        }
    }
}
