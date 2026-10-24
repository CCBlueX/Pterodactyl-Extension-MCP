<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Databases;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Databases;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class CreateDatabase extends ServerTool
{
    public function __construct(private readonly Databases $databases) {}

    public function name(): string
    {
        return 'create_database';
    }

    public function title(): string
    {
        return 'Create database';
    }

    public function description(): string
    {
        return 'Create a MySQL database for a server within its database limit. The panel prefixes the name with "s<server id>_"; the password is returned when you have the database.view_password permission.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'name' => Schema::string('The database name: 3-48 letters, numbers, dashes and underscores.', ['minLength' => 3, 'maxLength' => 48]),
            'remote' => Schema::string('The hosts the database user may connect from: "%" for any, or an IP address with % wildcards.', ['default' => '%']),
        ], ['server', 'name']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['database.create']);
        $database = $this->databases->create($server, $arguments->string('name', null, 48), $arguments->string('remote', '%', 191));

        return $this->databases->describe($database, $user->can('database.view_password', $server));
    }
}
