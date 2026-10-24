<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools;

enum Kind: string
{
    case Read = 'read';

    /** Console and power: always offered to users with those permissions. */
    case Control = 'control';

    case Write = 'write';

    /** Deletes, overwrites or resets something that cannot be undone. */
    case Destroy = 'destroy';

    /** Writing and destroying tools are only offered when the administrator allows them. */
    public function changes(): bool
    {
        return $this === self::Write || $this === self::Destroy;
    }

    /** @return array<string, bool> */
    public function annotations(): array
    {
        return match ($this) {
            self::Read => ['readOnlyHint' => true, 'openWorldHint' => false],
            self::Destroy => ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false],
            default => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
        };
    }
}
