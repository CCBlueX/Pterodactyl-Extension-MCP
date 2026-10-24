<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Databases;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Databases;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class DeleteDatabase extends ServerTool
{
    public function __construct(private readonly Databases $databases) {}

    public function name(): string
    {
        return 'delete_database';
    }

    public function title(): string
    {
        return 'Delete database';
    }

    public function description(): string
    {
        return 'Delete a server database and its user, with all data in it. This cannot be undone.';
    }

    public function kind(): Kind
    {
        return Kind::Destroy;
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
        $server = $this->server($user, $arguments, ['database.delete']);
        $database = $this->databases->find($server, $arguments->string('database', null, 64));
        $name = $database->database;
        $this->databases->delete($database);

        return ['deleted' => $name];
    }
}
