<?php

declare(strict_types=1);

namespace PterodactylMcp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Services\Extensions\ExtensionSettingsRegistry;
use PterodactylMcp\Protocol\JsonRpc;
use PterodactylMcp\Protocol\McpServer;
use PterodactylMcp\Tools\Registry;
use PterodactylMcp\Tools\Tool;

/**
 * The Streamable HTTP transport, stateless and without server-sent events.
 */
final class McpController
{
    public function __construct(private readonly McpServer $server, private readonly ExtensionSettingsRegistry $settings) {}

    public function __invoke(Request $request): JsonResponse|Response
    {
        abort_unless($this->enabled(), 404);
        abort_if($request->headers->has('Origin') && ! $this->sameOrigin((string) $request->header('Origin')), 403, 'Requests from other websites are not allowed.');

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return new JsonResponse(JsonRpc::error(null, JsonRpc::PARSE_ERROR, 'The body is not valid JSON.'), 400);
        }

        $user = $request->user();
        $batch = array_is_list($payload);
        $responses = array_values(array_filter(array_map(
            fn (mixed $message): ?array => is_array($message) ? $this->server->handle($message, $user) : JsonRpc::error(null, JsonRpc::INVALID_REQUEST, 'Invalid JSON-RPC request.'),
            $batch ? $payload : [$payload],
        )));

        if ($responses === []) {
            return new Response('', 202);
        }

        return new JsonResponse($batch ? $responses : $responses[0], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function stream(): Response
    {
        return new Response('', 405, ['Allow' => 'POST']);
    }

    /** The tools the signed-in user gets, for the account screen. */
    public function tools(Request $request, Registry $registry): JsonResponse
    {
        return new JsonResponse([
            'enabled' => $this->enabled(),
            'version' => McpServer::version(),
            'tools' => array_values(array_map(static fn (Tool $tool): array => [
                'name' => $tool->name(),
                'title' => $tool->title(),
                'description' => $tool->description(),
                'group' => Registry::group($tool),
                'kind' => $tool->kind()->value,
            ], $registry->for($request->user()))),
        ]);
    }

    private function enabled(): bool
    {
        return (bool) ($this->settings->get('mcp')?->get('enabled') ?? true);
    }

    private function sameOrigin(string $origin): bool
    {
        return mb_rtrim($origin, '/') === mb_rtrim((string) config('app.url'), '/');
    }
}
