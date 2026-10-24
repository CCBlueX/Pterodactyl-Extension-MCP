<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Files;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Files;
use PterodactylMcp\Settings;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class WriteFile extends ServerTool
{
    public function __construct(private readonly Files $files, private readonly Settings $settings) {}

    public function name(): string
    {
        return 'write_file';
    }

    public function title(): string
    {
        return 'Write file';
    }

    public function description(): string
    {
        return 'Create a server file or replace its whole contents; missing parent folders are created. Pass expected_sha256 (from read_file, or "absent" to only create) so a file changed in the meantime is not overwritten; use edit_file for small changes.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'path' => Schema::string('The file from the server root, such as "/plugins/Essentials/config.yml".', ['maxLength' => 2048]),
            'content' => Schema::string('The new contents of the whole file.'),
            'encoding' => Schema::enum(['text', 'base64'], 'How content is given: text, or base64 for binary files.', 'text'),
            'expected_sha256' => Schema::string('The sha256 of the current file as read_file returned it, or "absent" to refuse overwriting an existing file.', ['maxLength' => 64]),
        ], ['server', 'path', 'content']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $path = Files::normalize($arguments->string('path', null, 2048));
        $contents = $this->contents($arguments);
        $expected = $arguments->optionalString('expected_sha256', 64);
        $server = $this->server($user, $arguments);
        $entry = $this->files->entry($server, $path);

        if ($path === '/' || ($entry !== null && Files::isDirectory($entry))) {
            throw new ToolError("{$path} is a directory. Give the path of a file inside it.");
        }

        $exists = $entry !== null;
        $permission = $exists ? 'file.update' : 'file.create';

        if (! $user->can($permission, $server)) {
            throw new ToolError("You do not have the {$permission} permission on {$server->name}.");
        }

        if ($expected !== null) {
            $this->checkExpected($user, $server, $path, $exists, mb_strtolower($expected));
        }

        $this->files->write($server, $path, $contents);

        return ['path' => $path, 'size_bytes' => mb_strlen($contents, '8bit'), 'sha256' => hash('sha256', $contents), 'created' => ! $exists];
    }

    private function contents(Arguments $arguments): string
    {
        $contents = $arguments->string('content');

        return match ($arguments->string('encoding', 'text')) {
            'text' => $contents,
            'base64' => $this->decode($contents),
            default => throw new ToolError('The argument "encoding" must be text or base64.'),
        };
    }

    private function decode(string $contents): string
    {
        $decoded = base64_decode($contents, true);

        if ($decoded === false) {
            throw new ToolError('The content is not valid base64.');
        }

        if (mb_strlen($decoded, '8bit') > $this->settings->maxTransferBytes()) {
            throw new ToolError('The content is larger than the '.intdiv($this->settings->maxTransferBytes(), 1024 * 1024).' MiB this MCP server writes. Use upload_file_url for large files.');
        }

        return $decoded;
    }

    private function checkExpected(User $user, Server $server, string $path, bool $exists, string $expected): void
    {
        if ($expected === 'absent') {
            if ($exists) {
                throw new ToolError("{$path} already exists. Read it with read_file and pass its sha256 as expected_sha256 to replace it.");
            }

            return;
        }

        if (! $exists) {
            throw new ToolError("{$path} does not exist. Pass expected_sha256 \"absent\" to create it.");
        }

        if (! $user->can('file.read-content', $server)) {
            throw new ToolError("Checking expected_sha256 needs the file.read-content permission on {$server->name}.");
        }

        $current = hash('sha256', $this->files->read($server, $path, $this->settings->maxTransferBytes()));

        if ($current !== $expected) {
            throw new ToolError("{$path} changed since you read it: its sha256 is now {$current}. Read it again before writing.");
        }
    }
}
