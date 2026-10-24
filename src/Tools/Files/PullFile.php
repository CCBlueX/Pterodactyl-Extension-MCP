<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Files;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Files;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class PullFile extends ServerTool
{
    public function __construct(private readonly Files $files) {}

    public function name(): string
    {
        return 'pull_file';
    }

    public function title(): string
    {
        return 'Pull file from URL';
    }

    public function description(): string
    {
        return 'Have the node download a file from a public URL into a server directory, at most 5 pulls per 10 minutes per server. The node refuses private and loopback hosts and does not follow redirects, so give the final download URL.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'url' => Schema::string('The http(s) URL to download.', ['maxLength' => 2048]),
            'directory' => Schema::string('The directory from the server root to save into; the server root by default.', ['maxLength' => 2048]),
            'filename' => Schema::string('The name to save as; taken from the URL by default.', ['maxLength' => 255]),
            'use_header' => Schema::boolean('Take the name from the Content-Disposition header of the response.'),
            'foreground' => Schema::boolean('Wait until the download finished instead of returning right away.'),
        ], ['server', 'url']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $url = $arguments->string('url', null, 2048);
        $directory = $arguments->optionalString('directory', 2048);
        $options = [];

        if ($arguments->has('filename')) {
            $options['filename'] = $arguments->string('filename', null, 255);
        }

        foreach (['use_header', 'foreground'] as $flag) {
            if ($arguments->has($flag)) {
                $options[$flag] = $arguments->bool($flag);
            }
        }

        $server = $this->server($user, $arguments, ['file.create', 'file.update']);
        $this->files->pull($server, $url, $directory, $options);

        return ['url' => $url, 'directory' => Files::normalize($directory ?? '/'), 'finished' => $options['foreground'] ?? false];
    }
}
