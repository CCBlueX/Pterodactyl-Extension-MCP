<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Files;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Files;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class DecompressFile extends ServerTool
{
    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'decompress_file';
    }

    public function title(): string
    {
        return 'Decompress file';
    }

    public function description(): string
    {
        return 'Unpack an archive (zip, tar, tar.gz and similar) into root. Large archives can take a few minutes.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'root' => Schema::string('The directory the archive is in and is unpacked into.', ['maxLength' => 2048, 'default' => '/']),
            'file' => Schema::string('The archive name relative to root.', ['maxLength' => 2048]),
        ], ['server', 'file']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $root = $arguments->string('root', '/', 2048);
        $file = $arguments->string('file', null, 2048);
        $server = $this->server($user, $arguments, ['file.create', 'file.update']);
        $this->files->decompress($server, $root, $file);

        return ['root' => Files::normalize($root), 'decompressed' => $file];
    }
}
