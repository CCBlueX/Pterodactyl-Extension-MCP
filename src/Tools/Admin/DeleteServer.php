<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Admin;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Admin;
use PterodactylMcp\Tools\AdminTool;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ToolError;

final class DeleteServer extends AdminTool
{
    public function __construct(private readonly Admin $admin) {}

    public function name(): string
    {
        return 'admin_delete_server';
    }

    public function title(): string
    {
        return 'Delete server';
    }

    public function description(): string
    {
        return 'Delete any server with its files and databases; this cannot be undone. confirm must be the server\'s exact name. force also deletes it from the panel when Wings or a database host cannot clean up.';
    }

    public function kind(): Kind
    {
        return Kind::Destroy;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'confirm' => Schema::string('The exact name of the server, to confirm it is the right one.'),
            'force' => Schema::boolean('Delete it from the panel even when Wings or a database host fails to clean up.', false),
        ], ['server', 'confirm']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($arguments);
        $confirm = $arguments->string('confirm', null, 191);

        if ($confirm !== $server->name) {
            throw new ToolError("The server {$server->uuidShort} is called \"{$server->name}\", not \"{$confirm}\". Check that it is the server you mean, then pass its exact name as confirm.");
        }

        $this->admin->delete($server, $arguments->bool('force'));

        return ['deleted' => $server->uuidShort, 'name' => $server->name];
    }
}
