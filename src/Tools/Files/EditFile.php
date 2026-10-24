<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Files;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Files;
use PterodactylMcp\Settings;
use PterodactylMcp\Support\TextEdits;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Kind;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class EditFile extends ServerTool
{
    private const int MAX_EDITS = 50;

    public function __construct(private readonly Files $files, private readonly Settings $settings) {}

    public function name(): string
    {
        return 'edit_file';
    }

    public function title(): string
    {
        return 'Edit file';
    }

    public function description(): string
    {
        return 'Replace exact text in a server file. Every old_text must occur exactly once unless replace_all is set; edits apply in order, each to the result of the one before, and nothing is written if any edit fails.';
    }

    public function kind(): Kind
    {
        return Kind::Write;
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'path' => Schema::string('The file from the server root, such as "/server.properties".', ['maxLength' => 2048]),
            'edits' => Schema::list(Schema::object([
                'old_text' => Schema::string('The exact text to replace, including whitespace and line breaks.'),
                'new_text' => Schema::string('The text to put in its place.'),
                'replace_all' => Schema::boolean('Replace every occurrence instead of requiring exactly one.', false),
            ], ['old_text', 'new_text']), 'The replacements, applied in order.', self::MAX_EDITS),
            'expected_sha256' => Schema::string('The sha256 of the file as read_file returned it; the edit is refused if the file changed since.', ['maxLength' => 64]),
        ], ['server', 'path', 'edits']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $path = Files::normalize($arguments->string('path', null, 2048));
        $edits = array_map(static function (mixed $item): array {
            $edit = Arguments::of($item, 'edits');

            return ['old_text' => $edit->string('old_text'), 'new_text' => $edit->string('new_text'), 'replace_all' => $edit->bool('replace_all')];
        }, $arguments->list('edits', self::MAX_EDITS));
        $expected = $arguments->optionalString('expected_sha256', 64);

        if ($edits === []) {
            throw new ToolError('Give at least one edit in "edits".');
        }

        $server = $this->server($user, $arguments, ['file.read-content', 'file.update']);
        $current = $this->files->read($server, $path, $this->settings->maxTransferBytes());
        $sha256 = hash('sha256', $current);

        if ($expected !== null && mb_strtolower($expected) !== $sha256) {
            throw new ToolError("{$path} changed since you read it: its sha256 is now {$sha256}. Read it again before editing.");
        }

        $result = TextEdits::apply($current, $edits);

        if ($result['text'] !== $current) {
            $this->files->write($server, $path, $result['text']);
        }

        return ['path' => $path, 'replacements' => $result['replacements'], 'sha256' => hash('sha256', $result['text']), 'size_bytes' => mb_strlen($result['text'], '8bit')];
    }
}
