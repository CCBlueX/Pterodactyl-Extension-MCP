<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools;

use Pterodactyl\Models\User;

/**
 * Tools live in a sub-namespace per group (Tools\Files\ReadFile); the group is shown on the account screen.
 */
interface Tool
{
    public function name(): string;

    public function title(): string;

    public function description(): string;

    /** @return array<string, mixed> The JSON schema of the arguments. */
    public function schema(): array;

    public function kind(): Kind;

    /** @return array<array-key, mixed> Data returned to the model as JSON. */
    public function call(User $user, Arguments $arguments): array;
}
