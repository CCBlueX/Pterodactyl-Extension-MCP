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

final class SuspendServer extends AdminTool
{
    public function __construct(private readonly Admin $admin) {}

    public function name(): string
    {
        return 'admin_suspend_server';
    }

    public function title(): string
    {
        return 'Suspend server';
    }

    public function description(): string
    {
        return 'Suspend or unsuspend any server. Suspending stops it and locks its users out until it is unsuspended; a server being transferred cannot be changed.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'suspended' => Schema::boolean('true to suspend, false to unsuspend.'),
        ], ['server', 'suspended']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        if (! $arguments->has('suspended')) {
            throw new ToolError('The argument "suspended" is required.');
        }

        $server = $this->server($arguments);
        $suspended = $arguments->bool('suspended');
        $changed = $server->isSuspended() !== $suspended;

        $this->admin->suspend($server, $suspended);

        return ['identifier' => $server->uuidShort, 'name' => $server->name, 'suspended' => $server->refresh()->isSuspended(), 'changed' => $changed];
    }
}
