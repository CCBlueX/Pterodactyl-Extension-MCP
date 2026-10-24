<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Network;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Network;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class RemoveAllocation extends ServerTool
{
    public function __construct(private readonly Network $network) {}

    public function name(): string
    {
        return 'remove_allocation';
    }

    public function title(): string
    {
        return 'Remove allocation';
    }

    public function description(): string
    {
        return 'Return an allocation to the node\'s free pool and clear its notes. The primary allocation cannot be removed, nor any allocation of a server without an allocation limit.';
    }

    public function kind(): Kind
    {
        return Kind::Destroy;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'allocation' => Schema::integer('The allocation id from get_server.'),
        ], ['server', 'allocation']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $server = $this->server($user, $arguments, ['allocation.delete']);
        $allocation = $this->network->find($server, $arguments->optionalInt('allocation') ?? throw new ToolError('The argument "allocation" is required.'));
        $address = $this->network->view($server, $allocation);

        $this->network->unassign($server, $allocation);

        return ['removed' => $address['id'], 'address' => $address['address'], 'port' => $address['port']];
    }
}
