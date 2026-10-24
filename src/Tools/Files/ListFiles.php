<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Files;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Files;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class ListFiles extends ServerTool
{
    private const int MAX_ENTRIES = 1000;

    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'list_files';
    }

    public function title(): string
    {
        return 'List files';
    }

    public function description(): string
    {
        return 'List the files and folders in a server directory, optionally recursing up to 3 levels into subfolders. Stops after '.self::MAX_ENTRIES.' entries with truncated: true.';
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'directory' => Schema::string('The directory from the server root, such as "/" or "/plugins".', ['maxLength' => 2048, 'default' => '/']),
            'depth' => Schema::integer('How many levels of subfolders to list as well.', 0, 3, 0),
        ], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['file.read']);
        $directory = Files::normalize($arguments->string('directory', '/', 2048));
        $entries = [];
        $complete = $this->walk($server, $directory, $arguments->int('depth', 0, 0, 3), $entries);

        return ['directory' => $directory, 'entries' => $entries, 'truncated' => ! $complete];
    }

    /**
     * Add the entries of a directory, each folder followed by its own contents, until the limit is reached.
     *
     * @param  list<array<string, mixed>>  $entries
     */
    private function walk(Server $server, string $directory, int $depth, array &$entries): bool
    {
        foreach ($this->files->list($server, $directory) as $entry) {
            if (count($entries) >= self::MAX_ENTRIES) {
                return false;
            }

            $entries[] = $entry;

            if ($entry['directory'] && $depth > 0 && ! $this->walk($server, $entry['path'], $depth - 1, $entries)) {
                return false;
            }
        }

        return true;
    }
}
