<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Console;

use Pterodactyl\Contracts\Servers\ReadsServerLogs;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Console;
use PterodactylMcp\Support\ConsoleDiff;
use PterodactylMcp\Support\Pattern;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class ReadConsole extends ServerTool
{
    public function __construct(private readonly Console $console) {}

    public function name(): string
    {
        return 'read_console';
    }

    public function title(): string
    {
        return 'Read console';
    }

    public function description(): string
    {
        return 'Read the latest console output of a server, oldest line first. Wings keeps the last 100 lines; use read_file on the log files for older output.';
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'lines' => Schema::integer('How many of the latest lines to read.', 1, ReadsServerLogs::MAX_LINES, 50),
            'grep' => Schema::pattern('Only return the lines matching this expression.'),
            'ignore' => Schema::pattern('Drop the lines matching this expression.'),
        ], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['websocket.connect']);
        $grep = Pattern::optional($arguments->optionalString('grep', 500));
        $ignore = Pattern::optional($arguments->optionalString('ignore', 500));
        $lines = $this->console->lines($server, $arguments->int('lines', 50, 1, ReadsServerLogs::MAX_LINES));

        return ['lines' => ConsoleDiff::filter($lines, $grep, $ignore)];
    }
}
