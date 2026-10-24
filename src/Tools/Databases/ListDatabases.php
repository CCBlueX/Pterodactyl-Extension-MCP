<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Databases;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Databases;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class ListDatabases extends ServerTool
{
    public function __construct(private readonly Databases $databases) {}

    public function name(): string
    {
        return 'list_databases';
    }

    public function title(): string
    {
        return 'List databases';
    }

    public function description(): string
    {
        return 'List the MySQL databases of a server with their connection details. Passwords are only included when asked for and you have the database.view_password permission.';
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'include_passwords' => Schema::boolean('Include each database password.', false),
        ], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['database.read']);
        $passwords = $arguments->bool('include_passwords') && $user->can('database.view_password', $server);

        return [
            'limit' => $server->database_limit,
            'databases' => array_map(fn ($database): array => $this->databases->describe($database, $passwords), $this->databases->all($server)),
        ];
    }
}
