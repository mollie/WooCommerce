<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent\Seed;

use Mollie\WooCommerce\SDK\MollieApi;
use Mollie\WooCommerce\SDK\IdempotencyKey;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\FakeMollieApi;

/**
 * SdkMollieApi sessions: idempotency key header, raw call keeping clientAccessToken, expiry.
 * Observed at the HTTP layer, where the header and the dropped field actually happen.
 *
 * @covers \Mollie\WooCommerce\SDK\SdkMollieApi
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressSeed
 */
class SdkMollieApiTest extends ExpressFlowTestCase
{
    /** The lifetime config/express.php gives the adapter, and the one the fake uses. */
    private const SESSION_LIFETIME_SECONDS = FakeMollieApi::SESSION_LIFETIME_SECONDS;


    /**
     * Scenario: the session is sent as a raw call, carrying the key it was given
     *   Given a Checkout Session payload and a deterministic idempotency key
     *   When the adapter creates the session
     *   Then Mollie received exactly that payload with that key in the Idempotency-Key header
     *   And the session it returns still carries its clientAccessToken
     *   And nothing marked secret or personal reached the log
     *
     * @test
     */
    public function it_sends_the_session_as_a_raw_call_with_the_idempotency_key_it_was_given(): void
    {
        $api = $this->expressApi();
        $payload = $this->sessionPayload();
        $key = IdempotencyKey::for('express.session.v1', ['order' => 4711, 'attempt' => 1]);

        $session = $api->createSession($payload, $key);

        $requests = $this->fakeMollie()->requests('POST', 'sessions');
        $this->assertCount(1, $requests, 'The adapter must send exactly one session request.');
        $this->assertSame($key, $requests[0]['idempotencyKey'], 'The key the adapter was given must be the header.');
        $this->assertSame('live', $requests[0]['authorizedAs'], 'The adapter resolves the key itself.');
        $this->assertSame([$payload], $this->sessionPayloads(), 'The payload must reach Mollie unchanged.');
        $this->assertValidSessionPayload($this->sessionPayloads()[0]);

        $this->assertStringStartsWith('sess_', $session->id());
        $this->assertNotEmpty(
            $session->clientAccessToken(),
            "The typed sessions endpoint drops clientAccessToken, so an empty one means \$client->sessions was used."
        );
        $this->assertSame(
            $session->id(),
            FakeMollieApi::sessionIdFromClientAccessToken($session->clientAccessToken()),
            'The token must belong to the session that was returned.'
        );

        $this->assertNothingLeakedToLog();
    }

    /**
     * Scenario: the same intent sent twice creates one session
     *   Given a session already created with a key
     *   When the identical payload is sent again with the identical key
     *   Then Mollie replays the first answer instead of opening a second session
     *   And the adapter returns the same session id
     *
     * @test
     */
    public function it_creates_one_session_for_two_calls_with_the_same_key_and_payload(): void
    {
        $api = $this->expressApi();
        $payload = $this->sessionPayload();
        $key = IdempotencyKey::for('express.session.v1', ['order' => 4711, 'attempt' => 1]);

        $first = $api->createSession($payload, $key);
        $second = $api->createSession($payload, $key);

        $this->assertSame($first->id(), $second->id(), 'A repeated intent must return the first session.');
        $this->assertCount(1, $this->fakeMollie()->sessions(), 'Mollie must hold one session, not two.');

        $requests = $this->fakeMollie()->requests('POST', 'sessions');
        $this->assertCount(2, $requests);
        $this->assertFalse($requests[0]['replayed']);
        $this->assertTrue($requests[1]['replayed'], 'The second call must have been replayed by Mollie.');

        $this->assertNothingLeakedToLog();
    }

    /**
     * Scenario: an open session's expiry is derived from createdAt
     *   Given Mollie answers an open session without any expiry field, as the real API does
     *   When the adapter creates the session
     *   Then its expiry is createdAt plus the configured session lifetime
     *   And a session the store remembers can therefore be handed out again until then
     *
     * The real API sends no expiry field while a session is open, only expiredAt once expired.
     *
     * @test
     */
    public function it_derives_the_expiry_from_created_at_when_mollie_names_none(): void
    {
        $this->fakeMollie()->setNow(1790000000);
        $api = $this->expressApi();

        $session = $api->createSession($this->sessionPayload(), IdempotencyKey::for('express.session.v1', ['attempt' => 1]));

        $answer = $this->fakeMollie()->handle('GET', 'sessions/' . $session->id(), ['Authorization' => 'Bearer ' . CanaryData::LIVE_API_KEY], null);
        $this->assertSame(200, $answer['status']);
        $this->assertArrayNotHasKey('expiresAt', (array) $answer['body'], 'The fake must answer like the real API.');
        $this->assertSame(
            gmdate('c', 1790000000 + self::SESSION_LIFETIME_SECONDS),
            $session->expiresAt(),
            'An open session with no expiry of its own expires a lifetime after it was created.'
        );
    }

    /**
     * Scenario: an expiry Mollie does name is the one that counts
     *   Given Mollie answers a session that carries expiredAt
     *   When the adapter reads it
     *   Then that value is used instead of one derived from createdAt
     *
     * @test
     */
    public function it_prefers_the_expiry_mollie_names(): void
    {
        $this->fakeMollie()->setNow(1790000000);
        $api = $this->expressApi();
        $created = $api->createSession($this->sessionPayload(), IdempotencyKey::for('express.session.v1', ['attempt' => 1]));

        $this->fakeMollie()->expireSession($created->id());
        $session = $api->session($created->id());

        $this->assertSame('expired', $session->status());
        $this->assertSame(
            gmdate('c', 1790000000 + FakeMollieApi::SESSION_LIFETIME_SECONDS),
            $session->expiresAt(),
            'The expiredAt of an expired session is not replaced by a derived value.'
        );
    }

    /**
     * Scenario: any id with the documented prefix is asked for, and stays one path segment
     *   Given ids that keep the documented sess_ or tr_ prefix, followed by more than letters and digits
     *   When the adapter reads the session or the payment
     *   Then exactly one request reaches Mollie, for that id encoded as one segment of its own path
     *
     * Mollie documents ^sess_.+$ and ^tr_.+$, nothing narrower.
     *
     * @test
     * @dataProvider idsWithTheDocumentedPrefix
     */
    public function it_asks_mollie_for_any_id_with_the_documented_prefix_inside_its_own_path_segment(string $resource, string $id): void
    {
        $api = $this->expressApi();

        try {
            $resource === 'sessions' ? $api->session($id) : $api->payment($id);
        } catch (\InvalidArgumentException $refused) {
            $this->fail("An id with the documented prefix must reach Mollie: {$id}");
        } catch (\Throwable $unknownToTheFake) {
            // The fake knows no such id; only the request matters here.
        }

        $requests = array_merge($this->fakeMollie()->requests('GET', 'sessions'), $this->fakeMollie()->requests('GET', 'payments'));
        $this->assertCount(1, $requests);
        $this->assertSame($resource . '/' . rawurlencode($id), $requests[0]['path']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function idsWithTheDocumentedPrefix(): array
    {
        return [
            'session id with a hyphen' => ['sessions', 'sess_fake-0001'],
            'session id that climbs the path' => ['sessions', 'sess_x/../payments/tr_1'],
            'session id with a query' => ['sessions', 'sess_x?include=details'],
            'payment id with an underscore' => ['payments', 'tr_fake_0001'],
            'payment id that climbs the path' => ['payments', 'tr_x/../../sessions'],
        ];
    }

    /**
     * Scenario: an id without the documented prefix never reaches Mollie
     *   Given an id that does not start with the resource's prefix, or is nothing after it
     *   When the adapter is asked to read it
     *   Then it refuses before any request
     *
     * @test
     * @dataProvider idsWithoutTheDocumentedPrefix
     */
    public function it_refuses_an_id_without_the_documented_prefix_before_asking_mollie(string $resource, string $id): void
    {
        $api = $this->expressApi();

        try {
            $resource === 'sessions' ? $api->session($id) : $api->payment($id);
            $this->fail("The adapter must refuse {$id}.");
        } catch (\InvalidArgumentException $refused) {
            $this->assertSame([], $this->fakeMollie()->requests('GET', 'sessions'));
            $this->assertSame([], $this->fakeMollie()->requests('GET', 'payments'));
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function idsWithoutTheDocumentedPrefix(): array
    {
        return [
            'a payment id as session' => ['sessions', 'tr_fake0001'],
            'the bare session prefix' => ['sessions', 'sess_'],
            'an Orders API id as payment' => ['payments', 'ord_fake0001'],
            'the bare payment prefix' => ['payments', 'tr_'],
            'a prefix in the middle' => ['payments', 'x_tr_fake0001'],
        ];
    }

    private function expressApi(): MollieApi
    {
        $api = $this->bootExpress()->get(MollieApi::class);
        $this->assertInstanceOf(MollieApi::class, $api);

        return $api;
    }

    /**
     * A payload the documented Sessions rules accept: quantity 2 at 10.00 including 21% VAT.
     *
     * @return array<string, mixed>
     */
    private function sessionPayload(): array
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
