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

final class UpdateAllocation extends ServerTool
{
    public function __construct(private readonly Network $network) {}

    public function name(): string
    {
        return 'update_allocation';
    }

    public function title(): string
    {
        return 'Update allocation';
    }

    public function description(): string
    {
        return 'Change the notes of an allocation and/or make it the primary one. The primary allocation is the address players join; the change takes effect on the next start.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'allocation' => Schema::integer('The allocation id from get_server.'),
            'notes' => Schema::string('The new notes. An empty string clears them.', ['maxLength' => 256]),
            'primary' => Schema::boolean('true to make this the primary allocation.'),
        ], ['server', 'allocation']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $notes = $arguments->optionalString('notes', 256);
        $primary = $arguments->has('primary') && $arguments->bool('primary');

        if ($arguments->has('primary') && ! $primary) {
            throw new ToolError('primary can only be true. To move the primary allocation, set primary: true on the other allocation.');
        }

        if ($notes === null && ! $primary) {
            throw new ToolError('Give notes, primary: true, or both.');
        }

        $server = $this->server($user, $arguments, ['allocation.update']);
        $allocation = $this->network->find($server, $arguments->optionalInt('allocation') ?? throw new ToolError('The argument "allocation" is required.'));

        if ($notes !== null) {
            $allocation = $this->network->setNotes($allocation, $notes);
        }

        if ($primary) {
            $allocation = $this->network->setPrimary($server, $allocation);
        }

        return $this->network->view($server, $allocation);
    }
}
