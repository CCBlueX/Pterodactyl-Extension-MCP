<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Pterodactyl\Contracts\Subusers\CreatesSubusers;
use Pterodactyl\Contracts\Subusers\DeletesSubusers;
use Pterodactyl\Contracts\Subusers\UpdatesSubusers;
use Pterodactyl\Enum\ResourceLimit;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Servers\SubuserPermissionCatalog;
use PterodactylMcp\Tools\ToolError;

/**
 * Panel contracts for subusers, with the checks of the Client API SubuserRequest: nobody changes their own
 * access, and only the owner or a root admin may grant or take away permissions they do not hold themselves.
 * The contracts log their own activity.
 */
final readonly class Subusers
{
    private const string WEBSOCKET = 'websocket.connect';

    public function __construct(
        private SubuserPermissionCatalog $catalog,
        private CreatesSubusers $creates,
        private UpdatesSubusers $updates,
        private DeletesSubusers $deletes,
    ) {}

    /** @return list<Subuser> */
    public function all(Server $server): array
    {
        return $server->subusers()->with('user')->orderBy('id')->get()->all();
    }

    public function find(Server $server, string $email): ?Subuser
    {
        return $server->subusers()->with('user')
            ->whereHas('user', static fn ($query) => $query->where('email', mb_trim($email)))
            ->first();
    }

    /** @param list<string> $permissions */
    public function create(User $caller, Server $server, string $email, array $permissions): Subuser
    {
        Throttle::hit($server, ResourceLimit::Subuser);
        $this->checkKnown($permissions);
        self::checkGrantable($caller, $server, $permissions, []);

        return $this->creates->create($server, $email, self::withWebsocket($permissions));
    }

    /** @param list<string> $permissions */
    public function update(User $caller, Server $server, Subuser $subuser, array $permissions): Subuser
    {
        self::checkNotSelf($caller, $subuser);
        $this->checkKnown($permissions);
        self::checkGrantable($caller, $server, $permissions, $subuser->permissions);

        $requested = self::withWebsocket($permissions);
        if (! self::hasFullAccess($caller, $server)) {
            $held = self::held($caller, $server);
            $requested = self::withWebsocket([...array_intersect($requested, $held), ...array_diff($subuser->permissions, $held)]);
        }

        return $this->updates->update($server, $subuser, $requested);
    }

    public function delete(User $caller, Server $server, Subuser $subuser): void
    {
        self::checkNotSelf($caller, $subuser);

        if (! self::hasFullAccess($caller, $server) && array_diff($subuser->permissions, self::held($caller, $server), [self::WEBSOCKET]) !== []) {
            throw new ToolError('You cannot remove a subuser that has permissions your account does not have.');
        }

        $this->deletes->delete($server, $subuser);
    }

    /** @return array<string, mixed> */
    public function describe(Subuser $subuser): array
    {
        return [
            'email' => $subuser->user->email,
            'username' => $subuser->user->username,
            'permissions' => array_values($subuser->permissions),
            'two_factor' => (bool) $subuser->user->use_totp,
        ];
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $current  permissions the subuser already holds, which may be kept
     */
    private static function checkGrantable(User $caller, Server $server, array $permissions, array $current): void
    {
        if (self::hasFullAccess($caller, $server)) {
            return;
        }

        $missing = array_values(array_diff($permissions, self::held($caller, $server), $current));

        if ($missing !== []) {
            throw new ToolError('You cannot grant permissions your account does not have: '.implode(', ', $missing).'.');
        }
    }

    private static function checkNotSelf(User $caller, Subuser $subuser): void
    {
        if ($subuser->user_id === $caller->id) {
            throw new ToolError('You cannot change or remove your own subuser access.');
        }
    }

    private static function hasFullAccess(User $caller, Server $server): bool
    {
        return $caller->root_admin || $caller->id === $server->owner_id;
    }

    /** @return list<string> */
    private static function held(User $caller, Server $server): array
    {
        return array_values($caller->permissionsOn($server));
    }

    /**
     * @param  array<array-key, string>  $permissions
     * @return list<string>
     */
    private static function withWebsocket(array $permissions): array
    {
        return array_values(array_unique([...array_values($permissions), self::WEBSOCKET]));
    }

    /** @param list<string> $permissions */
    private function checkKnown(array $permissions): void
    {
        $known = $this->catalog->keys();
        $unknown = array_values(array_diff($permissions, $known));

        if ($unknown !== []) {
            throw new ToolError('Unknown permission '.implode(', ', $unknown).'. Valid permissions: '.implode(', ', $known).'.');
        }
    }
}
