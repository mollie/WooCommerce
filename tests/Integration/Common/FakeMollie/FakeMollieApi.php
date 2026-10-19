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
        if ($method === 'POST' && $path === 'payments') {
            return $this->createPayment($state, $request);
        }
        if ($method === 'DELETE' && preg_match('#^payments/(tr_[^/]+)$#', $path, $m)) {
            if (!isset($state['payments'][$m[1]])) {
                return $this->problem(404, 'Not Found', 'No payment exists with token ' . $m[1] . '.');
            }
            // Only an authorisation that was not captured can be released.
            if ($state['payments'][$m[1]]['status'] !== 'authorized') {
                return $this->problem(422, 'Unprocessable Entity', 'The payment cannot be canceled.');
            }
            $state['payments'][$m[1]]['status'] = 'canceled';

            return ['status' => 200, 'body' => $this->paymentResource($state, $state['payments'][$m[1]])];
        }
        if ($method === 'POST' && preg_match('#^payments/(tr_[^/]+)/captures$#', $path, $m)) {
            return isset($state['payments'][$m[1]])
                ? $this->createCapture($state, $m[1], $request)
                : $this->problem(404, 'Not Found', 'No payment exists with token ' . $m[1] . '.');
        }
        if ($method === 'DELETE' && preg_match('#^orders/(ord_[^/]+)$#', $path, $m)) {
            if (!isset($state['orders'][$m[1]])) {
                return $this->problem(404, 'Not Found', 'No order exists with token ' . $m[1] . '.');
            }
            if (!in_array($state['orders'][$m[1]]['status'], ['created', 'authorized', 'shipping'], true)) {
                return $this->problem(422, 'Unprocessable Entity', 'The order cannot be canceled from state: ' . $state['orders'][$m[1]]['status']);
            }
            $state['orders'][$m[1]]['status'] = 'canceled';

            return ['status' => 200, 'body' => $this->orderResource($state, $state['orders'][$m[1]], '')];
        }
        if ($method === 'POST' && preg_match('#^orders/(ord_[^/]+)/shipments$#', $path, $m)) {
            if (!isset($state['orders'][$m[1]])) {
                return $this->problem(404, 'Not Found', 'No order exists with token ' . $m[1] . '.');
            }
            if (!in_array($state['orders'][$m[1]]['status'], ['paid', 'authorized', 'shipping'], true)) {
                return $this->problem(422, 'Unprocessable Entity', 'The order cannot be shipped from state: ' . $state['orders'][$m[1]]['status']);
            }
            // Every line at once, which is all the plugin asks for.
            $state['orders'][$m[1]]['status'] = 'completed';
            $id = 'shp_fake' . $this->nextSequence($state);
            $state['shipments'][$id] = ['resource' => 'shipment', 'id' => $id, 'orderId' => $m[1], 'lines' => $request['body']['lines'] ?? []];

            return ['status' => 201, 'body' => $state['shipments'][$id]];
        }
        if ($method === 'GET' && preg_match('#^customers/(cst_[^/]+)$#', $path, $m)) {
            return isset($state['customers'][$m[1]])
                ? ['status' => 200, 'body' => $this->customerResource($state['customers'][$m[1]])]
                : $this->problem(404, 'Not Found', 'No customer exists with token ' . $m[1] . '.');
        }
        if (preg_match('#^customers/(cst_[^/]+)/mandates$#', $path, $m)) {
            if (!isset($state['customers'][$m[1]])) {
                return $this->problem(404, 'Not Found', 'No customer exists with token ' . $m[1] . '.');
            }
            if ($method === 'POST') {
                $mandate = $this->storeMandate($state, $m[1], (string) ($request['body']['method'] ?? 'directdebit'), 'valid');

                return ['status' => 201, 'body' => $mandate];
            }

            return ['status' => 200, 'body' => $this->collection('mandates', $this->mandatesOf($state, $m[1]))];
        }
        if ($method === 'GET' && preg_match('#^customers/(cst_[^/]+)/mandates/(mdt_[^/]+)$#', $path, $m)) {
            return isset($state['mandates'][$m[2]]) && $state['mandates'][$m[2]]['customerId'] === $m[1]
                ? ['status' => 200, 'body' => $state['mandates'][$m[2]]]
                : $this->problem(404, 'Not Found', 'No mandate exists with token ' . $m[2] . '.');
        }
        if ($method === 'PATCH' && preg_match('#^payments/(tr_[^/]+)$#', $path, $m)) {
            if (!isset($state['payments'][$m[1]])) {
                return $this->problem(404, 'Not Found', 'No payment exists with token ' . $m[1] . '.');
            }
            // The fields Mollie lets a merchant change on an existing payment.
            $changes = array_intersect_key(
                $request['body'],
                array_flip(['description', 'redirectUrl', 'cancelUrl', 'webhookUrl', 'metadata', 'restrictPaymentMethodsToCountry'])
            );
            $state['payments'][$m[1]] = array_merge($state['payments'][$m[1]], $changes);

            return ['status' => 200, 'body' => $this->paymentResource($state, $state['payments'][$m[1]])];
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
            return ['status' => 200, 'body' => $this->collection('chargebacks', $this->chargebacksOf($state, $m[1]))];
        }
        if ($method === 'GET' && preg_match('#^orders/(ord_[^/]+)$#', $path, $m)) {
            return isset($state['orders'][$m[1]])
                ? ['status' => 200, 'body' => $this->orderResource($state, $state['orders'][$m[1]], (string) ($request['query']['embed'] ?? ''))]
                : $this->problem(404, 'Not Found', 'No order exists with token ' . $m[1] . '.');
        }
        if ($method === 'GET' && $path === 'methods/all') {
            return ['status' => 200, 'body' => $this->collection('methods', array_map([$this, 'methodResource'], $state['methods']))];
        }
        if ($method === 'GET' && $path === 'methods') {
            $offered = array_values(array_filter($state['methods'], function (string $id) use ($state, $request): bool {
                return $this->methodIsOfferedFor($state['methodRules'][$id] ?? [], $request['query']);
            }));

            return ['status' => 200, 'body' => $this->collection('methods', array_map([$this, 'methodResource'], $offered))];
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
     * POST /v2/payments. A recurring payment needs the customer and a valid mandate of theirs; it
     * is charged without the shopper, so it starts pending (direct debit) or paid (anything else).
     *
     * @param array<string, mixed> $state
     * @param array<string, mixed> $request
     * @return array{status: int, body: array<string, mixed>|null}
     */
    private function createPayment(array &$state, array $request): array
    {
        $body = $request['body'];
        if (!isset($body['amount']['value'], $body['amount']['currency'])) {
            return $this->problem(422, 'Unprocessable Entity', 'The amount is required.', 'amount');
        }
        if (!isset($body['description']) || $body['description'] === '') {
            return $this->problem(422, 'Unprocessable Entity', 'The description is required.', 'description');
        }
        $sequenceType = (string) ($body['sequenceType'] ?? 'oneoff');
        $status = 'open';
        if ($sequenceType === 'recurring') {
            $customerId = (string) ($body['customerId'] ?? '');
            if (!isset($state['customers'][$customerId])) {
                return $this->problem(422, 'Unprocessable Entity', 'The customer id is invalid.', 'customerId');
            }
            $usable = array_filter($this->mandatesOf($state, $customerId), static function (array $mandate) use ($body): bool {
                return $mandate['status'] === 'valid'
                    && (!isset($body['mandateId']) || $mandate['id'] === $body['mandateId'])
                    && (!isset($body['method']) || $mandate['method'] === $body['method']);
            });
            if ($usable === []) {
                return $this->problem(422, 'Unprocessable Entity', 'No suitable mandates found for customer.', 'customerId');
            }
            $mandate = array_values($usable)[0];
            $body['mandateId'] = $mandate['id'];
            $body['method'] = $mandate['method'];
            $status = $mandate['method'] === 'directdebit' ? 'pending' : 'paid';
        }

        $now = $this->now($state);
        $id = 'tr_fake' . $this->nextSequence($state);
        $state['payments'][$id] = array_merge($body, [
            'id' => $id,
            'mode' => $request['authorizedAs'],
            'status' => $status,
            'sequenceType' => $sequenceType,
            'createdAt' => gmdate('c', $now),
            'paidAt' => $status === 'paid' ? gmdate('c', $now) : null,
        ]);

        return ['status' => 201, 'body' => $this->paymentResource($state, $state['payments'][$id])];
    }

    /**
     * POST /v2/payments/{id}/captures. The capture is accepted; the payment turns paid when the
     * test says Mollie settled it (setPaymentStatus), as the webhook that follows would tell.
     *
     * @param array<string, mixed> $state
     * @param array<string, mixed> $request
     * @return array{status: int, body: array<string, mixed>|null}
     */
    private function createCapture(array &$state, string $paymentId, array $request): array
    {
        if ($state['payments'][$paymentId]['status'] !== 'authorized') {
            return $this->problem(422, 'Unprocessable Entity', 'The payment cannot be captured in its current state.');
        }
        $id = 'cpt_fake' . $this->nextSequence($state);
        $state['captures'][$id] = [
            'resource' => 'capture',
            'id' => $id,
            'paymentId' => $paymentId,
            'amount' => $request['body']['amount'] ?? $state['payments'][$paymentId]['amount'],
            'status' => 'pending',
            'createdAt' => gmdate('c', $this->now($state)),
        ];

        return ['status' => 201, 'body' => $state['captures'][$id]];
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function storeMandate(array &$state, string $customerId, string $method, string $status): array
    {
        $id = 'mdt_fake' . $this->nextSequence($state);
        $state['mandates'][$id] = [
            'resource' => 'mandate',
            'id' => $id,
            'customerId' => $customerId,
            'mode' => $state['customers'][$customerId]['mode'],
            'method' => $method,
            'status' => $status,
            'createdAt' => gmdate('c', $this->now($state)),
        ];

        return $state['mandates'][$id];
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
     *                                      shippingAddress, amount (to simulate a mismatch), id (any
     *                                      tr_ id, since Mollie only promises the prefix).
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
        $id = (string) ($outcome['id'] ?? 'tr_fake' . $this->nextSequence($state));
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
        // The SDK reads "paid" off paidAt, not off the status.
        if ($status === 'paid' && empty($state['payments'][$paymentId]['paidAt'])) {
            $state['payments'][$paymentId]['paidAt'] = gmdate('c', $this->now($state));
        }
        $this->store->save($state);
    }

    /**
     * A payment the plugin created at checkout, in a run that is not this test: Payments API.
     *
     * @param array<string, mixed> $fields amount is required; id, mode (live), status (open),
     *                                     method (ideal), metadata and the rest have defaults.
     * @return array<string, mixed> The payment as stored.
     */
    public function seedPayment(array $fields): array
    {
        if (!isset($fields['amount']['value'], $fields['amount']['currency'])) {
            throw new \InvalidArgumentException('A seeded payment needs an amount.');
        }
        $state = $this->state();
        $now = $this->now($state);
        $status = (string) ($fields['status'] ?? 'open');
        $payment = array_merge([
            'id' => 'tr_fake' . $this->nextSequence($state),
            'mode' => 'live',
            'method' => 'ideal',
            'description' => 'Seeded payment',
            'metadata' => null,
            'redirectUrl' => 'https://shop.example/checkout/order-received/',
            'webhookUrl' => 'https://shop.example/wp-json/mollie/v1/webhook',
            'createdAt' => gmdate('c', $now),
            'paidAt' => $status === 'paid' ? gmdate('c', $now) : null,
        ], $fields, ['status' => $status]);
        $state['payments'][$payment['id']] = $payment;
        $this->store->save($state);

        return $payment;
    }

    /**
     * An order the plugin created at checkout through the Orders API, with its one payment.
     *
     * @param array<string, mixed> $fields amount is required; id, mode (live), status (created),
     *                                     method (klarna), metadata have defaults. paymentStatus
     *                                     is the status of the embedded payment (open).
     * @return array<string, mixed> The order as stored, with paymentId.
     */
    public function seedOrder(array $fields): array
    {
        if (!isset($fields['amount']['value'], $fields['amount']['currency'])) {
            throw new \InvalidArgumentException('A seeded order needs an amount.');
        }
        $paymentStatus = (string) ($fields['paymentStatus'] ?? 'open');
        unset($fields['paymentStatus']);

        $state = $this->state();
        $now = $this->now($state);
        $order = array_merge([
            'id' => 'ord_fake' . $this->nextSequence($state),
            'mode' => 'live',
            'status' => 'created',
            'method' => 'klarna',
            'metadata' => null,
            'orderNumber' => '1',
            'lines' => [],
            'redirectUrl' => 'https://shop.example/checkout/order-received/',
            'webhookUrl' => 'https://shop.example/wp-json/mollie/v1/webhook',
            'createdAt' => gmdate('c', $now),
        ], $fields);
        $paymentId = 'tr_fake' . $this->nextSequence($state);
        $order['paymentId'] = $paymentId;
        $state['orders'][$order['id']] = $order;
        $state['payments'][$paymentId] = [
            'id' => $paymentId,
            'orderId' => $order['id'],
            'mode' => $order['mode'],
            'status' => $paymentStatus,
            'method' => $order['method'],
            'amount' => $order['amount'],
            'description' => 'Order ' . $order['orderNumber'],
            'metadata' => $order['metadata'],
            'redirectUrl' => $order['redirectUrl'],
            'webhookUrl' => $order['webhookUrl'],
            'createdAt' => gmdate('c', $now),
        ];
        $this->store->save($state);

        return $order;
    }

    /**
     * @param array<string, mixed> $extra Fields to overwrite on the stored order.
     */
    public function setOrderStatus(string $orderId, string $status, array $extra = []): void
    {
        $state = $this->state();
        if (!isset($state['orders'][$orderId])) {
            throw new \InvalidArgumentException("The fake Mollie has no order {$orderId}.");
        }
        $state['orders'][$orderId] = array_merge($state['orders'][$orderId], $extra, ['status' => $status]);
        $this->store->save($state);
    }

    /**
     * A shopper Mollie knows, as after a first payment at the checkout.
     *
     * @param array<string, string> $mandates Method => status of each mandate the customer has.
     * @return array{id: string, mandates: array<string, string>} The customer id and, per method, the mandate id.
     */
    public function seedCustomer(array $mandates = [], string $mode = 'live'): array
    {
        $state = $this->state();
        $id = 'cst_fake' . $this->nextSequence($state);
        $state['customers'][$id] = ['id' => $id, 'mode' => $mode, 'name' => 'Seeded customer', 'email' => 'shopper@example.org'];
        $mandateIds = [];
        foreach ($mandates as $method => $status) {
            $mandateIds[$method] = $this->storeMandate($state, $id, $method, $status)['id'];
        }
        $this->store->save($state);

        return ['id' => $id, 'mandates' => $mandateIds];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function captures(): array
    {
        return $this->state()['captures'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function shipments(): array
    {
        return $this->state()['shipments'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function orders(): array
    {
        return $this->state()['orders'];
    }

    /**
     * A refund made in the Mollie dashboard: the plugin never asked for it.
     *
     * @return array<string, mixed> The refund as stored.
     */
    public function refundFromDashboard(string $paymentId, string $value): array
    {
        $state = $this->state();
        if (!isset($state['payments'][$paymentId])) {
            throw new \InvalidArgumentException("The fake Mollie has no payment {$paymentId}.");
        }
        $id = 're_fake' . $this->nextSequence($state);
        $state['refunds'][$id] = [
            'resource' => 'refund',
            'id' => $id,
            'paymentId' => $paymentId,
            'amount' => ['value' => $value, 'currency' => $state['payments'][$paymentId]['amount']['currency']],
            'status' => 'refunded',
            'description' => '',
            'metadata' => null,
            'createdAt' => gmdate('c', $this->now($state)),
        ];
        $this->store->save($state);

        return $state['refunds'][$id];
    }

    /**
     * The shopper's bank took the money back.
     *
     * @return array<string, mixed> The chargeback as stored.
     */
    public function chargeBack(string $paymentId, string $value): array
    {
        $state = $this->state();
        if (!isset($state['payments'][$paymentId])) {
            throw new \InvalidArgumentException("The fake Mollie has no payment {$paymentId}.");
        }
        $id = 'chb_fake' . $this->nextSequence($state);
        $state['chargebacks'][$id] = [
            'resource' => 'chargeback',
            'id' => $id,
            'paymentId' => $paymentId,
            'amount' => ['value' => $value, 'currency' => $state['payments'][$paymentId]['amount']['currency']],
            'createdAt' => gmdate('c', $this->now($state)),
        ];
        $this->store->save($state);

        return $state['chargebacks'][$id];
    }

    /**
     * @param int|null $expiredAt When Mollie says the session expired; by default a lifetime after
     *                            it was created.
     */
    public function expireSession(string $sessionId, ?int $expiredAt = null): void
    {
        $state = $this->state();
        if (!isset($state['sessions'][$sessionId])) {
            throw new \InvalidArgumentException("The fake Mollie has no session {$sessionId}.");
        }
        $state['sessions'][$sessionId]['status'] = 'expired';
        if ($expiredAt !== null) {
            $state['sessions'][$sessionId]['expiresAt'] = gmdate('c', $expiredAt);
        }
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
     * What Mollie offers a method for. Without a rule a method is offered for every request, as before.
     *
     * @param array{min?: string, max?: string, currencies?: array<int, string>, countries?: array<int, string>, sequenceTypes?: array<int, string>} $rule
     */
    public function offerMethodOnlyFor(string $methodId, array $rule): void
    {
        $state = $this->state();
        $state['methodRules'][$methodId] = $rule;
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
        $chargebacks = $this->chargebacksOf($state, $payment['id']);

        $captured = array_sum(array_map(static function (array $capture): float {
            return (float) $capture['amount']['value'];
        }, array_filter($state['captures'], static function (array $capture) use ($payment): bool {
            return $capture['paymentId'] === $payment['id'];
        })));

        $resource = array_merge(['sequenceType' => 'oneoff', 'locale' => 'en_US'], $payment, [
            'resource' => 'payment',
            'profileId' => 'pfl_fake',
            'isCancelable' => $payment['status'] === 'authorized',
            'amountCaptured' => $money($payment['status'] === 'paid' ? (float) $payment['amount']['value'] : $captured),
            'amountRefunded' => $money($refunded),
            'amountRemaining' => $money(max(0.0, (float) $payment['amount']['value'] - $refunded)),
            '_links' => [
                'self' => ['href' => $base, 'type' => 'application/hal+json'],
                'dashboard' => ['href' => 'https://www.mollie.com/dashboard/payments/' . $payment['id'], 'type' => 'text/html'],
            ],
        ]);
        if ($payment['status'] === 'open') {
            $resource['_links']['checkout'] = ['href' => 'https://www.mollie.com/checkout/select-method/' . $payment['id'], 'type' => 'text/html'];
        }
        if ($refunds) {
            $resource['_links']['refunds'] = ['href' => $base . '/refunds', 'type' => 'application/hal+json'];
        }
        if ($chargebacks) {
            $resource['amountChargedBack'] = $money(array_sum(array_map(static function (array $chargeback): float {
                return (float) $chargeback['amount']['value'];
            }, $chargebacks)));
            $resource['_links']['chargebacks'] = ['href' => $base . '/chargebacks', 'type' => 'application/hal+json'];
        }

        return array_filter($resource, static function ($value): bool {
            return $value !== null;
        });
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function orderResource(array $state, array $order, string $embed): array
    {
        $payment = $state['payments'][$order['paymentId']];
        $refunds = $this->refundsOf($state, $order['paymentId']);
        $refunded = array_sum(array_map(static function (array $refund): float {
            return (float) $refund['amount']['value'];
        }, $refunds));
        $base = 'https://api.mollie.com/v2/orders/' . $order['id'];

        $resource = array_merge($order, [
            'resource' => 'order',
            'profileId' => 'pfl_fake',
            'locale' => 'en_US',
            'isCancelable' => in_array($order['status'], ['created', 'authorized', 'shipping'], true),
            '_links' => [
                'self' => ['href' => $base, 'type' => 'application/hal+json'],
                'dashboard' => ['href' => 'https://www.mollie.com/dashboard/orders/' . $order['id'], 'type' => 'text/html'],
            ],
        ]);
        unset($resource['paymentId']);
        if ($refunded > 0) {
            $resource['amountRefunded'] = [
                'value' => number_format($refunded, 2, '.', ''),
                'currency' => $order['amount']['currency'],
            ];
        }
        $embeds = array_filter(explode(',', $embed));
        if (in_array('payments', $embeds, true)) {
            $resource['_embedded']['payments'] = [$this->paymentResource($state, $payment)];
        }
        if (in_array('refunds', $embeds, true) && $refunds) {
            $resource['_embedded']['refunds'] = array_map(static function (array $refund) use ($order): array {
                return array_merge($refund, ['orderId' => $order['id']]);
            }, $refunds);
        }

        return array_filter($resource, static function ($value): bool {
            return $value !== null;
        });
    }

    /**
     * @param array<string, mixed> $customer
     * @return array<string, mixed>
     */
    private function customerResource(array $customer): array
    {
        $base = 'https://api.mollie.com/v2/customers/' . $customer['id'];

        return array_merge($customer, [
            'resource' => 'customer',
            'locale' => 'en_US',
            'metadata' => null,
            'createdAt' => gmdate('c', 1790000000),
            '_links' => [
                'self' => ['href' => $base, 'type' => 'application/hal+json'],
                'mandates' => ['href' => $base . '/mandates', 'type' => 'application/hal+json'],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $state
     * @return array<int, array<string, mixed>>
     */
    private function mandatesOf(array $state, string $customerId): array
    {
        return array_values(array_filter($state['mandates'], static function (array $mandate) use ($customerId): bool {
            return $mandate['customerId'] === $customerId;
        }));
    }

    /**
     * @param array<string, mixed> $state
     * @return array<int, array<string, mixed>>
     */
    private function chargebacksOf(array $state, string $paymentId): array
    {
        return array_values(array_filter($state['chargebacks'], static function (array $chargeback) use ($paymentId): bool {
            return $chargeback['paymentId'] === $paymentId;
        }));
    }

    /**
     * GET /v2/methods answers for the amount, currency, billing country and sequence type asked about.
     *
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $query
     */
    private function methodIsOfferedFor(array $rule, array $query): bool
    {
        $amount = isset($query['amount']['value']) ? (float) $query['amount']['value'] : null;
        $currency = isset($query['amount']['currency']) ? (string) $query['amount']['currency'] : null;
        $country = isset($query['billingCountry']) ? (string) $query['billingCountry'] : null;
        $sequenceType = (string) ($query['sequenceType'] ?? 'oneoff');

        if ($amount !== null && isset($rule['min']) && $amount < (float) $rule['min']) {
            return false;
        }
        if ($amount !== null && isset($rule['max']) && $amount > (float) $rule['max']) {
            return false;
        }
        if ($currency !== null && isset($rule['currencies']) && !in_array($currency, $rule['currencies'], true)) {
            return false;
        }
        if ($country !== null && isset($rule['countries']) && !in_array($country, $rule['countries'], true)) {
            return false;
        }

        return !isset($rule['sequenceTypes']) || in_array($sequenceType, $rule['sequenceTypes'], true);
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
            'orders' => [],
            'refunds' => [],
            'chargebacks' => [],
            'captures' => [],
            'shipments' => [],
            'customers' => [],
            'mandates' => [],
            'requests' => [],
            'idempotency' => [],
            'failures' => [],
            'methods' => ['ideal', 'creditcard', 'banktransfer', 'paypal', 'applepay'],
            'methodRules' => [],
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
