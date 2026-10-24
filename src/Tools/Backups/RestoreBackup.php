<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Backups;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Backups;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class RestoreBackup extends ServerTool
{
    public function __construct(private readonly Backups $backups) {}

    public function name(): string
    {
        return 'restore_backup';
    }

    public function title(): string
    {
        return 'Restore backup';
    }

    public function description(): string
    {
        return 'Unpack a finished backup over the server files; with truncate, every current file is deleted first. The server is unavailable until Wings reports the restore done (watch get_server status).';
    }

    public function kind(): Kind
    {
        return Kind::Destroy;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'backup' => Schema::string('The backup uuid from list_backups.'),
            'truncate' => Schema::boolean('Delete all current files before restoring.', false),
        ], ['server', 'backup']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['backup.restore']);
        $backup = $this->backups->find($server, $arguments->string('backup', '', 36));
        $truncate = $arguments->bool('truncate');

        $this->backups->restore($user, $server, $backup, $truncate);

        return ['restoring' => $backup->uuid, 'name' => $backup->name, 'truncate' => $truncate];
    }
}
