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

final class RenameFiles extends ServerTool
{
    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'rename_files';
    }

    public function title(): string
    {
        return 'Rename files';
    }

    public function description(): string
    {
        return 'Rename or move files and folders. from and to are relative to root; a "to" with folders in it, such as "../old/server.jar", moves the file.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'root' => Schema::string('The directory the names are relative to.', ['maxLength' => 2048, 'default' => '/']),
            'files' => Schema::list(Schema::object([
                'from' => Schema::string('The current name.', ['maxLength' => 2048]),
                'to' => Schema::string('The new name.', ['maxLength' => 2048]),
            ], ['from', 'to']), 'The files to rename.', 100),
        ], ['server', 'files']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $root = $arguments->string('root', '/', 2048);
        $files = array_map(static function (mixed $item): array {
            $file = Arguments::of($item, 'files');

            return ['from' => $file->string('from', null, 2048), 'to' => $file->string('to', null, 2048)];
        }, $arguments->list('files', 100));

        if ($files === []) {
            throw new ToolError('Give at least one file in "files".');
        }

        $server = $this->server($user, $arguments, ['file.update']);
        $this->files->rename($server, $root, $files);

        return ['root' => Files::normalize($root), 'renamed' => count($files)];
    }
}
