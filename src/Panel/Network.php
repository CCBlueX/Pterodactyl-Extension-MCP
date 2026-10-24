<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Illuminate\Support\Facades\Validator;
use Pterodactyl\Actions\Allocations\CreateAllocations;
use Pterodactyl\Contracts\Allocations\AssignsAllocations;
use Pterodactyl\Contracts\Allocations\SetsPrimaryAllocations;
use Pterodactyl\Contracts\Allocations\UnassignsAllocations;
use Pterodactyl\Contracts\Allocations\UpdatesAllocationNotes;
use Pterodactyl\Enum\ResourceLimit;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Server;
use PterodactylMcp\Tools\ToolError;

/**
 * Panel contracts for allocations, used like the Client NetworkAllocationController. The contracts log their own
 * activity.
 */
final readonly class Network
{
    public function __construct(
        private AssignsAllocations $assign,
        private UpdatesAllocationNotes $notes,
        private SetsPrimaryAllocations $primary,
        private UnassignsAllocations $unassign,
    ) {}

    public function find(Server $server, int $id): Allocation
    {
        return $server->allocations()->whereKey($id)->first()
            ?? throw new ToolError("{$server->name} has no allocation {$id}. Use get_server to see its allocations.");
    }

    public function assign(Server $server): Allocation
    {
        Throttle::hit($server, ResourceLimit::Allocation);

        return $this->assign->assign($server);
    }

    /** Blank notes are cleared. */
    public function setNotes(Allocation $allocation, ?string $notes): Allocation
    {
        $notes = $notes === null || mb_trim($notes) === '' ? null : mb_trim($notes);
        Validator::validate(['notes' => $notes], ['notes' => [...CreateAllocations::NOTES, 'present']]);

        return $this->notes->update($allocation, $notes);
    }

    public function setPrimary(Server $server, Allocation $allocation): Allocation
    {
        return $this->primary->setPrimary($server, $allocation);
    }

    public function unassign(Server $server, Allocation $allocation): void
    {
        $this->unassign->unassign($server, $allocation);
    }

    /** @return array<string, mixed> */
    public function view(Server $server, Allocation $allocation): array
    {
        return [
            'id' => $allocation->id,
            'address' => $allocation->ip_alias ?? $allocation->ip,
            'port' => $allocation->port,
            'notes' => $allocation->notes,
            'primary' => $server->allocation_id === $allocation->id,
        ];
    }
}
