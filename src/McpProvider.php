<?php

declare(strict_types=1);

namespace PterodactylMcp;

use Pterodactyl\Extensions\ExtensionProvider;
use Pterodactyl\Services\Extensions\ExtensionSettingDefinition;
use Pterodactyl\Services\Extensions\ExtensionSettingsDefinition;

class McpProvider extends ExtensionProvider
{
    public function boot(): void
    {
        $this->registerApiRoutes();

        $this->registerSettings(new ExtensionSettingsDefinition($this->settings(), [
            ExtensionSettingDefinition::make('enabled', 'enabled', true, ['boolean'])
                ->label('Enabled')
                ->help('Turn the MCP endpoint off without uninstalling the extension.')
                ->field('toggle'),
            ExtensionSettingDefinition::make('allow_write_tools', 'allow_write_tools', false, ['boolean'])
                ->label('Allow changes')
                ->help('Adds the tools that change files, backups, startup, network, databases, schedules and subusers. Console and power tools are always available to users with those permissions.')
                ->field('toggle'),
            ExtensionSettingDefinition::make('allow_admin_tools', 'allow_admin_tools', false, ['boolean'])
                ->label('Allow administration')
                ->help('Root administrators get tools to list nodes and eggs, and with changes allowed, to create, resize, suspend and delete servers.')
                ->field('toggle'),
            ExtensionSettingDefinition::make('max_read_kb', 'max_read_kb', 256, ['required', 'integer', 'min:1', 'max:4096'])
                ->label('Largest text answer (KB)')
                ->help('The most text read_file returns in one answer. Larger files can still be paged, tailed and searched.')
                ->field('number'),
            ExtensionSettingDefinition::make('max_transfer_mb', 'max_transfer_mb', 16, ['required', 'integer', 'min:1', 'max:64'])
                ->label('Largest file transfer (MB)')
                ->help('The largest file read_file searches or returns as base64, and the largest base64 file write_file accepts.')
                ->field('number'),
        ]));
    }
}
