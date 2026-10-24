<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Validator;
use Pterodactyl\Contracts\Files\ChangesFilePermissions;
use Pterodactyl\Contracts\Files\CompressesFiles;
use Pterodactyl\Contracts\Files\CopiesFiles;
use Pterodactyl\Contracts\Files\CreatesDirectories;
use Pterodactyl\Contracts\Files\DecompressesFiles;
use Pterodactyl\Contracts\Files\DeletesFiles;
use Pterodactyl\Contracts\Files\ListsDirectories;
use Pterodactyl\Contracts\Files\PullsFiles;
use Pterodactyl\Contracts\Files\ReadsFileContents;
use Pterodactyl\Contracts\Files\RenamesFiles;
use Pterodactyl\Contracts\Files\WritesFileContents;
use Pterodactyl\Enum\JwtScope;
use Pterodactyl\Enum\ResourceLimit;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Exceptions\Http\Server\FileSizeTooLargeException;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Nodes\NodeJwt;
use PterodactylMcp\Tools\ToolError;

/**
 * Panel contracts for server files. The contracts log their own activity except reading and downloading, which
 * the Client API controller logs itself and so does this class.
 */
final readonly class Files
{
    public function __construct(
        private ListsDirectories $lister,
        private ReadsFileContents $reader,
        private WritesFileContents $writer,
        private CreatesDirectories $directories,
        private RenamesFiles $renamer,
        private CopiesFiles $copier,
        private DeletesFiles $deleter,
        private CompressesFiles $compressor,
        private DecompressesFiles $decompressor,
        private ChangesFilePermissions $permissions,
        private PullsFiles $puller,
        private NodeJwt $jwt,
    ) {}

    /** The path with `.` and `..` applied and one leading slash, such as "/plugins/config.yml" or "/". */
    public static function normalize(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }

        return '/'.implode('/', $segments);
    }

    /** @param array<string, mixed> $entry */
    public static function isDirectory(array $entry): bool
    {
        return ! ($entry['file'] ?? true);
    }

    /**
     * The entries of a directory as list_files shows them, with paths from the server root.
     *
     * @return list<array{path: string, name: string, directory: bool, size_bytes: int, modified: string, mode: ?string, mime: string}>
     */
    public function list(Server $server, string $directory): array
    {
        $directory = self::normalize($directory);

        try {
            $entries = $this->lister->list($server, $directory);
        } catch (DaemonConnectionException $exception) {
            $this->explainMissing($server, $directory, $exception, expectDirectory: true);
        }

        return array_map(fn (array $entry): array => $this->describe($entry, $directory), $entries);
    }

    /**
     * The Wings entry of a file or directory from its parent listing, or null when it does not exist. The root
     * always exists.
     *
     * @return array<string, mixed>|null
     */
    public function entry(Server $server, string $path): ?array
    {
        $path = self::normalize($path);

        if ($path === '/') {
            return ['name' => '', 'file' => false];
        }

        try {
            $entries = $this->lister->list($server, dirname($path));
        } catch (DaemonConnectionException $exception) {
            if (self::isMissing($exception)) {
                return null;
            }

            throw $exception;
        }

        foreach ($entries as $entry) {
            if (($entry['name'] ?? null) === basename($path)) {
                return $entry;
            }
        }

        return null;
    }

    /** The raw contents of a file, refused when larger than `$maxBytes`. Does not log; see logRead(). */
    public function read(Server $server, string $path, int $maxBytes): string
    {
        try {
            return $this->reader->read($server, $path, $maxBytes);
        } catch (FileSizeTooLargeException) {
            throw new ToolError("{$path} is larger than the ".self::size($maxBytes).' this MCP server reads in full. Use download_file_url to fetch it instead.');
        } catch (DaemonConnectionException $exception) {
            $this->explainMissing($server, self::normalize($path), $exception, expectDirectory: false);
        }
    }

    public function logRead(Server $server, string $path): void
    {
        Activity::event('server:file.read')->subject($server)->property('file', $path)->log();
    }

    public function write(Server $server, string $path, string $contents): void
    {
        $this->writer->write($server, $path, $contents);
    }

    public function createFolder(Server $server, string $name, string $root): void
    {
        $this->directories->create($server, $name, $root);
    }

    /** @param list<array{from: string, to: string}> $files */
    public function rename(Server $server, string $root, array $files): void
    {
        $this->renamer->rename($server, $root, $files);
    }

    public function copy(Server $server, string $location): void
    {
        $this->copier->copy($server, $location);
    }

    /** @param list<string> $files */
    public function delete(Server $server, string $root, array $files): void
    {
        $this->deleter->delete($server, $root, $files);
    }

    /**
     * @param  list<string>  $files
     * @return array{path: string, name: string, directory: bool, size_bytes: int, modified: string, mode: ?string, mime: string}
     */
    public function compress(Server $server, string $root, array $files): array
    {
        return $this->describe($this->compressor->compress($server, $root, $files), self::normalize($root));
    }

    public function decompress(Server $server, string $root, string $file): void
    {
        $this->decompressor->decompress($server, $root, $file);
    }

    /** @param list<array{file: string, mode: string}> $files */
    public function chmod(Server $server, string $root, array $files): void
    {
        $this->permissions->change($server, $root, $files);
    }

    /**
     * Validated like PullFileRequest and throttled in the same bucket as the Client API route.
     *
     * @param  array{filename?: string, use_header?: bool, foreground?: bool}  $options
     */
    public function pull(Server $server, string $url, ?string $directory, array $options): void
    {
        Validator::make(['url' => $url, 'directory' => $directory, ...$options], [
            'url' => ['required', 'string', 'url', 'max:2048'],
            'directory' => ['nullable', 'string', 'max:2048'],
            'filename' => ['nullable', 'string', 'max:255'],
            'use_header' => ['boolean'],
            'foreground' => ['boolean'],
        ])->validate();

        Throttle::hit($server, ResourceLimit::FilePull);

        $this->puller->pull($server, $url, $directory, $options);
    }

    /** @return array{url: string, expires_at: string} */
    public function downloadUrl(User $user, Server $server, string $file): array
    {
        $expiresAt = CarbonImmutable::now()->addMinutes(15);
        $token = $this->jwt->issue(
            $server->node,
            JwtScope::FileDownload,
            identifiedBy: $user->id.$server->uuid,
            expiresAt: $expiresAt,
            user: $user,
            claims: ['file_path' => rawurldecode($file), 'server_uuid' => $server->uuid],
        );

        Activity::event('server:file.download')->subject($server)->property('file', $file)->log();

        return [
            'url' => sprintf('%s/download/file?token=%s', $server->node->getConnectionAddress(), $token->toString()),
            'expires_at' => $expiresAt->toAtomString(),
        ];
    }

    /** @return array{url: string, expires_at: string} */
    public function uploadUrl(User $user, Server $server, string $directory): array
    {
        $expiresAt = CarbonImmutable::now()->addMinutes(15);
        $token = $this->jwt->issue(
            $server->node,
            JwtScope::FileUpload,
            identifiedBy: $user->id.$server->uuid,
            expiresAt: $expiresAt,
            user: $user,
            claims: ['server_uuid' => $server->uuid],
        );

        return [
            'url' => sprintf('%s/upload/file?token=%s&directory=%s', $server->node->getConnectionAddress(), $token->toString(), rawurlencode($directory)),
            'expires_at' => $expiresAt->toAtomString(),
        ];
    }

    private static function isMissing(DaemonConnectionException $exception): bool
    {
        return in_array($exception->getStatusCode(), [404, 500], true);
    }

    private static function size(int $bytes): string
    {
        return $bytes >= 1024 * 1024 ? round($bytes / 1024 / 1024, 1).' MiB' : round($bytes / 1024, 1).' KiB';
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{path: string, name: string, directory: bool, size_bytes: int, modified: string, mode: ?string, mime: string}
     */
    private function describe(array $entry, string $directory): array
    {
        $name = (string) ($entry['name'] ?? '');

        return [
            'path' => mb_rtrim($directory, '/').'/'.$name,
            'name' => $name,
            'directory' => self::isDirectory($entry),
            'size_bytes' => (int) ($entry['size'] ?? 0),
            'modified' => Date::parse($entry['modified'] ?? '')->toAtomString(),
            'mode' => $entry['mode_bits'] ?? null,
            'mime' => $entry['mime'] ?? 'application/octet-stream',
        ];
    }

    /** Wings 1.13 answers HTTP 500 instead of 404 for a missing path, so a failure is checked against the parent. */
    private function explainMissing(Server $server, string $path, DaemonConnectionException $exception, bool $expectDirectory): never
    {
        if (self::isMissing($exception)) {
            $entry = $this->entry($server, $path);

            if ($entry === null) {
                throw new ToolError($expectDirectory
                    ? "No directory {$path} exists on {$server->name}. Use list_files on a parent directory to see what is there."
                    : "No file {$path} exists on {$server->name}. Use list_files to find the right path.");
            }

            if (self::isDirectory($entry) !== $expectDirectory) {
                throw new ToolError($expectDirectory
                    ? "{$path} is a file, not a directory. Use read_file to read it."
                    : "{$path} is a directory, not a file. Use list_files to see its contents.");
            }
        }

        throw $exception;
    }
}
