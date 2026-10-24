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

final class CompressFiles extends ServerTool
{
    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'compress_files';
    }

    public function title(): string
    {
        return 'Compress files';
    }

    public function description(): string
    {
        return 'Pack files and folders relative to root into a new .tar.gz archive in root; the node picks the name. Large archives can take up to 15 minutes.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'root' => Schema::string('The directory the names are relative to and the archive is created in.', ['maxLength' => 2048, 'default' => '/']),
            'files' => Schema::list(Schema::string('A file or folder name relative to root.', ['maxLength' => 2048]), 'The files and folders to pack.', 100),
        ], ['server', 'files']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $root = $arguments->string('root', '/', 2048);
        $files = $arguments->strings('files', 100);

        if ($files === []) {
            throw new ToolError('Give at least one file in "files".');
        }

        foreach ($files as $file) {
            if (mb_strlen($file) > 2048) {
                throw new ToolError('A file name can be at most 2048 characters long.');
            }
        }

        $server = $this->server($user, $arguments, ['file.archive']);
        set_time_limit(960);

        return ['archive' => $this->files->compress($server, $root, $files)];
    }
}
