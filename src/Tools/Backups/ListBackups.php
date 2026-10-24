<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Backups;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Backups;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class ListBackups extends ServerTool
{
    public function __construct(private readonly Backups $backups) {}

    public function name(): string
    {
        return 'list_backups';
    }

    public function title(): string
    {
        return 'List backups';
    }

    public function description(): string
    {
        return 'List the backups of a server, newest first, with the backup limit and how many count towards it. successful is null while a backup is still running.';
    }

    public function schema(): array
    {
        return Schema::object(['server' => Schema::server()], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        return $this->backups->list($this->server($user, $arguments, ['backup.read']));
    }
}
