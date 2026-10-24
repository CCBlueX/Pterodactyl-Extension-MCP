<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Network;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Network;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;

final class AddAllocation extends ServerTool
{
    public function __construct(private readonly Network $network) {}

    public function name(): string
    {
        return 'add_allocation';
    }

    public function title(): string
    {
        return 'Add allocation';
    }

    public function description(): string
    {
        return 'Give the server another free port on its node, up to its allocation limit (at most 2 per minute). Notes need the allocation.update permission.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'notes' => Schema::string('A note for the new allocation, such as what it is used for.', ['maxLength' => 256]),
        ], ['server']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $notes = $arguments->optionalString('notes', 256);
        $server = $this->server($user, $arguments, $notes === null ? ['allocation.create'] : ['allocation.create', 'allocation.update']);

        $allocation = $this->network->assign($server);

        if ($notes !== null) {
            $allocation = $this->network->setNotes($allocation, $notes);
        }

        return $this->network->view($server, $allocation);
    }
}
