<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\FakeMollie;

/**
 * A stateful stand-in for api.mollie.com, covering what the Express Component flow touches.
 *
 * It sits behind the HTTP layer (see FakeMollieTransport), not behind the SDK, so the plugin's
 * real Api helper, the real SDK and the real WordPressHttpAdapter all run. That matters for this
 * feature: the Sessions call is a raw performHttpCall(), exception messages are assembled by the
 * adapter, and the idempotency key travels as a header — none of which a Mockery double of
 * MollieApiClient would exercise.
 *
 * What it models, because the feature depends on it:
 *  - POST /v2/sessions is validated with SessionRules and answered 422 on any deviation.
 *  - A session's metadata, description, redirectUrl and payment.webhookUrl are inherited by the
 *    payment created from it. The plugin never creates that payment; completeSession() is the
 *    stand-in for the shopper authorising in the wallet.
 *  - Idempotency-Key replays: same key and body returns the first response, same key with a
 *    different body is refused.
 *
 * What it does not model: anything the beta has not documented. In particular the payment carries
 * no reference back to its session, because nobody has seen a real payload that does.
 */
final class FakeMollieApi
{
    public const SESSION_LIFETIME_SECONDS = 900;

    private FakeMollieStore $store;

    public function __construct(FakeMollieStore $store)
    {
        $this->store = $store;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // The API surface
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: array<string, mixed>|null}
     */
    public function handle(string $method, string $path, array $headers, ?string $body): array
    {
        $method = strtoupper($method);
        $query = [];
        $parts = explode('?', $path, 2);
        $path = trim($parts[0], '/');
        if (isset($parts[1])) {
            parse_str($parts[1], $query);
        }
        $headers = array_change_key_case($headers, CASE_LOWER);
        $payload = $body === null || $body === '' ? [] : json_decode($body, true);

        $state = $this->state();
        $request = [
            'method' => $method,
            'path' => $path,
            'query' => $query,
            'body' => is_array($payload) ? $payload : null,
            'idempotencyKey' => $headers['idempotency-key'] ?? null,
            'authorizedAs' => $this->modeFromAuthorization($headers),
            'replayed' => false,
        ];

        $response = $this->respond($state, $request, (string) $body);

        $request['status'] = $response['status'];
        $state['requests'][] = $request;
        $this->store->save($state);

        return $response;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $request
     * @return array{status: int, body: array<string, mixed>|null}
     */
    private function respond(array &$state, array &$request, string $rawBody): array
    {
        if ($request['authorizedAs'] === null) {
            return $this->problem(401, 'Unauthorized Request', 'Missing authentication, or failed to authenticate.');
        }
        if ($request['body'] === null) {
            return $this->problem(400, 'Bad Request', 'The request body is not valid JSON.');
        }

        foreach ($state['failures'] as $index => $failure) {
            if ($failure['method'] === $request['method'] && strpos($request['path'], $failure['path']) === 0) {
                unset($state['failures'][$index]);
                $state['failures'] = array_values($state['failures']);

                return $this->problem($failure['status'], $failure['title'], $failure['detail'], $failure['field']);
            }
        }

        $key = $request['idempotencyKey'];
        if ($key !== null && $request['method'] !== 'GET' && isset($state['idempotency'][$key])) {
            $seen = $state['idempotency'][$key];
            if ($seen['bodyHash'] !== md5($rawBody) || $seen['path'] !== $request['path']) {
                return $this->problem(
                    400,
                    'Bad Request',
                    'You are using an idempotency key that you used before with different parameters.'
                        . ' Please send a unique idempotency key with each request.'
                );
            }
            $request['replayed'] = true;

            return $seen['response'];
        }

        $response = $this->route($state, $request);

        if ($key !== null && $request['method'] !== 'GET' && $response['status'] < 500) {
            $state['idempotency'][$key] = [
                'path' => $request['path'],
                'bodyHash' => md5($rawBody),
                'response' => $response,
            ];
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $request
     * @return array{status: int, body: array<string, mixed>|null}
     */
    private function route(array &$state, array $request): array
    {
        $method = $request['method'];
        $path = $request['path'];

        if ($method === 'POST' && $path === 'sessions') {
            return $this->createSession($state, $request);
        }
        if ($method === 'GET' && preg_match('#^sessions/(sess_[^/]+)$#', $path, $m)) {
            return isset($state['sessions'][$m[1]])
                ? ['status' => 200, 'body' => $this->sessionResource($state['sessions'][$m[1]])]
                : $this->problem(404, 'Not Found', 'No session exists with token ' . $m[1] . '.');
        }
        if ($method === 'GET' && preg_match('#^payments/(tr_[^/]+)$#', $path, $m)) {
            return isset($state['payments'][$m[1]])
                ? ['status' => 200, 'body' => $this->paymentResource($state, $state['payments'][$m[1]])]
                : $this->problem(404, 'Not Found', 'No payment exists with token ' . $m[1] . '.');
        }
        if (preg_match('#^payments/(tr_[^/]+)/refunds$#', $path, $m)) {
            if (!isset($state['payments'][$m[1]])) {
                return $this->problem(404, 'Not Found', 'No payment exists with token ' . $m[1] . '.');
            }

            return $method === 'POST'
                ? $this->createRefund($state, $m[1], $request)
                : ['status' => 200, 'body' => $this->collection('refunds', $this->refundsOf($state, $m[1]))];
        }
        if ($method === 'GET' && preg_match('#^payments/(tr_[^/]+)/chargebacks$#', $path, $m)) {
            return ['status' => 200, 'body' => $this->collection('chargebacks', [])];
        }
        if ($method === 'GET' && ($path === 'methods' || $path === 'methods/all')) {
            return ['status' => 200, 'body' => $this->collection('methods', array_map([$this, 'methodResource'], $state['methods']))];
        }
        if ($method === 'GET' && preg_match('#^methods/([a-z0-9]+)$#', $path, $m)) {
            return in_array($m[1], $state['methods'], true)
                ? ['status' => 200, 'body' => $this->methodResource($m[1])]
                : $this->problem(404, 'Not Found', 'The payment method is not available.');
        }
        if ($method === 'GET' && $path === 'profiles/me') {
            return ['status' => 200, 'body' => [
                'resource' => 'profile',
                'id' => 'pfl_fake',
                'mode' => $request['authorizedAs'],
                'name' => 'Fake Mollie profile',
                'status' => 'verified',
            ]];
        }

        return $this->problem(501, 'Not Faked', "{$method} /v2/{$path} is not implemented by the fake Mollie API.");
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $request
     * @return array{status: int, body: array<string, mixed>|null}
     */
    private function createSession(array &$state, array $request): array
    {
        $violations = SessionRules::violations($request['body']);
        if ($violations) {
            return $this->problem(422, 'Unprocessable Entity', $violations[0]['message'], $violations[0]['field']);
        }

        $id = 'sess_fake' . $this->nextSequence($state);
        $now = $this->now($state);
        $session = array_merge($request['body'], [
            'id' => $id,
            'mode' => $request['authorizedAs'],
            'status' => 'open',
            'createdAt' => gmdate('c', $now),
            'expiresAt' => gmdate('c', $now + self::SESSION_LIFETIME_SECONDS),
            'completedAt' => null,
            'paymentId' => null,
        ]);
        $state['sessions'][$id] = $session;

        return ['status' => 201, 'body' => $this->sessionResource($session)];
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $request
     * @return array{status: int, body: array<string, mixed>|null}
     */
    private function createRefund(array &$state, string $paymentId, array $request): array
    {
        $payment = $state['payments'][$paymentId];
        $amount = $request['body']['amount'] ?? null;
        if (!is_array($amount) || !isset($amount['value'], $amount['currency'])) {
            return $this->problem(422, 'Unprocessable Entity', 'The amount is required.', 'amount');
        }
        if (!in_array($payment['status'], ['paid', 'authorized'], true)) {
            return $this->problem(422, 'Unprocessable Entity', 'The payment cannot be refunded in its current state.');
        }

        $refunded = array_sum(array_map(static function (array $refund): float {
            return (float) $refund['amount']['value'];
        }, $this->refundsOf($state, $paymentId)));
        if ($refunded + (float) $amount['value'] > (float) $payment['amount']['value'] + 0.0001) {
            return $this->problem(422, 'Unprocessable Entity', 'The amount is higher than the remaining amount.', 'amount');
        }

        $id = 're_fake' . $this->nextSequence($state);
        $state['refunds'][$id] = [
            'resource' => 'refund',
            'id' => $id,
            'paymentId' => $paymentId,
            'amount' => $amount,
            'status' => 'pending',
            'description' => (string) ($request['body']['description'] ?? ''),
            'metadata' => $request['body']['metadata'] ?? null,
            'createdAt' => gmdate('c', $this->now($state)),
        ];

        return ['status' => 201, 'body' => $state['refunds'][$id]];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Control: what a test does in place of the shopper and of Mollie's own clock
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The shopper authorised in the wallet: Mollie creates the payment for the session.
     *
     * @param array<string, mixed> $outcome status (paid), method (applepay), billingAddress,
     *                                      shippingAddress, amount (to simulate a mismatch).
     * @return array<string, mixed> The payment as stored, including its webhookUrl.
     */
    public function completeSession(string $sessionId, array $outcome = []): array
    {
        $state = $this->state();
        if (!isset($state['sessions'][$sessionId])) {
            throw new \InvalidArgumentException("The fake Mollie has no session {$sessionId}.");
        }
        $session = $state['sessions'][$sessionId];
        if ($session['status'] !== 'open') {
            throw new \LogicException("Session {$sessionId} is {$session['status']}; only an open session can be paid.");
        }

        $status = (string) ($outcome['status'] ?? 'paid');
        $now = $this->now($state);
        $id = 'tr_fake' . $this->nextSequence($state);
        $payment = [
            'id' => $id,
            'mode' => $session['mode'],
            'status' => $status,
            'method' => (string) ($outcome['method'] ?? 'applepay'),
            'amount' => $outcome['amount'] ?? $session['amount'],
            'description' => $session['description'],
            'metadata' => $session['metadata'] ?? null,
            'redirectUrl' => $session['redirectUrl'],
            'webhookUrl' => $session['payment']['webhookUrl'] ?? null,
            'billingAddress' => $outcome['billingAddress'] ?? ($session['billingAddress'] ?? null),
            'shippingAddress' => $outcome['shippingAddress'] ?? ($session['shippingAddress'] ?? null),
            'createdAt' => gmdate('c', $now),
            'paidAt' => $status === 'paid' ? gmdate('c', $now) : null,
        ];
        $state['payments'][$id] = $payment;

        if (in_array($status, ['paid', 'authorized', 'pending'], true)) {
            $state['sessions'][$sessionId]['status'] = 'completed';
            $state['sessions'][$sessionId]['completedAt'] = gmdate('c', $now);
        }
        $state['sessions'][$sessionId]['paymentId'] = $id;
        $this->store->save($state);

        return $payment;
    }

    /**
     * @param array<string, mixed> $extra Fields to overwrite on the stored payment.
     */
    public function setPaymentStatus(string $paymentId, string $status, array $extra = []): void
    {
        $state = $this->state();
        if (!isset($state['payments'][$paymentId])) {
            throw new \InvalidArgumentException("The fake Mollie has no payment {$paymentId}.");
        }
        $state['payments'][$paymentId] = array_merge($state['payments'][$paymentId], $extra, ['status' => $status]);
        $this->store->save($state);
    }

    public function expireSession(string $sessionId): void
    {
        $state = $this->state();
        if (!isset($state['sessions'][$sessionId])) {
            throw new \InvalidArgumentException("The fake Mollie has no session {$sessionId}.");
        }
        $state['sessions'][$sessionId]['status'] = 'expired';
        $this->store->save($state);
    }

    /**
     * Makes the next matching call fail once: an outage (500), a rate limit (429), a rejection.
     */
    public function failNext(string $method, string $pathPrefix, int $status, string $detail = 'Simulated failure.', ?string $field = null): void
    {
        $titles = [400 => 'Bad Request', 422 => 'Unprocessable Entity', 429 => 'Too Many Requests', 500 => 'Internal Server Error', 503 => 'Service Unavailable'];
        $state = $this->state();
        $state['failures'][] = [
            'method' => strtoupper($method),
            'path' => trim($pathPrefix, '/'),
            'status' => $status,
            'title' => $titles[$status] ?? 'Error',
            'detail' => $detail,
            'field' => $field,
        ];
        $this->store->save($state);
    }

    /**
     * @param array<int, string> $methodIds
     */
    public function setMethods(array $methodIds): void
    {
        $state = $this->state();
        $state['methods'] = array_values($methodIds);
        $this->store->save($state);
    }

    /**
     * Pins the fake's clock, so createdAt, and with it when a session expires, are known values.
     */
    public function setNow(?int $timestamp): void
    {
        $state = $this->state();
        $state['now'] = $timestamp;
        $this->store->save($state);
    }

    public function reset(): void
    {
        $this->store->save([]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Observation
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Every request received, in order. Optionally narrowed to one method and path prefix.
     *
     * @return array<int, array<string, mixed>>
     */
    public function requests(?string $method = null, ?string $pathPrefix = null): array
    {
        $requests = $this->state()['requests'];

        return array_values(array_filter($requests, static function (array $request) use ($method, $pathPrefix): bool {
            return ($method === null || $request['method'] === strtoupper($method))
                && ($pathPrefix === null || strpos($request['path'], trim($pathPrefix, '/')) === 0);
        }));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sessions(): array
    {
        return $this->state()['sessions'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function payments(): array
    {
        return $this->state()['payments'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function refunds(): array
    {
        return $this->state()['refunds'];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Resources
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    private function sessionResource(array $session): array
    {
        $resource = $session;
        // The real API answers an open session without any expiry
        // expiredAt appears only once the session has expired.
        unset($resource['paymentId'], $resource['expiresAt']);
        if ($session['status'] === 'expired') {
            $resource['expiredAt'] = $session['expiresAt'];
        }
        $resource['resource'] = 'session';
        // Opaque to the plugin. The browser stub reads the session id back out of it; like the
        // real token it identifies the session and nothing about the merchant account.
        $resource['clientAccessToken'] = self::clientAccessTokenFor($session['id']);
        $resource['_links'] = [
            'self' => ['href' => 'https://api.mollie.com/v2/sessions/' . $session['id'], 'type' => 'application/hal+json'],
        ];

        return $resource;
    }

    public static function clientAccessTokenFor(string $sessionId): string
    {
        return 'fake_cat_' . rtrim(strtr(base64_encode($sessionId), '+/', '-_'), '=');
    }

    public static function sessionIdFromClientAccessToken(string $token): ?string
    {
        if (strpos($token, 'fake_cat_') !== 0) {
            return null;
        }
        $decoded = base64_decode(strtr(substr($token, 9), '-_', '+/'), true);

        return is_string($decoded) && strpos($decoded, 'sess_') === 0 ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    private function paymentResource(array $state, array $payment): array
    {
        $refunds = $this->refundsOf($state, $payment['id']);
        $refunded = array_sum(array_map(static function (array $refund): float {
            return (float) $refund['amount']['value'];
        }, $refunds));
        $currency = $payment['amount']['currency'];
        $money = static function (float $value) use ($currency): array {
            return ['value' => number_format($value, 2, '.', ''), 'currency' => $currency];
        };
        $base = 'https://api.mollie.com/v2/payments/' . $payment['id'];

        $resource = array_merge($payment, [
            'resource' => 'payment',
            'profileId' => 'pfl_fake',
            'sequenceType' => 'oneoff',
            'locale' => 'en_US',
            'amountRefunded' => $money($refunded),
            'amountRemaining' => $money(max(0.0, (float) $payment['amount']['value'] - $refunded)),
            '_links' => [
                'self' => ['href' => $base, 'type' => 'application/hal+json'],
                'dashboard' => ['href' => 'https://www.mollie.com/dashboard/payments/' . $payment['id'], 'type' => 'text/html'],
            ],
        ]);
        if ($refunds) {
            $resource['_links']['refunds'] = ['href' => $base . '/refunds', 'type' => 'application/hal+json'];
        }

        return array_filter($resource, static function ($value): bool {
            return $value !== null;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function methodResource(string $id): array
    {
        $image = 'https://www.mollie.com/external/icons/payment-methods/' . $id;

        return [
            'resource' => 'method',
            'id' => $id,
            'description' => ucfirst($id),
            'minimumAmount' => ['value' => '0.01', 'currency' => 'EUR'],
            'maximumAmount' => ['value' => '50000.00', 'currency' => 'EUR'],
            // The svg matters: AbstractPaymentMethod::getApiIcon() is declared ": string" and
            // fatals on a method without one, which takes the block cart down with it.
            'image' => ['size1x' => $image . '.png', 'size2x' => $image . '%402x.png', 'svg' => $image . '.svg'],
            'status' => 'activated',
            '_links' => ['self' => ['href' => 'https://api.mollie.com/v2/methods/' . $id, 'type' => 'application/hal+json']],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function collection(string $name, array $items): array
    {
        return [
            'count' => count($items),
            '_embedded' => [$name => array_values($items)],
            '_links' => [
                'self' => ['href' => 'https://api.mollie.com/v2/' . $name, 'type' => 'application/hal+json'],
                'previous' => null,
                'next' => null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @return array<int, array<string, mixed>>
     */
    private function refundsOf(array $state, string $paymentId): array
    {
        return array_values(array_filter($state['refunds'], static function (array $refund) use ($paymentId): bool {
            return $refund['paymentId'] === $paymentId;
        }));
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function problem(int $status, string $title, string $detail, ?string $field = null): array
    {
        $body = [
            'status' => $status,
            'title' => $title,
            'detail' => $detail,
            '_links' => ['documentation' => ['href' => 'https://docs.mollie.com/overview/handling-errors', 'type' => 'text/html']],
        ];
        if ($field !== null) {
            $body['field'] = $field;
        }

        return ['status' => $status, 'body' => $body];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        return array_merge([
            'sessions' => [],
            'payments' => [],
            'refunds' => [],
            'requests' => [],
            'idempotency' => [],
            'failures' => [],
            'methods' => ['ideal', 'creditcard', 'banktransfer', 'paypal', 'applepay'],
            'now' => null,
            'sequence' => 0,
        ], $this->store->load());
    }

    /**
     * @param array<string, mixed> $state
     */
    private function nextSequence(array &$state): string
    {
        $state['sequence']++;

        // The sequence keeps ids readable in order; the random tail keeps them unique across runs.
        // Test orders outlive a run in the shared database, and the webhook route authenticates a
        // known payment id, so a recycled id would be "known" for the wrong reason.
        return str_pad((string) $state['sequence'], 4, '0', STR_PAD_LEFT) . bin2hex(random_bytes(5));
    }

    /**
     * @param array<string, mixed> $state
     */
    private function now(array $state): int
    {
        return is_int($state['now']) ? $state['now'] : time();
    }

    /**
     * @param array<string, string> $headers Lower-cased.
     */
    private function modeFromAuthorization(array $headers): ?string
    {
        if (!preg_match('#^Bearer (live|test)_\w{30,}$#', (string) ($headers['authorization'] ?? ''), $m)) {
            return null;
        }

        return $m[1];
    }
}
