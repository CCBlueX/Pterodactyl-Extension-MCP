<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Files;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Files;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class DownloadFileUrl extends ServerTool
{
    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'download_file_url';
    }

    public function title(): string
    {
        return 'Download file URL';
    }

    public function description(): string
    {
        return 'Get a signed link that downloads a server file straight from the node, for files too large or binary for read_file. The link expires after 15 minutes.';
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'path' => Schema::string('The file from the server root, such as "/world.zip".', ['maxLength' => 2048]),
        ], ['server', 'path']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $path = Files::normalize($arguments->string('path', null, 2048));
        $server = $this->server($user, $arguments, ['file.read-content']);
        $entry = $this->files->entry($server, $path);

        if ($entry === null) {
            throw new ToolError("No file {$path} exists on {$server->name}. Use list_files to find the right path.");
        }

        if (Files::isDirectory($entry)) {
            throw new ToolError("{$path} is a directory. Use compress_files to pack it into an archive, then download that.");
        }

        return $this->files->downloadUrl($user, $server, $path);
    }
}
