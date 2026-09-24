<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent\Harness;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\FakeMollieApi;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\SessionRules;

/**
 * Proves the Express Component harness itself, before any feature code exists.
 *
 * Every feature test will lean on these properties — that the real SDK talks to the fake without a
 * key or a network, that the fake is as strict as the Sessions documentation, that a payment the
 * plugin never created can be driven through the real webhook route, and that the leak detector
 * actually detects. A harness that silently did less would make the feature tests pass for the
 * wrong reason, so they are pinned here.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressHarness
 */
class ExpressHarnessTest extends ExpressFlowTestCase
{
    /**
     * Scenario: the real SDK reaches the fake Mollie with no key and no network
     *   Given the plugin is booted with the harness
     *   When a Checkout Session is created through the plugin's own API client
     *   Then the fake answers with a session that keeps its clientAccessToken
     *   And the request was authenticated as live with the harness's fake key
     *
     * @test
     */
    public function it_answers_the_real_sdk_without_a_key_or_a_network(): void
    {
        $client = $this->apiClient();

        $session = $client->performHttpCall('POST', 'sessions', (string) wp_json_encode($this->validSessionPayload()));

        $this->assertSame('session', $session->resource);
        $this->assertStringStartsWith('sess_', $session->id);
        $this->assertSame('open', $session->status);
        $this->assertSame('live', $session->mode);
        $this->assertNotEmpty($session->clientAccessToken, 'The raw call must keep clientAccessToken.');
        $this->assertSame($session->id, FakeMollieApi::sessionIdFromClientAccessToken($session->clientAccessToken));
        $this->assertObjectNotHasAttribute(
            'expiresAt',
            $session,
            'Like the real API, an open session carries no expiry; the plugin derives it from createdAt.'
        );

        $requests = $this->fakeMollie()->requests('POST', 'sessions');
        $this->assertCount(1, $requests);
        $this->assertSame('live', $requests[0]['authorizedAs']);
        $this->assertSame([$this->validSessionPayload()], $this->sessionPayloads());
    }

    /**
     * Scenario: the session lifetime is a known value when the clock is pinned
     *
     * @test
     */
    public function it_issues_sessions_that_expire_fifteen_minutes_after_a_pinned_clock(): void
    {
        $this->fakeMollie()->setNow(1790000000);

        $session = $this->apiClient()->performHttpCall('POST', 'sessions', (string) wp_json_encode($this->validSessionPayload()));

        $this->assertSame(gmdate('c', 1790000000), $session->createdAt);

        $this->fakeMollie()->expireSession($session->id);
        $expired = $this->apiClient()->performHttpCall('GET', 'sessions/' . $session->id);

        $this->assertSame('expired', $expired->status);
        $this->assertSame(gmdate('c', 1790000000 + FakeMollieApi::SESSION_LIFETIME_SECONDS), $expired->expiredAt);
    }

    /**
     * Scenario: a payload that breaks the documented Sessions rules is refused like Mollie would
     *   Given a payload with one documented rule broken
     *   When it is sent through the real client
     *   Then an ApiException with status 422 surfaces, shaped by the real HTTP adapter
     *
     * @test
     * @dataProvider invalidSessionPayloads
     * @param array<string, mixed> $payload
     */
    public function it_refuses_a_payload_that_breaks_the_documented_rules(array $payload, string $expectedField): void
    {
        $this->assertSame($expectedField, SessionRules::violations($payload)[0]['field'] ?? null);

        try {
            $this->apiClient()->performHttpCall('POST', 'sessions', (string) wp_json_encode($payload));
            $this->fail('The fake must refuse the payload with a 422.');
        } catch (ApiException $exception) {
            $this->assertSame(422, $exception->getCode());
            $this->assertStringContainsString('Error executing API call (422: Unprocessable Entity)', $exception->getMessage());
        }

        $this->assertSame([], $this->fakeMollie()->sessions(), 'A refused payload must not create a session.');
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public function invalidSessionPayloads(): array
    {
        $valid = $this->validSessionPayload();

        $unbalanced = $valid;
        $unbalanced['amount']['value'] = '20.01';

        $floatVatRate = $valid;
        $floatVatRate['lines'][0]['vatRate'] = 21.0;

        $wrongVatAmount = $valid;
        $wrongVatAmount['lines'][0]['vatAmount']['value'] = '3.48';

        $wrongLineTotal = $valid;
        $wrongLineTotal['lines'][0]['totalAmount']['value'] = '19.99';

        $positiveDiscount = $valid;
        $positiveDiscount['lines'][] = [
            'type' => 'discount',
            'description' => 'Coupon',
            'quantity' => 1,
            'unitPrice' => ['currency' => 'EUR', 'value' => '5.00'],
            'totalAmount' => ['currency' => 'EUR', 'value' => '5.00'],
        ];
        $positiveDiscount['amount']['value'] = '25.00';

        $withProfileId = $valid;
        $withProfileId['profileId'] = 'pfl_notallowed';

        $missingLines = $valid;
        unset($missingLines['lines']);

        return [
            'line totals do not sum to the amount' => [$unbalanced, 'amount'],
            'vatRate is a float, not a string' => [$floatVatRate, 'lines.0.vatRate'],
            'vatAmount is off by a cent' => [$wrongVatAmount, 'lines.0.vatAmount'],
            'totalAmount is not unitPrice x quantity' => [$wrongLineTotal, 'lines.0.totalAmount'],
            'discount line with a positive unit price' => [$positiveDiscount, 'lines.1.unitPrice'],
            'profileId sent with an API key' => [$withProfileId, 'profileId'],
            'lines are missing' => [$missingLines, 'lines'],
        ];
    }

    /**
     * Scenario: a repeated idempotency key returns the first session instead of a second one
     *
     * @test
     */
    public function it_replays_a_repeated_idempotency_key(): void
    {
        $client = $this->apiClient();
        $body = (string) wp_json_encode($this->validSessionPayload());

        $client->setIdempotencyKey('express-session-order-1-attempt-1');
        $first = $client->performHttpCall('POST', 'sessions', $body);
        $client->setIdempotencyKey('express-session-order-1-attempt-1');
        $second = $client->performHttpCall('POST', 'sessions', $body);

        $this->assertSame($first->id, $second->id);
        $this->assertCount(1, $this->fakeMollie()->sessions());

        $requests = $this->fakeMollie()->requests('POST', 'sessions');
        $this->assertSame('express-session-order-1-attempt-1', $requests[0]['idempotencyKey']);
        $this->assertFalse($requests[0]['replayed']);
        $this->assertTrue($requests[1]['replayed']);
    }

    /**
     * Scenario: the same idempotency key with a different body is refused
     *   Given a session already created with a key
     *   When the same key is sent with a different body
     *   Then Mollie answers 400, which the plugin classifies as an outage and not as a refusal
     *
     * @test
     */
    public function it_refuses_an_idempotency_key_reused_for_a_different_request(): void
    {
        $client = $this->apiClient();
        $changed = $this->validSessionPayload();
        $changed['description'] = 'Order 2';

        $client->setIdempotencyKey('same-key');
        $client->performHttpCall('POST', 'sessions', (string) wp_json_encode($this->validSessionPayload()));

        $this->expectException(ApiException::class);
        // 400, as the real API answers it; a payload Mollie refuses is 422 instead.
        $this->expectExceptionCode(400);
        $client->setIdempotencyKey('same-key');
        $client->performHttpCall('POST', 'sessions', (string) wp_json_encode($changed));
    }

    /**
     * Scenario: the payment Mollie creates for a session carries what the plugin put on the session
     *   Given a session created with metadata and a webhook URL
     *   When the shopper authorises in the wallet
     *   Then the payment read back through the SDK has the session's metadata and addresses
     *
     * @test
     */
    public function it_creates_a_payment_that_inherits_the_session_metadata(): void
    {
        $client = $this->apiClient();
        $session = $client->performHttpCall('POST', 'sessions', (string) wp_json_encode($this->validSessionPayload()));

        $created = $this->fakeMollie()->completeSession($session->id, [
            'status' => 'paid',
            'method' => 'applepay',
            'billingAddress' => CanaryData::mollieAddress(),
            'shippingAddress' => CanaryData::mollieAddress(['city' => 'Rotterdam']),
        ]);
        $payment = $client->payments->get($created['id']);

        $this->assertTrue($payment->isPaid());
        $this->assertSame('applepay', $payment->method);
        $this->assertSame(4711, $payment->metadata->order_id);
        $this->assertSame('20.00', $payment->amount->value);
        $this->assertSame(CanaryData::EMAIL, $payment->billingAddress->email);
        $this->assertSame('Rotterdam', $payment->shippingAddress->city);
        $this->assertSame('Noord-Holland', $payment->shippingAddress->region);
        $this->assertSame('https://shop.example/wp-json/mollie/v1/webhook', $payment->webhookUrl);

        $this->assertSame('completed', $client->performHttpCall('GET', 'sessions/' . $session->id)->status);
    }

    /**
     * Scenario: a simulated outage surfaces once and then clears
     *
     * @test
     */
    public function it_simulates_a_failure_for_the_next_matching_call_only(): void
    {
        $client = $this->apiClient();
        $body = (string) wp_json_encode($this->validSessionPayload());
        $this->fakeMollie()->failNext('POST', 'sessions', 503, 'Mollie is having a bad day.');

        try {
            $client->performHttpCall('POST', 'sessions', $body);
            $this->fail('The first call must fail.');
        } catch (ApiException $exception) {
            $this->assertSame(503, $exception->getCode());
        }

        $this->assertStringStartsWith('sess_', $client->performHttpCall('POST', 'sessions', $body)->id);
    }

    /**
     * Scenario: anything on the Mollie host that is not faked is refused, never let through
     *
     * @test
     */
    public function it_never_lets_an_unfaked_mollie_call_through(): void
    {
        $this->bootExpress();

        $response = wp_remote_post('https://api.mollie.com/v2/terminals', [
            'headers' => ['Authorization' => 'Bearer ' . CanaryData::LIVE_API_KEY],
            'body' => '{}',
        ]);

        $this->assertSame(501, wp_remote_retrieve_response_code($response));
    }

    /**
     * Scenario: a payment the plugin never created is driven through the real webhook route
     *   Given a pending order that knows a payment id
     *   And Mollie holds that payment as paid
     *   When Mollie calls the webhook with the secret
     *   Then the order is paid and Mollie is answered 200
     *   And nothing marked secret or personal reached the log
     *
     * This is the rail every express payment will arrive on.
     *
     * @test
     */
    public function it_drives_a_fake_payment_through_the_real_webhook_route(): void
    {
        $this->bootExpress();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');

        $payload = $this->validSessionPayload();
        $payload['metadata'] = ['order_id' => $order->get_id()];
        $payload['amount']['value'] = $this->formattedTotal($order);
        $payload['lines'] = [[
            'description' => 'Everything',
            'quantity' => 1,
            'unitPrice' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'totalAmount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
        ]];
        $session = $this->apiClient()->performHttpCall('POST', 'sessions', (string) wp_json_encode($payload));
        $payment = $this->fakeMollie()->completeSession($session->id, [
            'status' => 'paid',
            'method' => 'ideal',
            'billingAddress' => CanaryData::mollieAddress(),
        ]);

        $order->update_meta_data('_mollie_payment_id', $payment['id']);
        $order->set_transaction_id($payment['id']);
        $order->save();

        $status = $this->deliverWebhook($payment['id']);

        $this->assertSame(200, $status);
        $this->assertTrue(wc_get_order($order->get_id())->is_paid(), 'The webhook must have completed the payment.');
        $this->assertNothingLeakedToLog();
    }

    /**
     * Scenario: an unauthenticated webhook is rejected before anything is looked up at Mollie
     *
     * @test
     */
    public function it_reports_the_status_code_of_a_rejected_webhook(): void
    {
        $this->bootExpress();

        $status = $this->deliverWebhook('tr_unknownToAnyOrder', false);

        $this->assertSame(401, $status);
        $this->assertSame([], $this->fakeMollie()->requests('GET', 'payments'));
    }

    /**
     * Scenario: the gateways register from the fake's methods list
     *
     * @test
     */
    public function it_registers_gateways_from_the_fake_methods_list(): void
    {
        $this->bootExpress();

        $gateways = WC()->payment_gateways()->payment_gateways();

        $this->assertArrayHasKey('mollie_wc_gateway_ideal', $gateways);
        $this->assertArrayHasKey('mollie_wc_gateway_applepay', $gateways);
        $this->assertArrayHasKey('mollie_wc_gateway_paypal', $gateways);
    }

    /**
     * Scenario: a scenario can start from a real cart
     *
     * @test
     */
    public function it_gives_a_scenario_a_real_cart(): void
    {
        $this->actAsGuest();

        $cart = $this->cartWith(['simple'], 2);

        $this->assertSame(2, $cart->get_cart_contents_count());
        $this->assertGreaterThan(0, (float) $cart->get_total('edit'));
    }

    /**
     * Scenario: the leak detector detects
     *   Given a log line carrying a marked email and one carrying the webhook secret
     *   Then the detector reports a leak
     *   And it reports none for a line carrying ids only
     *
     * @test
     */
    public function it_detects_a_marked_secret_or_personal_detail(): void
    {
        $this->assertFalse(CanaryData::leakedIn('webhook.admitted order=12 mollie_id=tr_abc status=paid'));
        $this->assertTrue(CanaryData::leakedIn('billing email ' . CanaryData::EMAIL));
        $this->assertTrue(CanaryData::leakedIn('https://shop.example/webhook?mollie_webhook_secret=' . CanaryData::WEBHOOK_SECRET));
        $this->assertTrue(CanaryData::leakedIn('Authorization: Bearer ' . CanaryData::LIVE_API_KEY));
        $this->assertTrue(CanaryData::leakedIn((string) wp_json_encode(CanaryData::mollieAddress())));

        $this->logger()->debug('ids only', ['order' => 12]);
        $this->assertNothingLeakedToLog();
    }

    private function apiClient(): MollieApiClient
    {
        $container = $this->bootExpress();

        return $container->get('SDK.api_helper')->getApiClient(CanaryData::LIVE_API_KEY);
    }

    /**
     * The acceptance-criteria example of the session-client spec: quantity 2, unit price 10.00
     * including 21% VAT, so totalAmount 20.00 and vatAmount 3.47.
     *
     * @return array<string, mixed>
     */
    private function validSessionPayload(): array
    {
        return [
            'amount' => ['currency' => 'EUR', 'value' => '20.00'],
            'description' => 'Order 4711',
            'lines' => [[
                'type' => 'physical',
                'description' => 'Test Simple Product',
                'quantity' => 2,
                'unitPrice' => ['currency' => 'EUR', 'value' => '10.00'],
                'totalAmount' => ['currency' => 'EUR', 'value' => '20.00'],
                'vatRate' => '21.00',
                'vatAmount' => ['currency' => 'EUR', 'value' => '3.47'],
            ]],
            'redirectUrl' => 'https://shop.example/checkout/order-received/',
            'requiredCustomerDetails' => ['email', 'billing-address', 'shipping-address'],
            'payment' => ['webhookUrl' => 'https://shop.example/wp-json/mollie/v1/webhook'],
            'metadata' => ['order_id' => 4711],
        ];
    }
}
