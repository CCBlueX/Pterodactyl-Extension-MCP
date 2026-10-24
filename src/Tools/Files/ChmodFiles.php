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

final class ChmodFiles extends ServerTool
{
    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'chmod_files';
    }

    public function title(): string
    {
        return 'Change file permissions';
    }

    public function description(): string
    {
        return 'Change the POSIX permission bits of files and folders relative to root, given as octal digits such as "644" or "0755".';
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
                'file' => Schema::string('A file or folder name relative to root.', ['maxLength' => 2048]),
                'mode' => Schema::string('The new mode as 3 or 4 octal digits, such as "644".', ['pattern' => '^[0-7]{3,4}$']),
            ], ['file', 'mode']), 'The files and their new modes.', 100),
        ], ['server', 'files']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $root = $arguments->string('root', '/', 2048);
        $files = array_map(static function (mixed $item): array {
            $file = Arguments::of($item, 'files');
            $mode = $file->string('mode');

            if (preg_match('/^[0-7]{3,4}$/', $mode) !== 1) {
                throw new ToolError("\"{$mode}\" is not a file mode. Give 3 or 4 octal digits, such as \"644\".");
            }

            return ['file' => $file->string('file', null, 2048), 'mode' => $mode];
        }, $arguments->list('files', 100));

        if ($files === []) {
            throw new ToolError('Give at least one file in "files".');
        }

        $server = $this->server($user, $arguments, ['file.update']);
        $this->files->chmod($server, $root, $files);

        return ['root' => Files::normalize($root), 'changed' => $files];
    }
}
