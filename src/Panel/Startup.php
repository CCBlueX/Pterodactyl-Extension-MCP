<?php

declare(strict_types=1);

namespace PterodactylMcp\Panel;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Pterodactyl\Actions\Eggs\CreateEgg;
use Pterodactyl\Contracts\Servers\ReinstallsServers;
use Pterodactyl\Contracts\Servers\UpdatesServerDetails;
use Pterodactyl\Contracts\Servers\UpdatesServerDockerImage;
use Pterodactyl\Contracts\Servers\UpdatesServerVariable;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;
use PterodactylMcp\Tools\ToolError;

/**
 * Panel contracts for startup and settings, used like the Client StartupController and SettingsController.
 * The contracts log their own activity.
 */
final readonly class Startup
{
    public function __construct(
        private UpdatesServerVariable $variables,
        private UpdatesServerDockerImage $dockerImage,
        private UpdatesServerDetails $details,
        private ReinstallsServers $reinstall,
    ) {}

    /** @return array<string, mixed> */
    public function view(Server $server): array
    {
        return [
            'startup_command' => $server->startup,
            'invocation' => $server->startupCommand(),
            'docker_image' => $server->image,
            'docker_images' => $this->images($server),
            'variables' => $server->variables()->where('user_viewable', true)->get()->map($this->variable(...))->values()->all(),
        ];
    }

    /**
     * Only variables the user can see and edit, with the value held to the variable's rules.
     *
     * @return array<string, mixed>
     */
    public function setVariable(Server $server, string $key, string $value): array
    {
        $variable = $server->variables()->where('env_variable', $key)->first();

        if (! $variable instanceof EggVariable || ! $variable->user_viewable) {
            throw new ToolError("{$server->name} has no startup variable \"{$key}\". Use get_startup to see its variables.");
        }

        if (! $variable->user_editable) {
            throw new ToolError("The startup variable {$key} is read-only.");
        }

        $value = mb_trim($value);
        $rules = $variable->rules ?? '';
        Validator::validate(['value' => $value === '' ? null : $value], ['value' => ['present', ...(Str::startsWith($rules, 'regex:') ? [$rules] : explode('|', $rules))]]);

        $this->variables->update($server, $variable->id, $value);
        $variable->server_value = $value === '' ? null : $value;

        return ['variable' => $this->variable($variable), 'invocation' => $server->startupCommand()];
    }

    public function setDockerImage(Server $server, string $image): void
    {
        $images = $this->images($server);

        if (! in_array($image, $images, true)) {
            throw new ToolError(sprintf('%s cannot use the image "%s". Pick one of: %s.', $server->name, $image, implode(', ', array_values($images))));
        }

        Validator::validate(['docker_image' => $image], ['docker_image' => CreateEgg::DOCKER_IMAGE]);

        if (! in_array($server->image, $images, true)) {
            throw new ToolError("The Docker image of {$server->name} has been set by an administrator and cannot be changed.");
        }

        $this->dockerImage->update($server, $image);
    }

    /** The description is kept unless one is given; an empty one clears it. */
    public function rename(Server $server, string $name, ?string $description): Server
    {
        $input = ['name' => mb_trim($name) === '' ? null : mb_trim($name)];

        if ($description !== null) {
            $input['description'] = mb_trim($description) === '' ? null : mb_trim($description);
        }

        return $this->details->update($server, [
            'external_id' => $server->external_id,
            'owner_id' => $server->owner_id,
            'description' => $server->description,
            ...$input,
        ]);
    }

    public function reinstall(Server $server): void
    {
        $this->reinstall->reinstall($server);
    }

    /** @return array<string, string> The egg's images, keyed by label. */
    private function images(Server $server): array
    {
        $egg = $server->egg ?? throw new ToolError("{$server->name} has no egg.");

        return $egg->docker_images;
    }

    /** @return array<string, mixed> */
    private function variable(EggVariable $variable): array
    {
        return [
            'name' => $variable->name,
            'env' => $variable->env_variable,
            'description' => $variable->description,
            'value' => $variable->server_value ?? null,
            'default' => $variable->default_value,
            'editable' => $variable->user_editable,
            'rules' => $variable->rules,
        ];
    }
}
