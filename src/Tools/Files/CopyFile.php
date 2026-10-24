<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Files;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Files;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class CopyFile extends ServerTool
{
    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'copy_file';
    }

    public function title(): string
    {
        return 'Copy file';
    }

    public function description(): string
    {
        return 'Copy a file next to itself; the node picks the name, such as "config copy.yml". Use list_files to see it and rename_files to rename it.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'path' => Schema::string('The file to copy, from the server root.', ['maxLength' => 2048]),
        ], ['server', 'path']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $path = Files::normalize($arguments->string('path', null, 2048));
        $server = $this->server($user, $arguments, ['file.create']);
        $this->files->copy($server, $path);

        return ['copied' => $path];
    }
}
