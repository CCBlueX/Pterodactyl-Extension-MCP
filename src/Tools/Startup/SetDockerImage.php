<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Startup;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Startup;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class SetDockerImage extends ServerTool
{
    public function __construct(private readonly Startup $startup) {}

    public function name(): string
    {
        return 'set_docker_image';
    }

    public function title(): string
    {
        return 'Set Docker image';
    }

    public function description(): string
    {
        return 'Switch the server to another Docker image its egg offers (see docker_images in get_startup). Takes effect on the next start; refused when an administrator set a custom image.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'image' => Schema::string('The image, such as "ghcr.io/pterodactyl/yolks:java_21".', ['maxLength' => 191]),
        ], ['server', 'image']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['startup.docker-image']);
        $image = mb_trim($arguments->string('image', '', 191));

        $this->startup->setDockerImage($server, $image);

        return ['docker_image' => $image];
    }
}
