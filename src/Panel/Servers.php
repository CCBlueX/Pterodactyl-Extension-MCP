<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Pterodactyl\Models\ActivityLog;
use Pterodactyl\Models\ActivityLogSubject;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;

/**
 * Server listings and the server activity log, queried the way the Client API does.
 */
final readonly class Servers
{
    /** The server status the Client API reports, with a running transfer as "transferring". */
    public static function status(Server $server): ?string
    {
        return $server->status ?? ($server->transfer !== null ? 'transferring' : null);
    }

    /** @return Collection<int, Server> The servers the user owns or is a subuser of; every server for root admins asking for all. */
    public function accessible(User $user, bool $all, ?string $search): Collection
    {
        $query = $all && $user->root_admin ? Server::query()->select('servers.*') : $user->accessibleServers();

        if ($search !== null && $search !== '') {
            $like = '%'.self::escapeLike(mb_strtolower($search)).'%';
            $query->where(static fn (Builder $builder) => $builder
                ->whereRaw('LOWER(servers.name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(servers.uuidShort) LIKE ?', [$like])
                ->orWhereRaw('LOWER(servers.uuid) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(servers.description, \'\')) LIKE ?', [$like]));
        }

        return $query->with(['egg', 'node', 'allocation', 'transfer'])->orderBy('servers.name')->get();
    }

    /**
     * The newest activity entries of a server, mirroring the Client ActivityLogController and its resource: IPs are
     * only shown to root admins, the actor themselves, or the subject of an entry without an actor.
     *
     * @return list<array<string, mixed>>
     */
    public function activity(Server $server, User $user, int $limit, ?string $eventPrefix): array
    {
        $query = $server->visibleActivity()
            ->with('actor')
            ->whereNotIn('activity_logs.event', ActivityLog::DISABLED_EVENTS);

        if ($eventPrefix !== null && $eventPrefix !== '') {
            $query->where('activity_logs.event', 'like', self::escapeLike($eventPrefix).'%');
        }

        return $query->orderByDesc('activity_logs.timestamp')
            ->orderByDesc('activity_logs.id')
            ->limit($limit)
            ->get()
            ->map(static function (ActivityLog $log) use ($user): array {
                $canViewIp = self::canViewIp($log, $user);
                $properties = $log->propertyValues();

                if (! $canViewIp && array_key_exists('ip', $properties)) {
                    $properties['ip'] = '[hidden]';
                }

                return array_filter([
                    'event' => $log->event,
                    'actor' => $log->actor instanceof User ? $log->actor->username : 'system',
                    'api_key' => ($log->api_key_id ?? 0) > 0,
                    'ip' => $canViewIp ? $log->ip : null,
                    'properties' => $properties === [] ? (object) [] : $properties,
                    'timestamp' => $log->timestamp->toAtomString(),
                ], static fn (mixed $value): bool => $value !== null);
            })
            ->values()
            ->all();
    }

    private static function canViewIp(ActivityLog $log, User $user): bool
    {
        if ($user->root_admin) {
            return true;
        }

        if ($log->actor_id !== null) {
            return $log->actor instanceof Model && $log->actor->is($user);
        }

        return $log->subjects->contains(
            static fn (ActivityLogSubject $subject): bool => $subject->subject_type === $user->getMorphClass() && $subject->subject_id === $user->getKey()
        );
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
