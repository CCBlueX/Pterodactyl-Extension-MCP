<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Backups;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Backups;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class LockBackup extends ServerTool
{
    public function __construct(private readonly Backups $backups) {}

    public function name(): string
    {
        return 'lock_backup';
    }

    public function title(): string
    {
        return 'Lock backup';
    }

    public function description(): string
    {
        return 'Lock or unlock a backup. A locked backup cannot be deleted and is never rotated out to make room for new ones.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'backup' => Schema::string('The backup uuid from list_backups.'),
            'locked' => Schema::boolean('true to lock, false to unlock.'),
        ], ['server', 'backup', 'locked']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['backup.delete']);
        $backup = $this->backups->find($server, $arguments->string('backup', '', 36));

        if (! $arguments->has('locked')) {
            throw new ToolError('The argument "locked" is required.');
        }

        if ($backup->is_locked !== $arguments->bool('locked')) {
            $backup = $this->backups->toggleLock($backup);
        }

        return $this->backups->view($backup);
    }
}
