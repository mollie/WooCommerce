<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\FakeMollie;

/**
 * Answers every WordPress HTTP request to api.mollie.com from the fake, in-process.
 *
 * Hooked on pre_http_request, so nothing leaves the machine: no API key is needed, a syntactically
 * valid fake key is enough, and a "live_" fake key can never reach Mollie. That last point is a
 * safety property and not a convenience — the Express Component may be gated on live mode, so the
 * test environment has to look live. Anything on the Mollie host the fake does not implement is
 * answered 501 instead of being let through.
 */
final class FakeMollieTransport
{
    private const HOST = 'api.mollie.com';

    private FakeMollieApi $api;

    public function __construct(FakeMollieApi $api)
    {
        $this->api = $api;
    }

    public function install(): void
    {
        // Priority 1: a test environment must never get a chance to let the request through.
        add_filter('pre_http_request', [$this, 'intercept'], 1, 3);
    }

    public function uninstall(): void
    {
        remove_filter('pre_http_request', [$this, 'intercept'], 1);
    }

    /**
     * @param false|array<string, mixed>|\WP_Error $preempt
     * @param array<string, mixed> $args
     * @param string $url
     * @return false|array<string, mixed>|\WP_Error
     */
    public function intercept($preempt, $args, $url)
    {
        $parts = wp_parse_url((string) $url);
        if (!is_array($parts) || strtolower((string) ($parts['host'] ?? '')) !== self::HOST) {
            return $preempt;
        }

        $path = (string) ($parts['path'] ?? '/');
        if (strpos($path, '/v2/') !== 0) {
            return $this->response(501, [
                'status' => 501,
                'title' => 'Not Faked',
                'detail' => "{$path} is outside /v2/ and is not implemented by the fake Mollie API.",
            ]);
        }

        $apiPath = substr($path, 4) . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $headers = array_map('strval', (array) ($args['headers'] ?? []));
        $body = isset($args['body']) && is_string($args['body']) ? $args['body'] : null;

        $result = $this->api->handle((string) ($args['method'] ?? 'GET'), $apiPath, $headers, $body);

        return $this->response($result['status'], $result['body']);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function response(int $status, ?array $body): array
    {
        return [
            'headers' => ['content-type' => 'application/hal+json'],
            'body' => $body === null ? '' : (string) wp_json_encode($body),
            'response' => ['code' => $status, 'message' => get_status_header_desc($status)],
            'cookies' => [],
            'filename' => null,
        ];
    }
}
