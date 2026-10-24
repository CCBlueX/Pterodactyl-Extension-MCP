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

final class DeleteFiles extends ServerTool
{
    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'delete_files';
    }

    public function title(): string
    {
        return 'Delete files';
    }

    public function description(): string
    {
        return 'Permanently delete files and folders (with everything inside) relative to root. This cannot be undone; make a backup first when unsure.';
    }

    public function kind(): Kind
    {
        return Kind::Destroy;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'root' => Schema::string('The directory the names are relative to.', ['maxLength' => 2048, 'default' => '/']),
            'files' => Schema::list(Schema::string('A file or folder name relative to root.', ['maxLength' => 2048]), 'The files and folders to delete.', 100),
        ], ['server', 'files']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $root = $arguments->string('root', '/', 2048);
        $files = $this->names($arguments);
        $server = $this->server($user, $arguments, ['file.delete']);
        $this->files->delete($server, $root, $files);

        return ['root' => Files::normalize($root), 'deleted' => $files];
    }

    /** @return list<string> */
    private function names(Arguments $arguments): array
    {
        $files = $arguments->strings('files', 100);

        if ($files === []) {
            throw new ToolError('Give at least one file in "files".');
        }

        foreach ($files as $file) {
            if (mb_strlen($file) > 2048) {
                throw new ToolError('A file name can be at most 2048 characters long.');
            }
        }

        return $files;
    }
}
