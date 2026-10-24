<?php

declare(strict_types=1);

namespace PterodactylMcp\Tools\Files;

use Pterodactyl\Models\User;
use PterodactylMcp\Panel\Files;
use PterodactylMcp\Settings;
use PterodactylMcp\Support\Pattern;
use PterodactylMcp\Support\TextView;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Schema;
use PterodactylMcp\Tools\ServerTool;
use PterodactylMcp\Tools\ToolError;

final class ReadFile extends ServerTool
{
    public function __construct(private readonly Files $files, private readonly Settings $settings) {}

    public function name(): string
    {
        return 'read_file';
    }

    public function title(): string
    {
        return 'Read file';
    }

    public function description(): string
    {
        return 'Read a server file as text: the whole file, a line range (offset/limit), the last lines (tail) or the lines matching grep, like grep -n -C. Files ending in .gz are unpacked; long answers are cut on a line boundary with next_offset to continue. Use encoding base64 for binary files.';
    }

    public function schema(): array
    {
        return Schema::object([
            'server' => Schema::server(),
            'path' => Schema::string('The file from the server root, such as "/server.properties" or "/logs/latest.log".', ['maxLength' => 2048]),
            'offset' => Schema::integer('The first line to return, starting at 1.', 1),
            'limit' => Schema::integer('How many lines to return from offset.', 1),
            'tail' => Schema::integer('Return only the last this many lines. Cannot be combined with offset or limit.', 1),
            'grep' => Schema::pattern('Only return the lines matching this expression, prefixed with their line number.'),
            'ignore' => Schema::pattern('Leave out the lines matching this expression.'),
            'context' => Schema::integer('Lines of context to show around each grep match.', 0, 10, 0),
            'encoding' => Schema::enum(['text', 'base64'], 'text for UTF-8 files, base64 for the raw bytes of any file.', 'text'),
        ], ['server', 'path']);
    }

    public function call(User $user, Arguments $arguments): array
    {
        $path = Files::normalize($arguments->string('path', null, 2048));
        $encoding = $arguments->string('encoding', 'text');
        $offset = $arguments->has('offset') ? $arguments->int('offset', 1, 1) : null;
        $limit = $arguments->has('limit') ? $arguments->int('limit', 1, 1) : null;
        $tail = $arguments->has('tail') ? $arguments->int('tail', 1, 1) : null;
        $grep = Pattern::optional($arguments->optionalString('grep', 500));
        $ignore = Pattern::optional($arguments->optionalString('ignore', 500));

        if (! in_array($encoding, ['text', 'base64'], true)) {
            throw new ToolError('The argument "encoding" must be text or base64.');
        }

        if ($tail !== null && ($offset !== null || $limit !== null)) {
            throw new ToolError('Use either tail or offset/limit, not both.');
        }

        $server = $this->server($user, $arguments, ['file.read-content']);
        $raw = $this->files->read($server, $path, $this->settings->maxTransferBytes());
        $this->files->logRead($server, $path);
        $file = ['path' => $path, 'size_bytes' => mb_strlen($raw, '8bit'), 'sha256' => hash('sha256', $raw)];

        if ($encoding === 'base64') {
            return [...$file, 'encoding' => 'base64', 'content' => base64_encode($raw)];
        }

        $view = TextView::of($this->text($path, $raw));
        $file['total_lines'] = $view->totalLines();
        $maxBytes = $this->settings->maxReadBytes();

        return $grep === null
            ? [...$file, ...$view->lines($offset, $limit, $tail, $ignore, $maxBytes)]
            : [...$file, ...$view->grep($grep, $ignore, $arguments->int('context', 0, 0, 10), $offset, $limit, $tail, $maxBytes)];
    }

    private function text(string $path, string $raw): string
    {
        if (str_ends_with(mb_strtolower($path), '.gz')) {
            $unpacked = @gzdecode($raw, $this->settings->maxTransferBytes());

            if ($unpacked === false) {
                throw new ToolError("{$path} is not a valid gzip file or unpacks to more than this MCP server reads in full. Use encoding base64 or download_file_url instead.");
            }

            $raw = $unpacked;
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            throw new ToolError("{$path} is not UTF-8 text. Read it with encoding base64 instead.");
        }

        return $raw;
    }
}
