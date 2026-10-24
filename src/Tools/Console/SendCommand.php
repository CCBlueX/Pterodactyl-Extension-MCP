<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Console;

use Pterodactyl\Contracts\Servers\ReadsServerLogs;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Console;
use PterodactylMcp\Settings;
use PterodactylMcp\Support\ConsoleDiff;
use PterodactylMcp\Support\Pattern;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class SendCommand extends ServerTool
{
    private const int MAX_COMMANDS = 20;

    private const int MAX_LENGTH = 1000;

    private const int MAX_WAIT = 30;

    /** Wings accepts console input while the server process runs, including while it starts. */
    private const array ACCEPTING_STATES = ['running', 'starting'];

    public function __construct(private readonly Console $console, private readonly Settings $settings) {}

    public function name(): string
    {
        return 'send_command';
    }

    public function title(): string
    {
        return 'Send console command';
    }

    public function description(): string
    {
        return 'Send one or more commands to the console of a running server and return the console lines printed while waiting. Waiting stops early once a new line matches until; output is null without the websocket.connect permission. complete is false when a burst of more than 100 lines outran the console buffer; read the server\'s log file with read_file for the full output.';
    }

    public function kind(): Kind
    {
        return Kind::Control;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'command' => Schema::string('The console command, such as "say hello" (Minecraft takes it without a leading slash).', ['maxLength' => self::MAX_LENGTH]),
            'commands' => Schema::list(Schema::string('One command.', ['maxLength' => self::MAX_LENGTH]), 'Several commands, sent in order about 250 ms apart. Use either command or commands.', self::MAX_COMMANDS),
            'wait_seconds' => Schema::integer('How long to collect console output after sending.', 0, self::MAX_WAIT, 3),
            'until' => Schema::pattern('Stop waiting as soon as a new console line matches this expression.'),
            'grep' => Schema::pattern('Only return the new lines matching this expression.'),
            'ignore' => Schema::pattern('Drop the new lines matching this expression.'),
        ], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['control.console']);
        $commands = $this->commands($arguments);
        $wait = $arguments->int('wait_seconds', 3, 0, self::MAX_WAIT);
        $until = Pattern::optional($arguments->optionalString('until', 500));
        $grep = Pattern::optional($arguments->optionalString('grep', 500));
        $ignore = Pattern::optional($arguments->optionalString('ignore', 500));

        $state = $this->console->freshState($server)->state;
        if (! in_array($state, self::ACCEPTING_STATES, true)) {
            throw new ToolError("{$server->name} is {$state}, so it cannot take commands. Start it with the power tool first.");
        }

        $watch = $user->can('websocket.connect', $server);
        $previous = $watch ? $this->console->lines($server, ReadsServerLogs::MAX_LINES) : [];

        set_time_limit($wait + 30);

        foreach ($commands as $index => $command) {
            if ($index > 0) {
                usleep(250_000);
            }

            $this->console->command($server, $command);
        }

        if (! $watch) {
            return ['sent' => $commands, 'output' => null, 'waited_ms' => 0];
        }

        $started = hrtime(true);
        $deadline = $started + $wait * 1_000_000_000;
        $lines = [];
        $complete = true;
        $matched = false;

        while (true) {
            if ($wait > 0) {
                usleep((int) max(0, min(500_000, ($deadline - hrtime(true)) / 1000)));
            }

            $current = $this->console->linesWhilePolling($server);

            if ($current !== null) {
                $diff = ConsoleDiff::newLines($previous, $current);
                $previous = $current;
                $complete = $complete && $diff['complete'];
                array_push($lines, ...$diff['lines']);
            }

            if ($until !== null && $current !== null && ConsoleDiff::filter($diff['lines'], $until, null) !== []) {
                $matched = true;
                break;
            }

            if (hrtime(true) >= $deadline) {
                break;
            }
        }

        [$output, $truncated] = $this->capped(ConsoleDiff::filter($lines, $grep, $ignore));

        return array_filter([
            'sent' => $commands,
            'output' => $output,
            'matched' => $until === null ? null : $matched,
            'complete' => $complete,
            'truncated' => $truncated ?: null,
            'waited_ms' => intdiv(hrtime(true) - $started, 1_000_000),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @return list<string> The commands to send, newlines stripped. */
    private function commands(Arguments $arguments): array
    {
        $single = $arguments->optionalString('command');
        $many = $arguments->strings('commands', self::MAX_COMMANDS);

        if (($single === null) === ($many === [])) {
            throw new ToolError('Pass exactly one of "command" (a single command) or "commands" (a list).');
        }

        return array_map(static function (string $command): string {
            $command = mb_trim((string) preg_replace('/[\r\n]+/', ' ', $command));

            if ($command === '') {
                throw new ToolError('Commands cannot be empty.');
            }

            if (mb_strlen($command) > self::MAX_LENGTH) {
                throw new ToolError('Commands can be at most '.self::MAX_LENGTH.' characters long. Split long input into several commands.');
            }

            return $command;
        }, $single === null ? $many : [$single]);
    }

    /**
     * @param  list<string>  $lines
     * @return array{list<string>, bool} The oldest lines that fit into the read limit, and whether any were cut.
     */
    private function capped(array $lines): array
    {
        $budget = $this->settings->maxReadBytes();

        foreach ($lines as $index => $line) {
            $budget -= mb_strlen($line, '8bit') + 1;

            if ($budget < 0) {
                return [array_slice($lines, 0, $index), true];
            }
        }

        return [$lines, false];
    }
}
