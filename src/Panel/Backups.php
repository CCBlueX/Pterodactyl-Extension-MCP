<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Pterodactyl\Contracts\Backups\DeletesBackups;
use Pterodactyl\Contracts\Backups\GeneratesBackupDownloadLinks;
use Pterodactyl\Contracts\Backups\InitiatesBackups;
use Pterodactyl\Contracts\Backups\RestoresBackups;
use Pterodactyl\Contracts\Backups\TogglesBackupLocks;
use Pterodactyl\Enum\ResourceLimit;
use Pterodactyl\Exceptions\Service\Backup\BackupLockedException;
use Pterodactyl\Exceptions\Service\Backup\TooManyBackupsException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use PterodactylMcp\Tools\ToolError;

/**
 * Panel contracts for backups, used like the Client BackupController. The contracts log their own activity.
 */
final readonly class Backups
{
    private const int MAX_LISTED = 100;

    public function __construct(
        private InitiatesBackups $initiate,
        private DeletesBackups $delete,
        private TogglesBackupLocks $toggle,
        private RestoresBackups $restore,
        private GeneratesBackupDownloadLinks $downloadLink,
    ) {}

    /** @return array<string, mixed> */
    public function list(Server $server): array
    {
        $backups = $server->backups()->orderByDesc('created_at')->orderByDesc('id')->limit(self::MAX_LISTED + 1)->get();
        $result = [
            'limit' => (int) $server->backup_limit,
            'used' => $server->backups()->nonFailed()->count(),
            'backups' => $backups->take(self::MAX_LISTED)->map($this->view(...))->values()->all(),
        ];

        return $backups->count() > self::MAX_LISTED ? $result + ['truncated' => true] : $result;
    }

    public function find(Server $server, string $uuid): Backup
    {
        $backup = $uuid === '' ? null : $server->backups()->where('uuid', $uuid)->first();

        return $backup ?? throw new ToolError("{$server->name} has no backup \"{$uuid}\". Use list_backups to see its backups.");
    }

    /**
     * Only users who may delete backups can create a locked one, as in the Client API.
     *
     * @param  list<string>  $ignored
     */
    public function create(User $user, Server $server, ?string $name, bool $locked, array $ignored): Backup
    {
        $action = $this->initiate
            ->setIgnoredFiles($ignored)
            ->setIsLocked($locked && $user->can('backup.delete', $server));

        try {
            return $action->initiate($server, $name);
        } catch (TooManyBackupsException $exception) {
            throw new ToolError($this->tooManyBackups($server, $exception));
        }
    }

    public function delete(Backup $backup): void
    {
        try {
            $this->delete->delete($backup);
        } catch (BackupLockedException) {
            throw new ToolError("The backup \"{$backup->name}\" is locked. Unlock it with lock_backup (locked: false) first.");
        }
    }

    public function toggleLock(Backup $backup): Backup
    {
        return $this->toggle->toggle($backup);
    }

    public function restore(User $user, Server $server, Backup $backup, bool $truncate): void
    {
        Throttle::hit($server, ResourceLimit::Backup);
        $this->restore->restore($server, $backup, $user, $truncate);
    }

    public function downloadUrl(User $user, Backup $backup): string
    {
        $url = $this->downloadLink->generate($backup, $user);

        Activity::event('server:backup.download')->subject($backup)->property('name', $backup->name)->log();

        return $url;
    }

    /** @return array<string, mixed> */
    public function view(Backup $backup): array
    {
        return [
            'uuid' => $backup->uuid,
            'name' => $backup->name,
            'size_bytes' => $backup->bytes,
            'successful' => $backup->completed_at === null ? null : $backup->is_successful,
            'locked' => $backup->is_locked,
            'checksum' => $backup->checksum,
            'ignored_files' => $backup->ignored_files ?? [],
            'created_at' => $backup->created_at?->toAtomString(),
            'completed_at' => $backup->completed_at?->toAtomString(),
        ];
    }

    private function tooManyBackups(Server $server, TooManyBackupsException $exception): string
    {
        if ((int) $server->backup_limit <= 0) {
            return "{$server->name} cannot have backups: its backup limit is 0. Ask an administrator to raise it.";
        }

        $oldest = $server->backups()->nonFailed()->where('is_locked', false)->orderBy('created_at')->limit(5)->get();

        if ($oldest->isEmpty()) {
            return $exception->getMessage().' Every backup is locked: unlock one with lock_backup, then delete it with delete_backup.';
        }

        $list = $oldest->map(static fn (Backup $backup): string => sprintf('%s "%s" (%s)', $backup->uuid, $backup->name, $backup->created_at?->toAtomString()))->implode('; ');

        return $exception->getMessage()." Delete one with delete_backup first. Oldest unlocked backups: {$list}.";
    }
}
