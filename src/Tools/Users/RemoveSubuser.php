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

final class RemoveSubuser extends ServerTool
{
    public function __construct(private readonly Subusers $subusers) {}

    public function name(): string
    {
        return 'remove_subuser';
    }

    public function title(): string
    {
        return 'Remove subuser';
    }

    public function description(): string
    {
        return 'Take away a subuser\'s access to a server and end their SFTP sessions. Their account stays; you cannot remove yourself or a subuser holding permissions you lack.';
    }

    public function kind(): Kind
    {
        return Kind::Destroy;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'email' => Schema::string('The email address of the subuser, from list_subusers.', ['maxLength' => 191]),
        ], ['server', 'email']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['user.delete']);
        $email = mb_trim($arguments->string('email', null, 191));
        $subuser = $this->subusers->find($server, $email)
            ?? throw new ToolError("{$email} is not a subuser of {$server->name}. Use list_subusers to see its subusers.");
        $this->subusers->delete($user, $server, $subuser);

        return ['removed' => $subuser->user->email];
    }
}
