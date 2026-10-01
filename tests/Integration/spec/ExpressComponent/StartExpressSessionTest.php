<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerce\Adapter\WordPress\ExpressRoutes;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\FakeMollieApi;
use WP_REST_Response;

/**
 * POST mollie/v1/express/session: the token that lets the Express Component render its buttons
 * (REQ-B2, B4, B6, G1, G2, G3, G6; AC-8 to AC-12, AC-34 to AC-37, AC-40).
 *
 * Any anonymous visitor of the checkout triggers this before tapping anything, so it is an
 * anonymous caller causing a Mollie call (blueprint S-04). What keeps that safe is observed here
 * end to end, through the real REST server, the real cart, the real SDK and HTTP adapter, with only
 * Mollie faked at the HTTP layer:
 *  - the route takes a nonce and nothing else; the amount comes from the server-side cart;
 *  - no Mollie call while the cart is ineligible or its shipping is incomplete;
 *  - one session per shopper and pricing fingerprint, reused until the price changes or it expires;
 *  - a budget of new sessions per window (WC_Rate_Limiter), and a deterministic idempotency key;
 *  - the browser gets {clientAccessToken, expiresAt} and nothing else; Mollie's text never leaves.
 *
 * The shop: live, HTTPS, PayPal the only wallet with its checkout express button on. PayPal takes
 * its address from the checkout form, so the shipping rules apply. Prices include 21% VAT; zone LU
 * has two flat rates, zone AT one, zone MT none.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressSessionClient
 */
class StartExpressSessionTest extends ExpressFlowTestCase
{
    private const ROUTE = '/mollie/v1/express/session';

    private const PAYPAL_CHECKOUT = 'mollie_paypal_button_enabled_checkout';

    private const APPLE_PAY_EXPRESS = 'mollie_apple_pay_button_enabled_express_checkout';

    private const MOLLIE_TEXT = 'MOLLIETEXT4e1d the unit price is not what we expected';

    /**
     * @var array<int, array{0: string, 1: callable, 2: int}>
     */
    private array $filters = [];

    /**
     * @var array<int, int>
     */
    private array $zoneIds = [];

    /**
     * @var array<int, int>
     */
    private array $taxRateIds = [];

    /**
     * Rate ids by name: 'standard' and 'express' ship to LU, 'austria' to AT.
     *
     * @var array<string, string>
     */
    private array $rates = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->clearExpressRateLimits();
        $this->newShopper();
        $this->useHttps();
        $this->payPalIsTheOnlyExpressWallet();
        $this->taxedShippingZones();
    }

    public function tearDown(): void
    {
        foreach ($this->filters as [$hook, $callback, $priority]) {
            remove_filter($hook, $callback, $priority);
        }
        $this->filters = [];

        // Leave no shopper behind: later test classes read the customer's location for their taxes.
        $this->newShopper();
        foreach ($this->zoneIds as $zoneId) {
            (new \WC_Shipping_Zone($zoneId))->delete();
        }
        foreach ($this->taxRateIds as $taxRateId) {
            \WC_Tax::_delete_tax_rate($taxRateId);
        }
        \WC_Cache_Helper::get_transient_version('shipping', true);
        $this->clearExpressRateLimits();

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // The answer
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a successful start answers only the token and its expiry
     *   Given a guest with a cart that ships, a complete destination and a chosen rate
     *   When the browser posts a valid nonce to the session route
     *   Then it is answered 200 with exactly clientAccessToken and expiresAt
     *   And the token is the one of the session the fake Mollie created
     *   And nothing marked secret or personal is in the response
     *   And the token is in no option or transient and in no log line
     *
     * @test
     */
    public function it_answers_only_the_token_and_expiry_for_a_valid_start(): void
    {
        $this->readyGuestCheckout();

        $response = $this->startSession();

        $this->assertSame(200, $response->get_status());
        $data = (array) $response->get_data();
        $keys = array_keys($data);
        sort($keys);
        $this->assertSame(['clientAccessToken', 'expiresAt'], $keys);
        $this->assertSame(
            array_key_first($this->fakeMollie()->sessions()),
            FakeMollieApi::sessionIdFromClientAccessToken((string) $data['clientAccessToken'])
        );
        $this->assertNotEmpty($data['expiresAt']);
        $this->assertNothingLeakedToBrowser($data);
        $this->assertSame(0, $this->optionsContaining((string) $data['clientAccessToken']), 'The token must live in the WooCommerce session only.');
        $this->assertStringNotContainsString((string) $data['clientAccessToken'], $this->logger()->dump());
    }

    /**
     * Scenario: the session carries the secret webhook URL and an express_ref only
     *   Given a guest ready to check out
     *   When a session is started
     *   Then the payload has no profileId and no testmode key and passes the documented rules
     *   And its metadata is exactly an express_ref that carries nothing of the shopper
     *   And payment.webhookUrl is the REST webhook URL carrying mollie_webhook_secret
     *   And requiredCustomerDetails does not contain shipping-address
     *
     * @test
     */
    public function it_sends_the_secret_webhook_url_and_only_an_express_ref(): void
    {
        $this->readyGuestCheckout();

        $this->assertSame(200, $this->startSession()->get_status());

        $payload = $this->onlySessionPayload();
        $this->assertValidSessionPayload($payload);
        $this->assertArrayNotHasKey('profileId', $payload);
        $this->assertArrayNotHasKey('testmode', $payload);
        $this->assertSame(['express_ref'], array_keys((array) $payload['metadata']));
        $this->assertIsString($payload['metadata']['express_ref']);
        $this->assertNotSame('', $payload['metadata']['express_ref']);
        $this->assertFalse(CanaryData::leakedIn((string) wp_json_encode($payload['metadata'])));
        $this->assertSame(CanaryData::WEBHOOK_SECRET, $this->webhookSecretIn($payload));
        $this->assertNotContains('shipping-address', $payload['requiredCustomerDetails'] ?? []);
    }

    /**
     * Scenario: a shop whose webhook secret was never generated still sends a secret webhook URL
     *   Given the mollie_webhook_secret option does not exist yet
     *   When a session is started
     *   Then payment.webhookUrl carries a non-empty mollie_webhook_secret
     *   And it is the secret the shop now stores, so the webhook will be admitted
     *
     * @test
     */
    public function it_generates_the_webhook_secret_when_none_exists_yet(): void
    {
        delete_option('mollie_webhook_secret');
        $this->readyGuestCheckout();

        $this->assertSame(200, $this->startSession()->get_status());

        $secret = $this->webhookSecretIn($this->onlySessionPayload());
        $this->assertNotSame('', $secret, 'An empty secret would make every express webhook fail authentication.');
        $this->assertSame((string) get_option('mollie_webhook_secret'), $secret);
    }

    /**
     * Scenario: the wallet is asked only for what the store does not hold for this shopper
     *   Given a shopper whose checkout form holds an email and a billing address, or does not
     *   When a session is started
     *   Then requiredCustomerDetails asks for email and billing-address only when the store lacks them
     *
     * @test
     * @dataProvider shoppers
     * @param list<string> $expected
     */
    public function it_asks_the_wallet_for_details_only_the_store_lacks(bool $loggedIn, bool $formFilled, array $expected): void
    {
        $this->bootExpress();
        $loggedIn ? $this->actAsCustomer() : $this->actAsGuest();
        WC()->customer = new \WC_Customer(get_current_user_id(), true);
        $this->cartWith(['simple']);
        $this->fillCheckoutForm($formFilled ? $this->billing() : [], $this->shipping('LU'));
        $this->chooseRate('standard');

        $this->assertSame(200, $this->startSession()->get_status());

        $details = $this->onlySessionPayload()['requiredCustomerDetails'] ?? [];
        sort($details);
        $this->assertSame($expected, $details);
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: list<string>}>
     */
    public function shoppers(): array
    {
        return [
            'a guest with an empty checkout form' => [false, false, ['billing-address', 'email']],
            'a guest who filled in the checkout form' => [false, true, []],
            'a logged-in customer whose details the store holds' => [true, true, []],
        ];
    }

    /**
     * Scenario: whatever the browser sends, the session is priced from the server-side cart
     *   Given a guest ready to check out with a chosen shipping rate
     *   When the request also carries an amount, a currency, lines, an address and a country
     *   Then it is answered 200
     *   And the amount that reached Mollie is the WooCommerce cart total including the rate
     *   And the shipping_fee line is the chosen rate's cost including its tax
     *
     * @test
     */
    public function it_prices_from_the_server_cart_whatever_the_request_sends(): void
    {
        $this->readyGuestCheckout();

        $response = $this->startSession([
            'amount' => '0.01',
            'currency' => 'USD',
            'lines' => [['description' => 'Anything', 'quantity' => 1, 'unitPrice' => '0.01']],
            'address' => ['country' => 'US', 'postalCode' => '10001'],
            'shippingAddress' => ['country' => 'US'],
            'country' => 'US',
        ]);

        $this->assertSame(200, $response->get_status());
        $payload = $this->onlySessionPayload();
        $this->assertSame(['currency' => get_woocommerce_currency(), 'value' => $this->cartTotal()], $payload['amount']);
        $shippingLines = array_values(array_filter($payload['lines'], static function (array $line): bool {
            return ($line['type'] ?? '') === 'shipping_fee';
        }));
        $this->assertCount(1, $shippingLines);
        $this->assertSame(
            $this->decimal((float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax()),
            $shippingLines[0]['totalAmount']['value']
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Admission
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a start request that did not come from the shop's own page is refused
     *   Given a guest ready to check out
     *   When the request carries no nonce, a forged one, or a nonce for another action
     *   Then it is answered 403
     *   And nothing is sent to Mollie
     *
     * @test
     * @dataProvider badNonces
     */
    public function it_refuses_a_start_without_a_valid_nonce(?string $nonceAction, ?string $literal): void
    {
        $this->readyGuestCheckout();
        $params = [];
        if ($nonceAction !== null) {
            $params['nonce'] = wp_create_nonce($nonceAction);
        } elseif ($literal !== null) {
            $params['nonce'] = $literal;
        }

        $response = $this->restRequest('POST', self::ROUTE, $params);

        $this->assertSame(403, $response->get_status());
        $this->assertSame([], $this->fakeMollie()->requests('POST'));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public function badNonces(): array
    {
        return [
            'no nonce' => [null, null],
            'a forged nonce' => [null, 'a1b2c3d4e5'],
            'a nonce for another action' => ['wp_rest', null],
        ];
    }

    /**
     * Scenario: a nonce is bound to the shopper it was issued to
     *   Given a logged-out shopper whose page was given a nonce
     *   When another logged-out shopper, with a session of their own, posts that nonce
     *   Then it is answered 403 and nothing is sent to Mollie
     *   And that other shopper's own nonce is accepted
     *
     * Without this, every guest would share one nonce and it would prove nothing about the page the
     * request came from (REQ-G3). WooCommerce binds a guest nonce to the WooCommerce session only for
     * actions whose name starts with 'woocommerce'.
     *
     * @test
     */
    public function it_refuses_a_nonce_issued_to_another_shopper(): void
    {
        $this->readyGuestCheckout();
        $nonceOfAnotherShopper = wp_create_nonce(ExpressRoutes::NONCE_ACTION);

        $this->newShopper();
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
        $response = $this->restRequest('POST', self::ROUTE, ['nonce' => $nonceOfAnotherShopper]);

        $this->assertSame(403, $response->get_status());
        $this->assertSame([], $this->fakeMollie()->requests('POST'));
        $this->assertSame(200, $this->startSession()->get_status(), 'The shopper\'s own nonce must be accepted.');
    }

    /**
     * Scenario: a logged-out shopper can start express checkout
     *   Given a logged-out shopper with a cart that ships and a complete checkout form
     *   When the browser posts the nonce the page gave it
     *   Then it is answered 200 and one session was created
     *
     * @test
     */
    public function it_starts_a_session_for_a_logged_out_shopper(): void
    {
        $this->readyGuestCheckout();
        $this->assertSame(0, get_current_user_id());

        $response = $this->startSession();

        $this->assertSame(200, $response->get_status());
        $this->assertCount(1, $this->fakeMollie()->sessions());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // No Mollie call while the cart cannot be paid
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a cart that ships waits for the checkout form
     *   Given a guest with a cart that ships
     *   And the shipping address is incomplete, or no rate can be chosen for the destination
     *   When a session is requested
     *   Then it is answered 409 with the code shipping_incomplete and a message for the shopper
     *   And nothing is sent to Mollie
     *
     * @test
     * @dataProvider incompleteShipping
     */
    public function it_refuses_a_shipping_cart_until_the_form_is_complete(string $country, bool $fullAddress): void
    {
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->fillCheckoutForm([], $fullAddress ? $this->shipping($country) : ['country' => $country]);
        $this->recalculate();

        $response = $this->startSession();

        $this->assertSame(409, $response->get_status());
        $data = (array) $response->get_data();
        $this->assertSame('shipping_incomplete', $data['code']);
        $this->assertNotEmpty($data['message']);
        $this->assertNotSame('shipping_incomplete', $data['message'], 'The shopper must be told why, not given a code.');
        $this->assertSame([], $this->fakeMollie()->requests('POST'));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function incompleteShipping(): array
    {
        return [
            'the shipping address is incomplete' => ['LU', false],
            'no rate ships to the destination' => ['MT', true],
        ];
    }

    /**
     * Scenario: a cart Express cannot pay, or a shop where Express cannot run, is refused with its reason
     *   Given an empty cart, a cart with a subscription, or a shop in test mode
     *   When a session is requested
     *   Then it is answered 409 with the reason code
     *   And nothing is sent to Mollie
     *
     * @test
     * @dataProvider ineligible
     * @param list<string> $presets
     */
    public function it_refuses_an_ineligible_cart_with_its_reason(array $presets, bool $testMode, string $reason): void
    {
        if (in_array('subscription', $presets, true) && !class_exists('WC_Subscriptions_Product')) {
            $this->markTestSkipped('WooCommerce Subscriptions is not active on this site, so no subscription product can be in the cart.');
        }
        if ($testMode) {
            $this->useTestMode();
        }
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith($presets);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');

        $response = $this->startSession();

        $this->assertSame(409, $response->get_status());
        $this->assertSame($reason, ((array) $response->get_data())['code']);
        $this->assertSame([], $this->fakeMollie()->requests('POST'));
    }

    /**
     * @return array<string, array{0: list<string>, 1: bool, 2: string}>
     */
    public function ineligible(): array
    {
        return [
            'an empty cart' => [[], false, 'cart_empty'],
            'a subscription in the cart' => [['subscription'], false, 'subscription_in_cart'],
            'Express is unavailable in test mode' => [['simple'], true, 'mode_not_allowed'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // One session per checkout
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: the same checkout gets the same session
     *   Given a guest who started a session
     *   When the session is requested again with nothing changed, within its lifetime
     *   Then the same clientAccessToken is returned
     *   And no second session is created at Mollie
     *
     * @test
     */
    public function it_reuses_the_open_session_while_the_fingerprint_holds(): void
    {
        $this->readyGuestCheckout();

        $first = $this->startSession();
        $second = $this->startSession();

        $this->assertSame(200, $first->get_status());
        $this->assertSame(200, $second->get_status());
        $this->assertSame($this->token($first), $this->token($second));
        $this->assertCount(1, $this->fakeMollie()->requests('POST', 'sessions'));
    }

    /**
     * Scenario: a session about to expire is not handed out again
     *   Given a guest whose remembered session expires now
     *   When the session is requested again with nothing changed
     *   Then a new session is created and its token returned
     *
     * @test
     */
    public function it_creates_a_new_session_when_the_remembered_one_is_about_to_expire(): void
    {
        $this->readyGuestCheckout();
        $this->fakeMollie()->setNow(time() - FakeMollieApi::SESSION_LIFETIME_SECONDS);
        $first = $this->startSession();
        $this->fakeMollie()->setNow(null);

        $second = $this->startSession();

        $this->assertSame(200, $second->get_status());
        $this->assertNotSame($this->token($first), $this->token($second));
        $this->assertCount(2, $this->fakeMollie()->sessions());
    }

    /**
     * Scenario: a change of shipping that changes the total is a new checkout
     *   Given a guest who started a session
     *   When the shopper chooses another rate, or ships to another destination, and the total changes
     *   Then the next request creates a new session priced with the new total, under a new idempotency key
     *   And going back to the first choice never returns the first token again
     *
     * @test
     * @dataProvider shippingChanges
     */
    public function it_creates_a_new_session_when_shipping_changes_the_total(string $country, string $rate): void
    {
        $this->readyGuestCheckout();
        $first = $this->startSession();
        $firstTotal = $this->cartTotal();

        $this->fillCheckoutForm($this->billing(), $this->shipping($country));
        $this->chooseRate($rate);
        $second = $this->startSession();

        $this->assertSame(200, $second->get_status());
        $this->assertNotSame($firstTotal, $this->cartTotal(), 'The scenario needs a change of total.');
        $requests = $this->fakeMollie()->requests('POST', 'sessions');
        $this->assertCount(2, $requests);
        $this->assertSame($this->cartTotal(), $requests[1]['body']['amount']['value']);
        $this->assertNotSame($requests[0]['idempotencyKey'], $requests[1]['idempotencyKey']);
        $this->assertNotSame($this->token($first), $this->token($second));

        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
        $third = $this->startSession();

        $this->assertSame(200, $third->get_status());
        $this->assertNotSame($this->token($first), $this->token($third), 'A dropped token must never be handed out again.');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function shippingChanges(): array
    {
        return [
            'another rate' => ['LU', 'express'],
            'another destination' => ['AT', 'austria'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Work budget and idempotency
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a shopper cannot make the shop create sessions without limit
     *   Given the configured budget of new sessions per window
     *   When the shopper changes the rate and starts a session more times than the budget allows
     *   Then the request over the budget is answered 429
     *   And it sends nothing to Mollie
     *
     * @test
     */
    public function it_refuses_new_sessions_over_the_budget(): void
    {
        $config = require ROOT_DIR . '/config/express.php';
        $budget = (int) $config['maxNewSessions'];
        $this->assertGreaterThan(0, $budget);
        $this->readyGuestCheckout();

        for ($attempt = 0; $attempt < $budget; $attempt++) {
            $this->chooseRate($attempt % 2 === 0 ? 'express' : 'standard');
            $this->assertSame(200, $this->startSession()->get_status(), "Session {$attempt} is within the budget.");
        }
        $this->chooseRate($budget % 2 === 0 ? 'express' : 'standard');
        $over = $this->startSession();

        $this->assertSame(429, $over->get_status());
        $this->assertCount($budget, $this->fakeMollie()->requests('POST', 'sessions'));
    }

    /**
     * Scenario: a create whose answer was lost is retried under the same key
     *   Given Mollie created the session but the answer timed out on the way back
     *   When the shopper's browser asks again with nothing changed
     *   Then the first request was answered 503
     *   And the retry reaches Mollie with the same Idempotency-Key and gets the same session back
     *   And exactly one session exists at Mollie
     *
     * @test
     */
    public function it_replays_the_same_key_after_a_timeout(): void
    {
        $this->readyGuestCheckout();
        $this->loseTheNextSessionAnswer();

        $first = $this->startSession();
        $second = $this->startSession();

        $this->assertSame(503, $first->get_status());
        $this->assertSame(200, $second->get_status());
        $requests = $this->fakeMollie()->requests('POST', 'sessions');
        $this->assertCount(2, $requests);
        $this->assertNotEmpty($requests[0]['idempotencyKey']);
        $this->assertSame($requests[0]['idempotencyKey'], $requests[1]['idempotencyKey']);
        $this->assertTrue($requests[1]['replayed'], 'The retry must be recognised by Mollie as the same request.');
        $this->assertCount(1, $this->fakeMollie()->sessions());
        $this->assertSame(
            array_key_first($this->fakeMollie()->sessions()),
            FakeMollieApi::sessionIdFromClientAccessToken($this->token($second))
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Failures
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: Mollie refuses the payload and its text stays on the server
     *   Given Mollie answers the next session request 422 with its own message
     *   When a session is requested
     *   Then the route answers 502 with a generic message
     *   And express.session.failed is logged with kind=validation
     *   And Mollie's message is neither in the response nor in the log
     *
     * @test
     */
    public function it_hides_mollies_text_when_the_payload_is_refused(): void
    {
        $this->readyGuestCheckout();
        $this->fakeMollie()->failNext('POST', 'sessions', 422, self::MOLLIE_TEXT, 'lines.0.unitPrice');

        $response = $this->startSession();

        $this->assertSame(502, $response->get_status());
        $data = (array) $response->get_data();
        $this->assertNotEmpty($data['message']);
        $this->assertStringNotContainsString('MOLLIETEXT4e1d', (string) wp_json_encode($data));
        $this->assertStringNotContainsString('MOLLIETEXT4e1d', $this->logger()->dump());
        $failed = $this->loggedEvents('express.session.failed');
        $this->assertCount(1, $failed);
        $this->assertSame('error', $failed[0]['level']);
        $this->assertSame('validation', $failed[0]['context']['kind'] ?? null);
        $this->assertNothingLeakedToLog();
    }

    /**
     * Scenario: an outage or a rate limit at Mollie leaves nothing behind
     *   Given a guest who holds a session for one rate and then chooses another
     *   And Mollie answers the next session request with an outage or a rate limit
     *   When a session is requested
     *   Then the route answers 503
     *   And the shopper's WooCommerce session holds no session id or token, not even the dropped one
     *   And the next request creates a session and answers 200
     *
     * @test
     * @dataProvider mollieUnavailable
     */
    public function it_remembers_nothing_after_an_outage(int $mollieStatus): void
    {
        $this->readyGuestCheckout();
        $dropped = $this->token($this->startSession());
        $this->assertStringContainsString($dropped, $this->storedShopperSession(), 'The check must be able to see a remembered token.');
        $this->chooseRate('express');
        $this->fakeMollie()->failNext('POST', 'sessions', $mollieStatus);

        $response = $this->startSession();

        $this->assertSame(503, $response->get_status());
        $remembered = $this->storedShopperSession();
        $this->assertStringNotContainsString('sess_', $remembered);
        $this->assertStringNotContainsString($dropped, $remembered);

        $retry = $this->startSession();
        $this->assertSame(200, $retry->get_status());
        $this->assertNotSame($dropped, $this->token($retry));
    }

    /**
     * @return array<string, array{0: int}>
     */
    public function mollieUnavailable(): array
    {
        return [
            'an outage' => [503],
            'a rate limit' => [429],
        ];
    }

    /**
     * Scenario: a full start leaks no secret and no personal detail
     *   Given the shop's keys and webhook secret are canary values
     *   And a guest whose checkout form holds a canary name, email, phone and address
     *   When a session is started
     *   Then express.session.created was logged
     *   And no canary value is in the log or in the browser response
     *
     * @test
     */
    public function it_leaks_no_canary_during_a_full_start(): void
    {
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');

        $response = $this->startSession();

        $this->assertSame(200, $response->get_status());
        $this->assertCount(1, $this->loggedEvents('express.session.created'), 'The leak check needs something logged.');
        $this->assertNothingLeakedToLog();
        $this->assertNothingLeakedToBrowser($response->get_data());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function readyGuestCheckout(): void
    {
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function startSession(array $extra = []): WP_REST_Response
    {
        return $this->restRequest('POST', self::ROUTE, array_merge(['nonce' => wp_create_nonce(ExpressRoutes::NONCE_ACTION)], $extra));
    }

    private function token(WP_REST_Response $response): string
    {
        $this->assertSame(200, $response->get_status(), 'Expected a started session: ' . wp_json_encode($response->get_data()));

        return (string) ((array) $response->get_data())['clientAccessToken'];
    }

    /**
     * @return array<string, mixed>
     */
    private function onlySessionPayload(): array
    {
        $payloads = $this->sessionPayloads();
        $this->assertCount(1, $payloads);

        return $payloads[0];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function webhookSecretIn(array $payload): string
    {
        $url = (string) ($payload['payment']['webhookUrl'] ?? '');
        $this->assertStringStartsWith(rest_url(RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE), $url);
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

        return (string) ($query['mollie_webhook_secret'] ?? '');
    }

    /**
     * What WooCommerce stores for this shopper, as the end of the request would leave it:
     * get_session_data() reads the database, which WooCommerce only writes at shutdown.
     */
    private function storedShopperSession(): string
    {
        $session = WC()->session;
        $this->assertInstanceOf(\WC_Session_Handler::class, $session);
        $this->assertTrue($session->has_session(), 'The shopper needs a stored session for this check to mean anything.');
        $session->save_data();

        return (string) wp_json_encode($session->get_session_data());
    }

    private function cartTotal(): string
    {
        return $this->decimal((float) WC()->cart->get_total('edit'));
    }

    private function decimal(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<mixed>}>
     */
    private function loggedEvents(string $event): array
    {
        return array_values(array_filter($this->logger()->records(), static function (array $record) use ($event): bool {
            return $record['message'] === $event;
        }));
    }

    private function optionsContaining(string $needle): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s",
            '%' . $wpdb->esc_like($needle) . '%'
        ));
    }

    /**
     * @return array<string, string>
     */
    private function billing(): array
    {
        return [
            'first_name' => CanaryData::GIVEN_NAME,
            'last_name' => CanaryData::FAMILY_NAME,
            'email' => CanaryData::EMAIL,
            'phone' => CanaryData::PHONE,
            'address_1' => CanaryData::STREET,
            'address_2' => CanaryData::STREET_ADDITIONAL,
            'postcode' => 'L-1234',
            'city' => 'Luxembourg',
            'country' => 'LU',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function shipping(string $country): array
    {
        return [
            'first_name' => CanaryData::GIVEN_NAME,
            'last_name' => CanaryData::FAMILY_NAME,
            'address_1' => CanaryData::STREET,
            'postcode' => $country === 'AT' ? '1010' : '1234',
            'city' => $country === 'AT' ? 'Wien' : 'Town',
            'country' => $country,
        ];
    }

    /**
     * What the shopper typed into the checkout form. Every field not given is emptied, so a
     * scenario never inherits the previous one's form.
     *
     * @param array<string, string> $billing
     * @param array<string, string> $shipping
     */
    private function fillCheckoutForm(array $billing, array $shipping): void
    {
        $customer = WC()->customer;
        foreach (['first_name', 'last_name', 'email', 'phone', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country'] as $field) {
            $customer->{"set_billing_{$field}"}($billing[$field] ?? '');
        }
        foreach (['first_name', 'last_name', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country'] as $field) {
            $customer->{"set_shipping_{$field}"}($shipping[$field] ?? '');
        }
        $customer->save();
    }

    private function chooseRate(string $name): void
    {
        WC()->session->set('chosen_shipping_methods', [$this->rates[$name]]);
        $this->recalculate();
    }

    private function recalculate(): void
    {
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }

    /**
     * Mollie receives and creates the next session, but its answer never arrives: the transport
     * (priority 1) has answered, and this turns that answer into a timeout once.
     */
    private function loseTheNextSessionAnswer(): void
    {
        $lost = false;
        $filter = static function ($preempt, $args, $url) use (&$lost) {
            if (
                !$lost
                && is_array($preempt)
                && strpos((string) $url, 'api.mollie.com/v2/sessions') !== false
                && strtoupper((string) ($args['method'] ?? '')) === 'POST'
            ) {
                $lost = true;

                return new \WP_Error('http_request_failed', 'cURL error 28: Operation timed out');
            }

            return $preempt;
        };
        add_filter('pre_http_request', $filter, 2, 3);
        $this->filters[] = ['pre_http_request', $filter, 2];
    }

    /**
     * A shopper with a cart holds WooCommerce's session cookie, which is what binds a guest nonce to
     * that shopper and makes WooCommerce store the session. WooCommerce sets it only while headers
     * can still be sent, never under PHPUnit, so it is set here the way WooCommerce itself does.
     *
     * @param array<int, string> $presets
     */
    protected function cartWith(array $presets, int $quantity = 1): \WC_Cart
    {
        $cart = parent::cartWith($presets, $quantity);
        $this->withoutCookieNotices(static function (): void {
            WC()->session->set_customer_session_cookie(true);
        });

        return $cart;
    }

    /**
     * Every scenario is a different shopper. The fake Mollie starts empty for each test, but
     * WooCommerce keeps one session and one customer for the whole PHP process; without this a
     * scenario would be handed the session a previous one remembered, and the next test class would
     * inherit this one's checkout form.
     */
    private function newShopper(): void
    {
        if (!function_exists('WC') || !WC()->session instanceof \WC_Session_Handler) {
            return;
        }
        $this->withoutCookieNotices(static function (): void {
            WC()->session->forget_session();
        });
        // A customer read from the now empty session: the store's default location, no form data.
        WC()->customer = new \WC_Customer(0, true);
    }

    /**
     * wc_setcookie() raises a notice once headers are sent, which under the CLI they always are.
     */
    private function withoutCookieNotices(callable $callback): void
    {
        set_error_handler(static function (int $severity, string $message): bool {
            return strpos($message, 'headers already sent') !== false || strpos($message, 'cannot be set') !== false;
        }, E_USER_NOTICE | E_USER_WARNING | E_WARNING | E_NOTICE);
        try {
            $callback();
        } finally {
            restore_error_handler();
        }
    }

    private function useHttps(): void
    {
        $url = static function (): string {
            return 'https://shop.example';
        };
        add_filter('pre_option_home', $url, PHP_INT_MAX);
        add_filter('pre_option_siteurl', $url, PHP_INT_MAX);
        $this->filters[] = ['pre_option_home', $url, PHP_INT_MAX];
        $this->filters[] = ['pre_option_siteurl', $url, PHP_INT_MAX];
    }

    /**
     * PayPal takes its address from the checkout form. With Apple Pay (its own sheet) off, a cart
     * that ships is blocked until the form is complete, which is what the shipping scenarios need.
     */
    private function payPalIsTheOnlyExpressWallet(): void
    {
        $this->setGatewaySettingsForTest('paypal', ['enabled' => 'yes', self::PAYPAL_CHECKOUT => 'yes']);
        $this->setGatewaySettingsForTest('applepay', [self::APPLE_PAY_EXPRESS => 'no']);
    }

    private function taxedShippingZones(): void
    {
        $this->setOptionForTest('woocommerce_calc_taxes', 'yes');
        $this->setOptionForTest('woocommerce_prices_include_tax', 'yes');
        $this->setOptionForTest('woocommerce_tax_display_cart', 'incl');
        $this->setOptionForTest('woocommerce_shipping_tax_class', '');
        $this->setOptionForTest('woocommerce_ship_to_countries', '');
        $this->setOptionForTest('woocommerce_allowed_countries', 'all');
        $this->setOptionForTest('woocommerce_currency', 'EUR');

        foreach (['LU', 'AT', 'MT'] as $country) {
            $this->taxRateIds[] = \WC_Tax::_insert_tax_rate([
                'tax_rate_country' => $country,
                'tax_rate' => '21.0000',
                'tax_rate_name' => 'VAT',
                'tax_rate_priority' => 1,
                'tax_rate_compound' => 0,
                'tax_rate_shipping' => 1,
                'tax_rate_order' => 0,
                'tax_rate_class' => '',
            ]);
        }

        $this->rates = array_merge(
            $this->zone('Express test LU', 'LU', ['standard' => '5.00', 'express' => '7.50']),
            $this->zone('Express test AT', 'AT', ['austria' => '9.00']),
            $this->zone('Express test MT (no rates)', 'MT', [])
        );
        \WC_Cache_Helper::get_transient_version('shipping', true);

        // The site may have zones without locations, which match every address and would win over
        // these (zone_order cannot go below 0). Only this test's zones may match while it runs.
        $zoneIds = implode(',', array_map('intval', $this->zoneIds));
        $onlyTheseZones = static function (array $criteria) use ($zoneIds): array {
            $criteria[] = "AND zones.zone_id IN ({$zoneIds})";

            return $criteria;
        };
        add_filter('woocommerce_get_zone_criteria', $onlyTheseZones, PHP_INT_MAX);
        $this->filters[] = ['woocommerce_get_zone_criteria', $onlyTheseZones, PHP_INT_MAX];
    }

    /**
     * @param array<string, string> $costs Rate name => cost excluding tax.
     * @return array<string, string> Rate name => rate id.
     */
    private function zone(string $name, string $country, array $costs): array
    {
        $zone = new \WC_Shipping_Zone();
        $zone->set_zone_name($name);
        $zone->set_zone_order(0);
        $zone->add_location($country, 'country');
        $zone->save();
        $this->zoneIds[] = $zone->get_id();

        $rates = [];
        foreach ($costs as $rate => $cost) {
            $instanceId = $zone->add_shipping_method('flat_rate');
            update_option("woocommerce_flat_rate_{$instanceId}_settings", [
                'enabled' => 'yes',
                'title' => ucfirst($rate),
                'tax_status' => 'taxable',
                'cost' => $cost,
            ]);
            $rates[$rate] = 'flat_rate:' . $instanceId;
        }

        return $rates;
    }

    /**
     * The express budget is kept by WC_Rate_Limiter, in a table shared by every test, keyed on
     * something the caller cannot choose. Each scenario starts with a full budget.
     */
    private function clearExpressRateLimits(): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wc_rate_limits WHERE rate_limit_key LIKE %s",
            $wpdb->esc_like('mollie_express') . '%'
        ));
        // WC_Rate_Limiter also keeps each expiry in the object cache, under a per-key prefix that
        // WC_Rate_Limiter::cleanup() does not reach.
        if (wp_cache_supports('flush_group')) {
            wp_cache_flush_group(\WC_Rate_Limiter::CACHE_GROUP);
        } else {
            wp_cache_flush();
        }
    }
}
