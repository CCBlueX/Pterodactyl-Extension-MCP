<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Backups;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Backups;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class BackupDownloadUrl extends ServerTool
{
    public function __construct(private readonly Backups $backups) {}

    public function name(): string
    {
        return 'backup_download_url';
    }

    public function title(): string
    {
        return 'Backup download link';
    }

    public function description(): string
    {
        return 'Get a short-lived link to download a backup archive (.tar.gz). The link works for 15 minutes, or 5 for backups stored in S3.';
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
        $server = $this->server($user, $arguments, ['backup.download']);
        $backup = $this->backups->find($server, $arguments->string('backup', '', 36));

        return ['url' => $this->backups->downloadUrl($user, $backup)];
    }
}
