<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Files;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Files;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class CreateFolder extends ServerTool
{
    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'create_folder';
    }

    public function title(): string
    {
        return 'Create folder';
    }

    public function description(): string
    {
        return 'Create a folder on a server, such as "/plugins/MyPlugin".';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'path' => Schema::string('The new folder from the server root.', ['maxLength' => 2048]),
        ], ['server', 'path']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $path = Files::normalize($arguments->string('path', null, 2048));
        $name = basename($path);
        $root = dirname($path);

        if ($path === '/') {
            throw new ToolError('Give the path of the folder to create, such as "/backups".');
        }

        if (mb_strlen($name) > 255) {
            throw new ToolError('A folder name can be at most 255 characters long.');
        }

        $server = $this->server($user, $arguments, ['file.create']);
        $this->files->createFolder($server, $name, $root);

        return ['path' => $path];
    }
}
