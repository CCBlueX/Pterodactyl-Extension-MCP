<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Pterodactyl\Actions\Databases\DeployServerDatabase;
use Pterodactyl\Contracts\Databases\DeletesDatabases;
use Pterodactyl\Contracts\Databases\DeploysServerDatabases;
use Pterodactyl\Contracts\Databases\RotatesDatabasePasswords;
use Pterodactyl\Enum\ResourceLimit;
use Pterodactyl\Exceptions\Service\Database\DatabaseClientFeatureNotEnabledException;
use Pterodactyl\Facades\Hashids;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\Server;
use Pterodactyl\Support\DatabaseName;
use PterodactylMcp\Tools\ToolError;

/**
 * Panel contracts for server databases, with the checks of the Client API database routes. The contracts log
 * their own activity.
 */
final readonly class Databases
{
    public function __construct(
        private DeploysServerDatabases $deploy,
        private RotatesDatabasePasswords $passwords,
        private DeletesDatabases $deletes,
    ) {}

    /** @return list<Database> */
    public function all(Server $server): array
    {
        return $server->databases()->with('host')->get()->all();
    }

    /** Find a database of the server by the id the Client API shows (a hashid) or its numeric id. */
    public function find(Server $server, string $id): Database
    {
        $key = ctype_digit($id) ? (int) $id : Hashids::decodeFirst($id);
        $database = $key === null ? null : $server->databases()->with('host')->whereKey($key)->first();

        return $database ?? throw new ToolError("There is no database \"{$id}\" on {$server->name}. Use list_databases to see its databases.");
    }

    public function create(Server $server, string $name, string $remote): Database
    {
        Throttle::hit($server, ResourceLimit::Database);

        $rules = DeployServerDatabase::rules();
        Validator::validate(['database' => $name, 'remote' => $remote], [
            'database' => [
                ...$rules['database'],
                Rule::unique('databases')->where(static function (Builder $query) use ($server, $name): void {
                    $query->where('server_id', $server->id)->where('database', DatabaseName::generateUnique($name, $server->id));
                }),
            ],
            'remote' => $rules['remote'],
        ], ['database.unique' => 'The database name you have selected is already in use by this server.']);

        try {
            return $this->deploy->deploy($server, ['database' => $name, 'remote' => $remote])->load('host');
        } catch (DatabaseClientFeatureNotEnabledException $exception) {
            throw new ToolError($exception->getMessage().' Ask an administrator to enable client databases.');
        }
    }

    public function rotatePassword(Database $database): Database
    {
        $this->passwords->rotate($database);

        return $database->refresh()->load('host');
    }

    public function delete(Database $database): void
    {
        $this->deletes->delete($database);
    }

    /** @return array<string, mixed> The database as the Client API shows it; the password only when asked for. */
    public function describe(Database $database, bool $withPassword): array
    {
        $description = [
            'id' => Hashids::encode($database->id),
            'name' => $database->database,
            'username' => $database->username,
            'host' => "{$database->host->host}:{$database->host->port}",
            'remote' => $database->remote,
            'max_connections' => $database->max_connections,
        ];

        return $withPassword ? $description + ['password' => Crypt::decrypt($database->password)] : $description;
    }
}
