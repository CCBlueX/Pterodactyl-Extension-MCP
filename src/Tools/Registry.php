<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools;

use Illuminate\Contracts\Container\Container;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Extensions\ExtensionSettingsRegistry;

final readonly class Registry
{
    /** @var list<class-string<Tool>> */
    private const array TOOLS = [
        Servers\ListServers::class,
        Servers\GetServer::class,
        Servers\ReadActivity::class,
        Console\ReadConsole::class,
        Console\SendCommand::class,
        Console\Power::class,
        Files\ListFiles::class,
        Files\ReadFile::class,
        Files\DownloadFileUrl::class,
        Files\WriteFile::class,
        Files\EditFile::class,
        Files\CreateFolder::class,
        Files\RenameFiles::class,
        Files\CopyFile::class,
        Files\DeleteFiles::class,
        Files\CompressFiles::class,
        Files\DecompressFile::class,
        Files\ChmodFiles::class,
        Files\PullFile::class,
        Files\UploadFileUrl::class,
        Backups\ListBackups::class,
        Backups\CreateBackup::class,
        Backups\DeleteBackup::class,
        Backups\LockBackup::class,
        Backups\RestoreBackup::class,
        Backups\BackupDownloadUrl::class,
        Startup\GetStartup::class,
        Startup\SetStartupVariable::class,
        Startup\SetDockerImage::class,
        Startup\RenameServer::class,
        Startup\ReinstallServer::class,
        Network\AddAllocation::class,
        Network\UpdateAllocation::class,
        Network\RemoveAllocation::class,
        Databases\ListDatabases::class,
        Databases\CreateDatabase::class,
        Databases\RotateDatabasePassword::class,
        Databases\DeleteDatabase::class,
        Schedules\ListSchedules::class,
        Schedules\SaveSchedule::class,
        Schedules\SaveScheduleTask::class,
        Schedules\DeleteSchedule::class,
        Schedules\RunSchedule::class,
        Users\ListSubusers::class,
        Users\SaveSubuser::class,
        Users\RemoveSubuser::class,
        Admin\ListNodes::class,
        Admin\ListEggs::class,
        Admin\CreateServer::class,
        Admin\UpdateServerBuild::class,
        Admin\SuspendServer::class,
        Admin\DeleteServer::class,
    ];

    public function __construct(private Container $container, private ExtensionSettingsRegistry $settings) {}

    public static function group(Tool $tool): string
    {
        $parts = explode('\\', $tool::class);

        return $parts[count($parts) - 2];
    }

    /** @return array<string, Tool> The tools this user is offered, by name. */
    public function for(User $user): array
    {
        $settings = $this->settings->get('mcp');
        $changes = (bool) ($settings?->get('allow_write_tools') ?? false);
        $admin = $user->root_admin && (bool) ($settings?->get('allow_admin_tools') ?? false);
        $tools = [];

        foreach (self::TOOLS as $class) {
            $tool = $this->container->make($class);

            if (($changes || ! $tool->kind()->changes()) && ($admin || ! $tool instanceof AdminTool)) {
                $tools[$tool->name()] = $tool;
            }
        }

        return $tools;
    }
}
