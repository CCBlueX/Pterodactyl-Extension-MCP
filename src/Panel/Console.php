<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Pterodactyl\Contracts\Servers\ReadsServerLogs;
use Pterodactyl\Contracts\Servers\ReadsServerState;
use Pterodactyl\Contracts\Servers\SendsServerCommands;
use Pterodactyl\Contracts\Servers\SendsServerPower;
use Pterodactyl\Data\ServerState;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Facades\Daemon;
use Pterodactyl\Models\Server;

/**
 * Panel contracts for state, console and power. The contracts log their own activity.
 */
final readonly class Console
{
    public function __construct(
        private ReadsServerState $state,
        private ReadsServerLogs $logs,
        private SendsServerCommands $commands,
        private SendsServerPower $power,
    ) {}

    public function state(Server $server): ServerState
    {
        return $this->state->read($server);
    }

    /** The state straight from Wings, skipping the 20 second cache, for polling until a power change lands. */
    public function freshState(Server $server): ServerState
    {
        return ServerState::fromDaemon(Daemon::server($server)->details());
    }

    /** @return list<string> The last console lines without colour codes, oldest first. */
    public function lines(Server $server, int $lines = ReadsServerLogs::MAX_LINES): array
    {
        return array_map(static fn (string $line): string => (string) preg_replace('/\e\[[0-9;?]*[A-Za-z]/', '', $line), $this->logs->read($server, min($lines, ReadsServerLogs::MAX_LINES)));
    }

    /**
     * Like lines(), but null while Wings cannot read the console, such as while a restart recreates the container.
     *
     * @return list<string>|null
     */
    public function linesWhilePolling(Server $server): ?array
    {
        try {
            return $this->lines($server);
        } catch (DaemonConnectionException) {
            return null;
        }
    }

    public function command(Server $server, string $command): void
    {
        $this->commands->send($server, $command);
    }

    public function power(Server $server, string $signal): void
    {
        $this->power->send($server, $signal);
    }
}
