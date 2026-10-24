<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Admin;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Admin;
use PterodactylMcp\Tools\AdminTool;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;

final class ListNodes extends AdminTool
{
    public function __construct(private readonly Admin $admin) {}

    public function name(): string
    {
        return 'admin_list_nodes';
    }

    public function title(): string
    {
        return 'List nodes';
    }

    public function description(): string
    {
        return 'List every node with its location, memory and disk (allocated to servers against the total and overallocation) and free allocations. New servers fit while allocated plus their size stays within total × (1 + overallocate_percent / 100).';
    }

    public function schema(): array
    {
        return Schema::object();
    }

    public function call(User $user, Arguments $arguments): array
    {
        return ['nodes' => $this->admin->nodes()];
    }
}
