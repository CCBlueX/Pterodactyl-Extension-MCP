<?php

declare(strict_types=1);

namespace PterodactylMcp\Protocol;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Models\User;
use PterodactylMcp\Panel\ActivityTag;
use PterodactylMcp\Tools\Arguments;
use PterodactylMcp\Tools\Registry;
use PterodactylMcp\Tools\Tool;
use PterodactylMcp\Tools\ToolError;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Answers MCP requests (JSON-RPC 2.0) for one signed-in user.
 */
final readonly class McpServer
{
    public const array VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];

    private const string INSTRUCTIONS = <<<'TEXT'
        Manage Pterodactyl game servers as the signed-in user. Call list_servers first to get server identifiers.
        send_command returns the console lines the command printed; raise wait_seconds or pass until for slow commands. power can wait until the server is running or offline.
        File paths are relative to the server's root. read_file can page, tail and grep large logs, including .gz files, and returns a sha256 that write_file and edit_file accept as expected_sha256 to avoid overwriting someone else's change.
        For binary files call read_file or write_file with encoding base64 from a script, or use download_file_url and upload_file_url.
        Tools that change files, backups or settings are only listed when the panel administrator allows them. Every action is recorded in the server's activity log.
        TEXT;

    public function __construct(private Registry $tools) {}

    public static function version(): string
    {
        static $version;

        return $version ??= (string) (json_decode((string) file_get_contents(dirname(__DIR__, 2).'/extension.json'), true)['version'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null Null for notifications.
     */
    public function handle(array $message, User $user): ?array
    {
        if (! JsonRpc::isValid($message)) {
            return JsonRpc::error($message['id'] ?? null, JsonRpc::INVALID_REQUEST, 'Invalid JSON-RPC request.');
        }

        if (JsonRpc::isNotification($message)) {
            return null;
        }

        $id = $message['id'];
        $params = $message['params'] ?? [];

        return match ($message['method']) {
            'initialize' => JsonRpc::result($id, $this->initialize($params)),
            'ping' => JsonRpc::result($id, (object) []),
            'tools/list' => JsonRpc::result($id, ['tools' => array_values(array_map($this->describe(...), $this->tools->for($user)))]),
            'tools/call' => $this->call($id, $params, $user),
            default => JsonRpc::error($id, JsonRpc::METHOD_NOT_FOUND, "Unknown method \"{$message['method']}\"."),
        };
    }

    /** @return array<string, mixed> */
    private static function content(string $text, bool $error): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => $error];
    }

    private static function describeError(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ToolError, $exception instanceof DisplayException, $exception instanceof InvalidArgumentException => $exception->getMessage(),
            $exception instanceof ValidationException => implode(' ', array_merge(...array_values($exception->errors()))),
            $exception instanceof AuthorizationException => 'You are not allowed to do this.',
            $exception instanceof DaemonConnectionException => $exception->getMessage(),
            $exception instanceof HttpExceptionInterface => $exception->getMessage() !== '' ? $exception->getMessage() : 'The request failed with status '.$exception->getStatusCode().'.',
            default => self::unexpected($exception),
        };
    }

    private static function unexpected(Throwable $exception): string
    {
        report($exception);

        return 'The tool failed: '.$exception->getMessage();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $requested = (string) ($params['protocolVersion'] ?? '');

        return [
            'protocolVersion' => in_array($requested, self::VERSIONS, true) ? $requested : self::VERSIONS[0],
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'pterodactyl', 'title' => 'Pterodactyl', 'version' => self::version()],
            'instructions' => self::INSTRUCTIONS,
        ];
    }

    /** @return array<string, mixed> */
    private function describe(Tool $tool): array
    {
        return [
            'name' => $tool->name(),
            'title' => $tool->title(),
            'description' => $tool->description(),
            'inputSchema' => $tool->schema(),
            'annotations' => ['title' => $tool->title()] + $tool->kind()->annotations(),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(int|string $id, array $params, User $user): array
    {
        $tool = $this->tools->for($user)[(string) ($params['name'] ?? '')] ?? null;

        if ($tool === null) {
            return JsonRpc::error($id, JsonRpc::INVALID_PARAMS, 'Unknown tool "'.($params['name'] ?? '').'".');
        }

        try {
            $data = ActivityTag::during(static fn (): array => $tool->call($user, new Arguments(is_array($params['arguments'] ?? null) ? $params['arguments'] : [])));
        } catch (Throwable $exception) {
            return JsonRpc::result($id, self::content(self::describeError($exception), true));
        }

        return JsonRpc::result($id, ['structuredContent' => (object) $data] + self::content(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), false));
    }
}
