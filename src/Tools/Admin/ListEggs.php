<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Admin;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Admin;
use PterodactylMcp\Tools\AdminTool;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;

final class ListEggs extends AdminTool
{
    public function __construct(private readonly Admin $admin) {}

    public function name(): string
    {
        return 'admin_list_eggs';
    }

    public function title(): string
    {
        return 'List eggs';
    }

    public function description(): string
    {
        return 'List the eggs (game templates) servers can be created from, with their Docker images (label to image), startup command and variables.';
    }

    public function schema(): array
    {
        return Schema::object([
            'search' => Schema::string('Only eggs whose name or description contains this text.', ['maxLength' => 100]),
        ]);
    }

    public function call(User $user, Arguments $arguments): array
    {
        return ['eggs' => $this->admin->eggs($arguments->optionalString('search', 100))];
    }
}
