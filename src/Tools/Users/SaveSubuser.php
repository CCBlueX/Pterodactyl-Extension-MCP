<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Users;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Subusers;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class SaveSubuser extends ServerTool
{
    public function __construct(private readonly Subusers $subusers) {}

    public function name(): string
    {
        return 'save_subuser';
    }

    public function title(): string
    {
        return 'Save subuser';
    }

    public function description(): string
    {
        return 'Give the user with this email address access to a server, or replace their permissions when they already are a subuser. Unknown emails get a new account and an invite; websocket.connect is always granted, and you can only grant permissions you hold yourself.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'email' => Schema::string('The email address of the user.', ['maxLength' => 191]),
            'permissions' => Schema::list(Schema::string('A permission key.'), 'Every permission key the subuser should have, such as "control.console" or "file.read".'),
        ], ['server', 'email', 'permissions']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $email = mb_trim($arguments->string('email', null, 191));
        $permissions = array_values(array_unique($arguments->strings('permissions', 500)));
        if ($permissions === []) {
            throw new ToolError('Give at least one permission key in "permissions", such as "control.console".');
        }

        $server = $this->server($user, $arguments);
        $subuser = $this->subusers->find($server, $email);
        $permission = $subuser === null ? 'user.create' : 'user.update';

        if (! $user->can($permission, $server)) {
            throw new ToolError("You do not have the {$permission} permission on {$server->name}.");
        }

        $saved = $subuser === null
            ? $this->subusers->create($user, $server, $email, $permissions)
            : $this->subusers->update($user, $server, $subuser, $permissions);

        return ['created' => $subuser === null] + $this->subusers->describe($saved->loadMissing('user'));
    }
}
