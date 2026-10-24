<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Databases;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Databases;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class RotateDatabasePassword extends ServerTool
{
    public function __construct(private readonly Databases $databases) {}

    public function name(): string
    {
        return 'rotate_database_password';
    }

    public function title(): string
    {
        return 'Rotate database password';
    }

    public function description(): string
    {
        return 'Give a server database a new random password; the old one stops working at once. The new password is returned when you have the database.view_password permission.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'database' => Schema::string('The database id from list_databases.'),
        ], ['server', 'database']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['database.update']);
        $database = $this->databases->rotatePassword($this->databases->find($server, $arguments->string('database', null, 64)));

        return $this->databases->describe($database, $user->can('database.view_password', $server));
    }
}
