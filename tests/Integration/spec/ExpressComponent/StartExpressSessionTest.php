<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerce\ExpressComponent\Entry\ExpressRoutes;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\Doubles\RecordingLogger;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\FakeMollie\FakeMollieApi;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\ProductPresets;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Psr\Log\LoggerInterface;
use WP_REST_Response;

/**
 * POST mollie/v1/express/session: admission, pricing from the server cart, session reuse,
 * the rate budget, idempotency and what reaches the browser. Only Mollie is faked.
 * Shop: PayPal the only express wallet; zone LU has two flat rates, AT one, MT none.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressSessionClient
 */
class StartExpressSessionTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const ROUTE = '/mollie/v1/express/session';

    private const MOLLIE_TEXT = 'MOLLIETEXT4e1d the unit price is not what we expected';

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
    }

    public function tearDown(): void
    {
        $this->tearDownExpressCheckout();

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
     *   And no marked value is in the response
     *   And the token is in no option or transient and in no log line
     *
     * @test
     * @group REQ-G1
     * @group REQ-G3
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
     * Scenario: the wallet is always asked for the contact details and the billing address, and for where to ship
     *   Given a shopper whose checkout form holds an email and a billing address, or does not
     *   When a session is started for a cart that ships
     *   Then requiredCustomerDetails asks for email, billing-address and shipping-address either way
     *   And the form's own shipping address is not what the session is priced for
     *
     * @test
     * @dataProvider shoppers
     * @group REQ-C2
     */
    public function it_always_asks_the_wallet_for_contact_billing_and_shipping(bool $loggedIn, bool $formFilled): void
    {
        // Given
        $this->bootExpress();
        $loggedIn ? $this->actAsCustomer() : $this->actAsGuest();
        WC()->customer = new \WC_Customer(get_current_user_id(), true);
        $this->cartWith(['simple']);
        $this->fillCheckoutForm($formFilled ? $this->billing() : [], $formFilled ? $this->shipping('LU') : []);

        // When
        $status = $this->startSession()->get_status();

        // Then
        $this->assertSame(200, $status);
        $details = $this->onlySessionPayload()['requiredCustomerDetails'] ?? [];
        sort($details);
        $this->assertSame(['billing-address', 'email', 'shipping-address'], $details);
    }

    /**
     * Scenario: a cart that ships asks Mollie to call the store for the shipping options
     *   Given a guest with an empty checkout form and a cart that ships, or one that ships nothing
     *   When a session is started
     *   Then a cart that ships has an https shipping.callbackUrl carrying a secret and no shipping_fee line
     *   And its lines sum to the amount, which is the cart total without shipping
     *   And the wallet is asked for email, billing-address and shipping-address
     *   And a cart that ships nothing has no shipping object
     *
     * @test
     * @dataProvider cartsThatShipOrNot
     * @group REQ-H2
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     */
    public function it_sends_a_shipping_callback_instead_of_a_shipping_line_for_a_cart_that_ships(bool $ships): void
    {
        // Given
        $this->bootExpress();
        $this->actAsGuest();
        if (!$ships) {
            $this->addTestFilter('woocommerce_cart_needs_shipping', '__return_false');
        }
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm([], []);

        // When
        $response = $this->startSession();

        // Then
        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $payload = $this->onlySessionPayload();
        $this->assertValidSessionPayload($payload);
        $details = $payload['requiredCustomerDetails'] ?? [];
        sort($details);
        if (!$ships) {
            $this->assertArrayNotHasKey('shipping', $payload);
            $this->assertSame(['billing-address', 'email'], $details);

            return;
        }
        $base = rest_url('mollie/v1/express/shipping');
        $url = (string) ($payload['shipping']['callbackUrl'] ?? '');
        $this->assertStringStartsWith('https://', $url);
        $this->assertStringStartsWith($base, $url);
        $this->assertRegExp('/[A-Za-z0-9_-]{16,}/', substr($url, strlen($base)), 'The URL must carry a secret.');
        $this->assertArrayNotHasKey('options', $payload['shipping'], 'Mollie takes options or a callbackUrl, not both.');
        $types = array_column($payload['lines'], 'type');
        $this->assertNotContains('shipping_fee', $types);
        $this->assertSame($this->cartTotalWithoutShipping(), $payload['amount']['value']);
        $this->assertSame(['billing-address', 'email', 'shipping-address'], $details);
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public function cartsThatShipOrNot(): array
    {
        return [
            'a cart that ships' => [true],
            'a cart that ships nothing' => [false],
        ];
    }

    /**
     * Scenario: an empty shipping form neither blocks the component nor moves the session
     *   Given a guest with a cart that ships and an empty checkout form
     *   When a session is requested
     *   Then it is answered 200, not shipping_incomplete
     *   And when the shopper then fills the form with another destination and rate, the next request gets the same token
     *   And only one session was ever sent to Mollie
     *
     * @test
     * @group REQ-H2
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     */
    public function it_offers_express_with_an_empty_shipping_form_and_keeps_the_session_when_the_form_changes(): void
    {
        // Given
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->fillCheckoutForm([], []);

        // When
        $first = $this->startSession();
        $this->fillCheckoutForm($this->billing(), $this->shipping('AT'));
        $this->chooseRate('austria');
        $second = $this->startSession();

        // Then
        $this->assertSame(200, $first->get_status(), wp_json_encode($first->get_data()));
        $this->assertSame($this->token($first), $this->token($second));
        $this->assertCount(1, $this->fakeMollie()->requests('POST', 'sessions'));
    }

    /**
     * @return array<string, array{0: bool, 1: bool}>
     */
    public function shoppers(): array
    {
        return [
            'a guest with an empty checkout form' => [false, false],
            'a guest who filled in the checkout form' => [false, true],
            'a logged-in customer whose details the store holds' => [true, true],
        ];
    }

    /**
     * Scenario: whatever the browser sends, the session is priced from the server-side cart, without shipping
     *   Given a guest ready to check out
     *   When the request also carries an amount, a currency, lines, an address and a country
     *   Then it is answered 200
     *   And the amount that reached Mollie is the WooCommerce cart total without shipping
     *   And no line is a shipping_fee: Mollie adds the option the shopper picks in the wallet
     *
     * @test
     * @group REQ-B4
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     */
    public function it_prices_from_the_server_cart_whatever_the_request_sends(): void
    {
        // Given
        $this->readyGuestCheckout();

        // When
        $response = $this->startSession([
            'amount' => '0.01',
            'currency' => 'USD',
            'lines' => [['description' => 'Anything', 'quantity' => 1, 'unitPrice' => '0.01']],
            'address' => ['country' => 'US', 'postalCode' => '10001'],
            'shippingAddress' => ['country' => 'US'],
            'country' => 'US',
        ]);

        // Then
        $this->assertSame(200, $response->get_status());
        $payload = $this->onlySessionPayload();
        $this->assertSame(['currency' => get_woocommerce_currency(), 'value' => $this->cartTotalWithoutShipping()], $payload['amount']);
        $this->assertNotContains('shipping_fee', array_column($payload['lines'], 'type'));
    }

    /**
     * Scenario: a product line another plugin priced below zero does not cost the shopper the wallets
     *   Given a guest whose cart holds a product a plugin priced at -0.02 and a product above zero
     *   When the session is requested
     *   Then a session is created for the cart total without shipping
     *   And the line below zero is a discount line named after its product
     *
     * @test
     */
    public function it_starts_a_session_when_a_product_line_is_below_zero(): void
    {
        $this->readyGuestCheckout();
        $this->cartWith(['simple', 'simple_expensive']);
        $belowZero = (int) wc_get_product_id_by_sku('DUMMY_SIMPLE_SKU_01');
        $this->addTestFilter('woocommerce_before_calculate_totals', static function (\WC_Cart $cart) use ($belowZero): void {
            foreach ($cart->get_cart() as $item) {
                if ($item['data']->get_id() === $belowZero) {
                    $item['data']->set_price(-0.02);
                }
            }
        });
        $this->recalculate();

        $response = $this->startSession();

        $this->assertSame(200, $response->get_status(), 'Expected a started session: ' . wp_json_encode($response->get_data()));
        $payload = $this->onlySessionPayload();
        $this->assertSame($this->cartTotalWithoutShipping(), $payload['amount']['value']);
        $discounts = array_values(array_filter($payload['lines'], static function (array $line) use ($belowZero): bool {
            return $line['description'] === wc_get_product($belowZero)->get_name();
        }));
        $this->assertCount(1, $discounts);
        $this->assertSame('discount', $discounts[0]['type']);
        $this->assertStringStartsWith('-', $discounts[0]['totalAmount']['value']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Admission
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a start request that did not come from the shop's own page is refused
     *   Given a guest ready to check out
     *   When the request carries no nonce, an invalid one, or a nonce for another action
     *   Then it is answered 403
     *   And nothing is sent to Mollie
     *
     * @test
     * @dataProvider badNonces
     * @group REQ-G3
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
     * WooCommerce binds a guest nonce to its session only for actions named 'woocommerce*'.
     *
     * @test
     * @group REQ-G3
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

    // ──────────────────────────────────────────────────────────────────────────
    // No Mollie call while the cart cannot be paid
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a cart that ships does not wait for the checkout form
     *   Given a guest with a cart that ships
     *   And the form's shipping address is incomplete, or no rate ships to its destination
     *   When a session is requested
     *   Then it is answered 200, not shipping_incomplete
     *   And exactly one session is sent to Mollie
     *
     * @test
     * @dataProvider incompleteShipping
     * @group REQ-H2
     */
    public function it_starts_a_session_for_a_shipping_cart_whatever_the_form_holds(string $country, bool $fullAddress): void
    {
        // Given
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->fillCheckoutForm([], $fullAddress ? $this->shipping($country) : ['country' => $country]);
        $this->recalculate();

        // When
        $response = $this->startSession();

        // Then
        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertCount(1, $this->fakeMollie()->requests('POST', 'sessions'));
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
     * @group REQ-B2
     * @group REQ-H1
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

    /**
     * Scenario: a shop whose taxes follow the buyer's address in more than one country is refused before Mollie is asked
     *   Given taxes on, based on the customer's billing (or shipping) address
     *   And a shop that sells to Germany and Italy
     *   And a guest with a cart that ships, a complete destination and a chosen rate
     *   When a session is requested
     *   Then it is answered 409 as not available, with the reason tax_depends_on_address
     *   And nothing is sent to Mollie
     *
     * @test
     * @dataProvider taxBasesOnTheCustomer
     * @group REQ-NEW-EXPRESS-TAX
     */
    public function it_refuses_a_session_without_a_mollie_call_when_taxes_depend_on_the_address(string $taxBasedOn): void
    {
        // Given
        $this->shopSellingToGermanyAndItalyWithTaxesOn($taxBasedOn);
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
        $this->assertTrue(WC()->cart->needs_shipping(), 'The cart must ship, or the scenario proves nothing.');

        // When
        $response = $this->startSession();

        // Then
        $this->assertSame(409, $response->get_status());
        $this->assertSame('tax_depends_on_address', ((array) $response->get_data())['code']);
        $this->assertSame('tax_depends_on_address', $this->loggedEvents('express.session.refused')[0]['context']['reason'] ?? null);
        $this->assertSame([], $this->fakeMollie()->requests('POST'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function taxBasesOnTheCustomer(): array
    {
        return [
            'taxes on the billing address' => ['billing'],
            'taxes on the shipping address' => ['shipping'],
        ];
    }

    /**
     * Scenario: a saved address does not make a shop with address-dependent taxes eligible
     *   Given the same shop, taxes based on the billing address
     *   And a logged-in customer whose account has a saved billing country
     *   When a session is requested
     *   Then it is answered 409 as not available, with the reason tax_depends_on_address
     *   And nothing is sent to Mollie
     *
     * @test
     * @group REQ-NEW-EXPRESS-TAX
     */
    public function it_refuses_a_session_for_a_customer_with_a_saved_address_when_taxes_depend_on_the_address(): void
    {
        // Given
        $this->shopSellingToGermanyAndItalyWithTaxesOn('billing');
        $this->bootExpress();
        $this->actAsCustomer();
        update_user_meta($this->customer_id, 'billing_country', 'DE');
        try {
            $this->cartWith(['simple']);
            $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
            $this->chooseRate('standard');

            // When
            $response = $this->startSession();
        } finally {
            delete_user_meta($this->customer_id, 'billing_country');
        }

        // Then
        $this->assertSame(409, $response->get_status());
        $this->assertSame('tax_depends_on_address', ((array) $response->get_data())['code']);
        $this->assertSame([], $this->fakeMollie()->requests('POST'));
    }

    /**
     * Scenario: a guest whose cart is priced for no country is refused where taxes follow one country's address
     *   Given taxes on, based on the customer's billing address, and a shop that sells to Austria only
     *   And a guest who typed no address, so the cart is priced for the store's default customer location
     *   When a session is requested
     *   Then a session is created when the default location is Austria: the cart carries Austria's VAT
     *   And it is answered 409 with tax_depends_on_address when there is no default location, or the shop
     *       base is a country the shop does not sell to, prices entered with or without VAT: the cart
     *       carries no VAT, and the wallet's Austrian address would add it
     *   And nothing is sent to Mollie for a refused cart
     *
     * @test
     * @dataProvider defaultCustomerLocations
     * @group REQ-NEW-EXPRESS-TAX
     */
    public function it_refuses_a_session_for_a_cart_that_is_not_priced_for_the_only_tax_country(
        string $baseCountry,
        string $defaultLocation,
        string $pricesIncludeTax,
        int $status,
        ?string $code
    ): void {

        // Given
        $this->setOptionForTest('woocommerce_tax_based_on', 'billing');
        $this->setOptionForTest('woocommerce_allowed_countries', 'specific');
        $this->setOptionForTest('woocommerce_specific_allowed_countries', ['AT']);
        $this->setOptionForTest('woocommerce_default_country', $baseCountry);
        $this->setOptionForTest('woocommerce_default_customer_address', $defaultLocation);
        $this->setOptionForTest('woocommerce_prices_include_tax', $pricesIncludeTax);
        $this->newShopper();
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);

        // When
        $response = $this->startSession();

        // Then
        $this->assertSame($status, $response->get_status());
        $this->assertSame($code, ((array) $response->get_data())['code'] ?? null);
        $this->assertCount($code === null ? 1 : 0, $this->fakeMollie()->requests('POST', 'sessions'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: int, 4: ?string}>
     */
    public function defaultCustomerLocations(): array
    {
        return [
            'the default location is the shop base, Austria' => ['AT', 'base', 'no', 200, null],
            'no default location' => ['AT', '', 'no', 409, 'tax_depends_on_address'],
            'the shop base is not a selling country' => ['DE', 'base', 'no', 409, 'tax_depends_on_address'],
            'no default location, prices include VAT' => ['AT', '', 'yes', 409, 'tax_depends_on_address'],
        ];
    }

    private function shopSellingToGermanyAndItalyWithTaxesOn(string $taxBasedOn): void
    {
        $this->setOptionForTest('woocommerce_calc_taxes', 'yes');
        $this->setOptionForTest('woocommerce_tax_based_on', $taxBasedOn);
        $this->setOptionForTest('woocommerce_allowed_countries', 'specific');
        $this->setOptionForTest('woocommerce_specific_allowed_countries', ['DE', 'IT']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // One session per checkout
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a cart with nothing to pay is not sent to Mollie
     *   Given a checkout that is otherwise ready, whose total is zero
     *   When the checkout asks for a session
     *   Then the request is refused as not available, and nothing is sent to Mollie
     *
     * @test
     * @group REQ-B2
     */
    public function it_sends_no_session_to_mollie_for_a_cart_that_costs_nothing(): void
    {
        $this->readyGuestCheckout();
        $this->addTestFilter('woocommerce_calculated_total', static fn (): float => 0.0, PHP_INT_MAX);
        $this->recalculate();
        $this->assertSame('0.00', $this->cartTotal(), 'The cart must cost nothing, or the scenario proves nothing.');

        $answer = $this->startSession();

        $this->assertSame(409, $answer->get_status());
        $this->assertSame([], $this->fakeMollie()->requests('POST', 'sessions'));
        $this->assertSame('nothing_to_pay', $this->loggedEvents('express.session.refused')[0]['context']['reason'] ?? null);
    }

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
     * Scenario: a session whose order is settled is not handed out again
     *   Given a guest who started a session and submitted the order for it
     *   And the order was paid or cancelled, or still awaits payment
     *   When the same cart asks for a session again
     *   Then a settled order's session is dropped and a new one is created
     *   And an order still awaiting payment keeps its session
     *
     * @test
     * @dataProvider orderStates
     * @group REQ-G2
     */
    public function it_creates_a_new_session_once_the_remembered_one_has_a_settled_order(string $state, bool $reused): void
    {
        $this->readyGuestCheckout();
        $first = $this->startSession();
        $this->assertAnsweredOk($this->startOrder());
        $order = $this->onlyOrderFor((string) ($this->onlySessionPayload()['metadata']['express_ref'] ?? ''));
        if ($state === 'paid') {
            $order->payment_complete('tr_fakePaid');
        } elseif ($state === 'cancelled') {
            $order->update_status('cancelled');
        }

        $second = $this->startSession();

        $this->assertSame(200, $second->get_status());
        $this->assertSame($reused, $this->token($first) === $this->token($second));
        $this->assertCount($reused ? 1 : 2, $this->fakeMollie()->sessions());
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function orderStates(): array
    {
        return [
            'the order was paid' => ['paid', false],
            'the order was cancelled' => ['cancelled', false],
            'the order still awaits payment' => ['pending', true],
        ];
    }

    /**
     * Scenario: a session of another Mollie account is not handed out again
     *   Given a guest who started a session
     *   When the merchant connects a key of another profile
     *   And the same cart asks for a session again
     *   Then a new session is created and the first token is not handed out
     *
     * @test
     */
    public function it_creates_a_new_session_when_the_merchant_connects_another_profile(): void
    {
        $this->setOptionForTest(self::PLUGIN_ID . '_profile_merchant_id', 'pfl_ofTheFirstKey');
        $this->readyGuestCheckout();
        $first = $this->startSession();

        $this->setOptionForTest(self::PLUGIN_ID . '_profile_merchant_id', 'pfl_ofAnotherKey');
        $second = $this->startSession();

        $this->assertSame(200, $second->get_status());
        $this->assertNotSame($this->token($first), $this->token($second));
        $this->assertCount(2, $this->fakeMollie()->sessions());
    }

    /**
     * Scenario: a session about to expire is not handed out again
     *   Given a guest whose remembered session expires now
     *   When the session is requested again with nothing changed
     *   Then a new session is created and its token returned
     *
     * @test
     * @group REQ-G2
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
     * Scenario: a change of the cart that changes the total is a new checkout
     *   Given a guest who started a session
     *   When the cart changes and the total changes
     *   Then the next request creates a new session priced with the new total, under a new idempotency key
     *   And going back to the first cart never returns the first token again
     *
     * @test
     * @dataProvider cartChanges
     * @group REQ-G2
     * @group REQ-B4
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     * @param array<int, string> $presets
     */
    public function it_creates_a_new_session_when_the_cart_changes_the_total(array $presets, int $quantity): void
    {
        // Given
        $this->readyGuestCheckout();
        $first = $this->startSession();
        $firstTotal = $this->cartTotalWithoutShipping();

        // When
        $this->cartWith($presets, $quantity);
        $second = $this->startSession();

        // Then
        $this->assertSame(200, $second->get_status());
        $this->assertNotSame($firstTotal, $this->cartTotalWithoutShipping(), 'The scenario needs a change of total.');
        $requests = $this->fakeMollie()->requests('POST', 'sessions');
        $this->assertCount(2, $requests);
        $this->assertSame($this->cartTotalWithoutShipping(), $requests[1]['body']['amount']['value']);
        $this->assertNotSame($requests[0]['idempotencyKey'], $requests[1]['idempotencyKey']);
        $this->assertNotSame($this->token($first), $this->token($second));

        $this->cartWith(['simple'], 2);
        $third = $this->startSession();

        $this->assertSame(200, $third->get_status());
        $this->assertNotSame($this->token($first), $this->token($third), 'A dropped token must never be handed out again.');
    }

    /**
     * @return array<string, array{0: array<int, string>, 1: int}>
     */
    public function cartChanges(): array
    {
        return [
            'more of the same product' => [['simple'], 3],
            'another product' => [['simple_expensive'], 1],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Work budget and idempotency
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a shopper cannot make the shop create sessions without limit
     *   Given the configured budget of new sessions per window
     *   When the shopper changes the cart and starts a session more times than the budget allows
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
            $this->changeTheTotal($attempt);
            $this->assertSame(200, $this->startSession()->get_status(), "Session {$attempt} is within the budget.");
        }
        $this->changeTheTotal($budget);
        $over = $this->startSession();

        $this->assertSame(429, $over->get_status());
        $this->assertCount($budget, $this->fakeMollie()->requests('POST', 'sessions'));
    }

    /**
     * Scenario: a caller cannot buy a fresh address ceiling by sending a forwarded-IP header
     *   Given callers whose connection comes from one address
     *   And the shop has not enabled the Store API's proxy support
     *   When every session request comes from a new guest and carries a different X-Real-IP and X-Forwarded-For header
     *   Then the requests share the ceiling of the connection's address and the one over it is answered 429
     *
     * A new guest per request, so the shopper's own budget never refuses: only the address can.
     *
     * @test
     */
    public function it_keeps_one_address_ceiling_for_a_caller_who_varies_forwarded_ip_headers(): void
    {
        $ceiling = 3;
        $this->bootExpress($this->withAddressCeiling($ceiling));

        $this->fromAddress('203.0.113.7', function () use ($ceiling): void {
            for ($guest = 0; $guest <= $ceiling; $guest++) {
                $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.' . ($guest + 1);
                $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.' . ($guest + 1);
                $this->nextGuestAtTheCheckout();
                $status = $this->startSession()->get_status();

                $this->assertSame($guest < $ceiling ? 200 : 429, $status, "Guest {$guest} with a new forwarded address.");
            }
        });
        $this->assertCount($ceiling, $this->fakeMollie()->requests('POST', 'sessions'));
    }

    /**
     * Scenario: shoppers who reach the shop through one address do not use up each other's budget
     *   Given the shop is reached through one address: an office, a mobile carrier, a proxy without proxy support
     *   When one more guest than a shopper's budget allows each opens the checkout once
     *   Then every one of them gets a session
     *
     * @test
     */
    public function it_gives_every_shopper_behind_one_address_their_own_budget(): void
    {
        $budget = $this->configuredBudget();
        $this->bootExpress();
        $answers = [];

        $this->fromAddress('203.0.113.7', function () use ($budget, &$answers): void {
            for ($shopper = 0; $shopper <= $budget; $shopper++) {
                $this->nextGuestAtTheCheckout();
                $answers[] = $this->startSession()->get_status();
            }
        });

        $this->assertSame(array_fill(0, $budget + 1, 200), $answers);
    }

    /**
     * Scenario: dropping the WooCommerce session does not buy sessions without limit
     *   Given a ceiling of new sessions per address, above a shopper's own budget
     *   When a caller on one address starts over as a new guest for every session
     *   Then the request over the ceiling is answered 429 and sends nothing to Mollie
     *
     * @test
     */
    public function it_caps_the_new_sessions_of_one_address_whoever_asks(): void
    {
        $ceiling = $this->configuredBudget() + 2;
        $this->bootExpress($this->withAddressCeiling($ceiling));

        $this->fromAddress('203.0.113.7', function () use ($ceiling): void {
            for ($guest = 0; $guest <= $ceiling; $guest++) {
                $this->nextGuestAtTheCheckout();
                $status = $this->startSession()->get_status();

                $this->assertSame($guest < $ceiling ? 200 : 429, $status, "Guest {$guest} on the same address.");
            }
        });
        $this->assertCount($ceiling, $this->fakeMollie()->requests('POST', 'sessions'));
    }

    /**
     * Scenario: behind a proxy the shop vouches for, the forwarded address is the caller
     *   Given the shop enabled the Store API's proxy support, as WooCommerce documents for proxies and CDNs
     *   And guests reach the shop through the same proxy address
     *   When the guests of one forwarded address spend its ceiling
     *   Then a guest with another X-Real-IP still gets a session
     *
     * @test
     */
    public function it_keys_the_address_ceiling_on_the_forwarded_address_when_the_shop_enables_proxy_support(): void
    {
        $ceiling = 3;
        $this->addTestFilter('woocommerce_store_api_rate_limit_options', static function (array $options): array {
            $options['proxy_support'] = true;

            return $options;
        });
        $this->bootExpress($this->withAddressCeiling($ceiling));

        $this->fromAddress('10.0.0.1', function () use ($ceiling): void {
            $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.1';
            for ($guest = 0; $guest <= $ceiling; $guest++) {
                $this->nextGuestAtTheCheckout();
                $status = $this->startSession()->get_status();

                $this->assertSame($guest < $ceiling ? 200 : 429, $status, "Guest {$guest} of the first forwarded address.");
            }

            $_SERVER['HTTP_X_REAL_IP'] = '198.51.100.2';
            $this->nextGuestAtTheCheckout();
            $this->assertSame(200, $this->startSession()->get_status(), 'A guest behind the same proxy with another address is not held to that ceiling.');
        });
    }

    /**
     * Scenario: the merchant can see that the budget withholds express checkout, with debug off
     *   Given debug logging is off
     *   When the budget refuses a shopper, and refuses again in the same window
     *   Then express.session.budget_spent is written once to the log that is always on, as a warning naming the budget
     *
     * @test
     */
    public function it_warns_once_per_window_when_the_budget_refuses_a_shopper(): void
    {
        $budget = $this->configuredBudget();
        $alwaysOn = new RecordingLogger();
        $this->readyGuestCheckout([
            'settings.IsDebugEnabled' => static fn (): bool => false,
            'log.always_on' => static fn (): LoggerInterface => $alwaysOn,
        ]);
        for ($attempt = 0; $attempt < $budget; $attempt++) {
            $this->changeTheTotal($attempt);
            $this->assertSame(200, $this->startSession()->get_status());
        }
        $this->assertSame([], $alwaysOn->records(), 'Nothing is a problem while the budget has room.');

        foreach ([0, 1] as $refusal) {
            $this->changeTheTotal($budget + $refusal);
            $this->assertSame(429, $this->startSession()->get_status());
        }

        $written = $alwaysOn->records();
        $this->assertCount(1, $written, 'Anyone can be refused, so the log gets one line per window.');
        $this->assertSame(['warning', 'express.session.budget_spent'], [$written[0]['level'], $written[0]['message']]);
        $this->assertSame('shopper', $written[0]['context']['reason'] ?? null);
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
     * @group REQ-B6
     * @group REQ-G6
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
     *   Given a guest who holds a session for one cart and then changes its quantity
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
        $this->changeTheTotal(0);
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

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * A new total for the next session: the cart quantity alternates, a change of the shipping form does not reprice.
     */
    private function changeTheTotal(int $attempt): void
    {
        $this->cartWith(['simple'], 3 - $attempt % 2);
    }

    /**
     * A new guest with the checkout ready for a session.
     */
    private function nextGuestAtTheCheckout(): void
    {
        $this->newShopper();
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
    }

    /**
     * @return array<string, callable> The express.config override for bootExpress().
     */
    private function withAddressCeiling(int $ceiling): array
    {
        return [
            'express.config' => static function () use ($ceiling): array {
                return array_merge(require ROOT_DIR . '/config/express.php', ['maxNewSessionsPerAddress' => $ceiling]);
            },
        ];
    }

    private function configuredBudget(): int
    {
        $config = require ROOT_DIR . '/config/express.php';
        $budget = (int) $config['maxNewSessions'];
        $this->assertGreaterThan(0, $budget);

        return $budget;
    }

    /**
     * Runs the requests as if the connection came from $remoteAddr; the forwarded headers the
     * callback sets are removed afterwards.
     */
    private function fromAddress(string $remoteAddr, callable $requests): void
    {
        $saved = array_intersect_key($_SERVER, array_flip(['REMOTE_ADDR', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR']));
        $_SERVER['REMOTE_ADDR'] = $remoteAddr;
        try {
            $requests();
        } finally {
            unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
            $_SERVER = array_merge($_SERVER, $saved);
        }
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
     * Saves first: get_session_data() reads the database, which WooCommerce writes only at shutdown.
     */
    private function storedShopperSession(): string
    {
        $session = WC()->session;
        $this->assertInstanceOf(\WC_Session_Handler::class, $session);
        $this->assertTrue($session->has_session(), 'The shopper needs a stored session for this check to mean anything.');
        $session->save_data();

        return (string) wp_json_encode($session->get_session_data());
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
     * Mollie creates the next session, then this turns the fake transport's answer into a timeout once.
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
     * @scenario A session opens for the page the shopper is on, the cart or the checkout, and for no other
     *   Given a guest ready to pay, PayPal enabled and active at Mollie
     *   And PayPal's express switches set for the page asked: only the cart switch on for the cart,
     *        only the checkout switch on for the checkout, both on for any other surface
     *   When the browser posts a valid nonce with that surface to the session route
     *   Then a session opens for the cart and for the checkout, each through its own switch
     *   And the product page, an unknown surface or no surface at all is refused
     *   And nothing is sent to Mollie for a refused surface
     *
     * @test
     * @dataProvider surfacesAsked
     * @param string|null $surface null sends no surface
     * @group REQ-A1
     * @group REQ-NG2
     */
    public function it_opens_a_session_only_for_the_cart_or_the_checkout(?string $surface, string $cartSwitch, string $checkoutSwitch, bool $opens): void
    {
        // Given
        $this->setGatewaySettingsForTest('paypal', [
            'enabled' => 'yes',
            'mollie_paypal_button_enabled_cart' => $cartSwitch,
            'mollie_paypal_button_enabled_checkout' => $checkoutSwitch,
        ]);
        $this->readyGuestCheckout();
        $params = ['nonce' => wp_create_nonce(ExpressRoutes::NONCE_ACTION)];
        if ($surface !== null) {
            $params['surface'] = $surface;
        }

        // When
        $response = $this->restRequest('POST', self::ROUTE, $params);

        // Then
        if ($opens) {
            $this->assertNotSame('', $this->token($response));
            $this->assertNotSame([], $this->fakeMollie()->requests('POST'));

            return;
        }
        $this->assertGreaterThanOrEqual(400, $response->get_status(), "Surface '" . (string) $surface . "' must be refused.");
        $this->assertLessThan(500, $response->get_status());
        $this->assertSame([], $this->fakeMollie()->requests('POST'));
    }

    /**
     * @return array<string, array{0: ?string, 1: string, 2: string, 3: bool}>
     */
    public function surfacesAsked(): array
    {
        return [
            'the cart, through its cart switch' => ['cart', 'yes', 'no', true],
            'the checkout, through its checkout switch' => ['checkout', 'no', 'yes', true],
            'the product page' => ['product', 'yes', 'yes', false],
            'an unknown surface' => ['minicart', 'yes', 'yes', false],
            'no surface' => [null, 'yes', 'yes', false],
        ];
    }
}
