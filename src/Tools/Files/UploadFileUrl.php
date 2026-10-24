<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Files;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Files;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class UploadFileUrl extends ServerTool
{
    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'upload_file_url';
    }

    public function title(): string
    {
        return 'Upload file URL';
    }

    public function description(): string
    {
        return 'Get a signed link to upload files straight to the node into a server directory, for files too large for write_file. POST them as multipart/form-data in the field "files", for example with curl -F files=@world.zip; the link expires after 15 minutes.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'directory' => Schema::string('The directory from the server root to upload into.', ['maxLength' => 2048, 'default' => '/']),
        ], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $directory = Files::normalize($arguments->string('directory', '/', 2048));
        $server = $this->server($user, $arguments, ['file.create', 'file.update']);

        return [...$this->files->uploadUrl($user, $server, $directory), 'method' => 'POST multipart/form-data, field files'];
    }
}
