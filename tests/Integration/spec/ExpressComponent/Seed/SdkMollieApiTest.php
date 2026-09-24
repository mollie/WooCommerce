<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent\Seed;

use Mollie\WooCommerce\Adapter\Mollie\MollieApi;
use Mollie\WooCommerce\Core\Security\IdempotencyKey;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\FakeMollieApi;

/**
 * The one adapter that may call a mutating Mollie endpoint (blueprint chokepoint 2, ADR-012/013).
 *
 * Two properties are what make it a chokepoint rather than a wrapper. It sets the deterministic
 * idempotency key on the client before the call, so a retried intent — a shopper who resubmits
 * after a timeout, a scheduler that runs twice — gets the first session back instead of creating
 * a second one. And it sends the session as a raw call: SDK v2.79's typed sessions endpoint drops
 * clientAccessToken, and without that token mollie.js cannot render the wallet buttons at all.
 *
 * Both are observed at the HTTP layer, through the real SDK and the real WordPress HTTP adapter,
 * because that is where the header and the dropped field actually happen.
 *
 * @covers \Mollie\WooCommerce\Adapter\Mollie\SdkMollieApi
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
     * The real API omits every expiry field while a session is open and sends expiredAt only once
     * it has expired (seen live, 2026-09-23). Without this the workflow read an empty expiry as a
     * failed call and answered every shopper with mollie_unavailable.
     *
     * @test
     */
    public function it_derives_the_expiry_from_created_at_when_mollie_names_none(): void
    {
        $this->fakeMollie()->setNow(1790000000);
        $api = $this->expressApi();

        $session = $api->createSession($this->sessionPayload(), IdempotencyKey::for('express.session.v1', ['attempt' => 1]));

        $raw = $session->raw();
        $this->assertNotNull($raw);
        $this->assertObjectNotHasAttribute('expiresAt', $raw, 'The fake must answer like the real API.');
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
