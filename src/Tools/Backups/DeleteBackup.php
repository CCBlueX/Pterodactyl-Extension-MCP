<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Backups;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Backups;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class DeleteBackup extends ServerTool
{
    public function __construct(private readonly Backups $backups) {}

    public function name(): string
    {
        return 'delete_backup';
    }

    public function title(): string
    {
        return 'Delete backup';
    }

    public function description(): string
    {
        return 'Delete a backup and its stored archive. Locked backups are refused unless they failed.';
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
        ], ['server', 'backup']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['backup.delete']);
        $backup = $this->backups->find($server, $arguments->string('backup', '', 36));

        $this->backups->delete($backup);

        return ['deleted' => $backup->uuid, 'name' => $backup->name];
    }
}
