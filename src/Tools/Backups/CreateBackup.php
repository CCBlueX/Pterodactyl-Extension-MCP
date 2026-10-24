<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Backups;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Backups;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class CreateBackup extends ServerTool
{
    private const int MAX_WAIT_SECONDS = 60;

    public function __construct(private readonly Backups $backups) {}

    public function name(): string
    {
        return 'create_backup';
    }

    public function title(): string
    {
        return 'Create backup';
    }

    public function description(): string
    {
        return 'Start a backup of a server; it runs in the background unless wait_seconds is given. Locking needs the backup.delete permission and is ignored without it.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'name' => Schema::string('A name for the backup. Defaults to one with the start time.', ['maxLength' => 191]),
            'locked' => Schema::boolean('Lock the backup so it cannot be deleted or rotated out.', false),
            'ignored' => Schema::list(Schema::string('A file or folder, relative to the server root, in .gitignore syntax.'), 'Files and folders to leave out of the backup.', 1000),
            'wait_seconds' => Schema::integer('How long to wait for the backup to finish.', 0, self::MAX_WAIT_SECONDS, 0),
        ], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['backup.create']);
        $name = mb_trim($arguments->optionalString('name', 191) ?? '');
        $ignored = array_map(trim(...), $arguments->strings('ignored', 1000));
        $wait = $arguments->int('wait_seconds', 0, 0, self::MAX_WAIT_SECONDS);

        $backup = $this->backups->create($user, $server, $name === '' ? null : $name, $arguments->bool('locked'), $ignored);

        if ($wait > 0 && $backup->completed_at === null) {
            set_time_limit($wait + 30);
            $deadline = microtime(true) + $wait;

            while ($backup->completed_at === null && microtime(true) < $deadline) {
                usleep(1_000_000);
                $backup->refresh();
            }
        }

        return $this->backups->view($backup) + ['finished' => $backup->completed_at !== null];
    }
}
