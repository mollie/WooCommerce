<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerce\Adapter\WordPress\OrderLock;
use Mollie\WooCommerce\Core\Clock;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\Doubles\SettableClock;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Fixtures\ProductPresets;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use WC_Order;
use WP_REST_Response;
use wpdb;

/**
 * POST mollie/v1/express/order: the pending order created at Mollie's checkout.on('submit'), after the
 * shopper authorised in the wallet and before Mollie creates the payment (REQ-B3, B5, B6, C3, C4;
 * AC-8, AC-10, AC-12, AC-14, AC-14b, AC-16, AC-17).
 *
 * The route takes a nonce and nothing else. The session, the cart, the total, the shipping and the
 * addresses all come from the server side. One order per express_ref is enforced under the lock. A
 * refusal answers {ok: false, code, message} and leaves no order behind, so the browser can reject
 * the submit and the shopper can still check out normally.
 *
 * Observed end to end: the real REST server, cart, WooCommerce checkout and order storage, with only
 * Mollie faked at the HTTP layer. The shop is the one of ExpressCheckoutFixtures: PayPal the only
 * express wallet, 21% VAT, LU with 'standard' and 'express' rates.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressOrderLifecycle
 */
class StartExpressOrderTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const PAYPAL_GATEWAY = 'mollie_wc_gateway_paypal';

    private SettableClock $clock;

    /**
     * Product properties a scenario changed, restored in tearDown().
     *
     * @var array<int, array{virtual: bool, manage_stock: bool, stock_quantity: ?int, stock_status: string}>
     */
    private array $changedProducts = [];

    /**
     * The fixture customer's billing address before a scenario changed it, restored in tearDown().
     *
     * @var array<string, string>|null
     */
    private ?array $accountBackup = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->clock = new SettableClock();
    }

    public function tearDown(): void
    {
        foreach ($this->changedProducts as $productId => $original) {
            $product = wc_get_product($productId);
            $product->set_virtual($original['virtual']);
            $product->set_manage_stock($original['manage_stock']);
            $product->set_stock_quantity($original['stock_quantity']);
            $product->set_stock_status($original['stock_status']);
            $product->save();
        }
        $this->changedProducts = [];
        if ($this->accountBackup !== null) {
            $account = new \WC_Customer($this->customer_id);
            foreach ($this->accountBackup as $field => $value) {
                $setter = [$account, "set_billing_{$field}"];
                if (is_callable($setter)) {
                    $setter($value);
                }
            }
            $account->save();
            $this->accountBackup = null;
        }
        $this->tearDownExpressCheckout();

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Creating the order
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: the submit of a started session creates one pending express order equal to the cart
     *   Given a guest whose session was started for a cart of two simple products
     *   When the browser posts a valid nonce to the order route
     *   Then it is answered ok=true
     *   And exactly one order exists for this session
     *   And it is pending, created via mollie_express, with the provisional PayPal payment method
     *   And it carries the session's express_ref, session id and expiry in its meta
     *   And its total and line items equal the cart's
     *
     * @test
     */
    public function it_creates_one_pending_express_order_matching_the_cart(): void
    {
        $this->readyGuestCheckout();
        $session = $this->startedSession();
        $cartTotal = $this->cartTotal();
        $cartLines = $this->cartLines();
        $before = $this->allOrderIds();

        $response = $this->startOrder();

        $this->assertAnsweredOk($response);
        $created = array_values(array_diff($this->allOrderIds(), $before));
        $this->assertCount(1, $created, 'Exactly one order must be created.');
        $order = wc_get_order($created[0]);
        $this->assertInstanceOf(WC_Order::class, $order);
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('mollie_express', $order->get_created_via());
        $this->assertSame(self::PAYPAL_GATEWAY, $order->get_payment_method());
        $this->assertSame($session['ref'], $order->get_meta('_mollie_express_ref'));
        $this->assertSame($session['id'], $order->get_meta('_mollie_express_session_id'));
        $this->assertNotEmpty($order->get_meta('_mollie_express_expires_at'));
        $this->assertSame($cartTotal, $this->formattedTotal($order));
        $this->assertSame($cartLines, $this->orderLines($order));
        $this->assertCount(1, $this->loggedEvents('express.order.created'));
    }

    /**
     * Scenario: a cart that ships becomes an order shipped to the form's address with the chosen rate
     *   Given a guest whose checkout form ships to LU and who chose the 'express' rate
     *   And a session priced for that
     *   When the order is requested
     *   Then the order's shipping address is the form's
     *   And its only shipping line is the chosen rate
     *   And its total, which includes that rate, equals the cart total
     *
     * @test
     */
    public function it_ships_to_the_form_address_with_the_chosen_rate_as_its_shipping_line(): void
    {
        $this->readyGuestCheckout();
        $this->chooseRate('express');
        $session = $this->startedSession();
        $cartTotal = $this->cartTotal();

        $this->assertAnsweredOk($this->startOrder());

        $order = $this->onlyOrderFor($session['ref']);
        $shipping = $this->shipping('LU');
        $this->assertSame($shipping['address_1'], $order->get_shipping_address_1());
        $this->assertSame($shipping['postcode'], $order->get_shipping_postcode());
        $this->assertSame($shipping['city'], $order->get_shipping_city());
        $this->assertSame($shipping['country'], $order->get_shipping_country());
        $shippingLines = array_values($order->get_items('shipping'));
        $this->assertCount(1, $shippingLines);
        [$methodId, $instanceId] = explode(':', $this->rates['express']);
        $this->assertSame($methodId, $shippingLines[0]->get_method_id());
        $this->assertSame($instanceId, (string) $shippingLines[0]->get_instance_id());
        $this->assertGreaterThan(0, (float) $order->get_shipping_total());
        $this->assertSame($cartTotal, $this->formattedTotal($order));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Refusals
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a cart that ships cannot be submitted while the shipping details are incomplete
     *   Given a guest whose session was started for a complete, priced checkout
     *   When the checkout form's shipping destination is emptied, or moved to where no rate ships
     *   And the order is requested
     *   Then it is answered ok=false with code shipping_incomplete and the message that the order
     *        contains items to ship and the shipping details must be filled in
     *   And no order is created
     *
     * @test
     * @dataProvider incompleteShipping
     */
    public function it_refuses_a_shipping_cart_while_the_form_is_incomplete(string $whatIsMissing): void
    {
        $this->readyGuestCheckout();
        $this->startedSession();
        if ($whatIsMissing === 'destination') {
            $this->fillCheckoutForm($this->billing(), array_merge($this->shipping('LU'), ['postcode' => '', 'address_1' => '', 'city' => '']));
        } else {
            // WooCommerce picks a rate by itself whenever one exists, so "no rate" is a destination without rates.
            $this->fillCheckoutForm($this->billing(), $this->shipping('MT'));
        }
        $this->recalculate();
        $before = $this->allOrderIds();

        $response = $this->startOrder();

        $this->assertRefused($response, 'shipping_incomplete');
        $message = (string) ((array) $response->get_data())['message'];
        $this->assertStringContainsString('items to ship', $message);
        $this->assertStringContainsString('shipping details', $message);
        $this->assertSame($before, $this->allOrderIds());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function incompleteShipping(): array
    {
        return [
            'the shipping destination was emptied' => ['destination'],
            'no rate can be chosen for the destination' => ['rate'],
        ];
    }

    /**
     * Scenario: a repeated submit for the same session gets the order that exists
     *   Given a guest whose order was created for the started session
     *   When the order is requested again
     *   Then the second answer equals the first
     *   And still exactly one order exists for the session
     *   And express.order.reused is logged
     *
     * @test
     */
    public function it_creates_one_order_for_two_sequential_requests(): void
    {
        $this->readyGuestCheckout();
        $session = $this->startedSession();
        $before = $this->allOrderIds();

        $first = $this->startOrder();
        $second = $this->startOrder();

        $this->assertAnsweredOk($first);
        $this->assertAnsweredOk($second);
        $this->assertSame($first->get_data(), $second->get_data());
        $this->assertCount(1, array_diff($this->allOrderIds(), $before));
        $this->assertCount(1, $this->ordersFor($session['ref']));
        $this->assertCount(1, $this->loggedEvents('express.order.reused'));
    }

    /**
     * Scenario: a submit that arrives while another request holds the lock waits for it and gets its order
     *   Given a guest whose order was created for the started session
     *   And another connection holds the lock of this express_ref for about a second
     *   When the order is requested again
     *   Then the request waits for the lock instead of failing
     *   And it answers the existing order's outcome
     *   And still exactly one order exists for the session
     *
     * @test
     */
    public function it_answers_the_existing_order_to_a_request_that_waited_for_the_lock(): void
    {
        $this->readyGuestCheckout();
        $session = $this->startedSession();
        $first = $this->startOrder();
        $this->assertAnsweredOk($first);
        $before = $this->allOrderIds();

        $holder = $this->holdTheLockBriefly($session['ref'], 1);
        $started = microtime(true);
        $second = $this->startOrder();
        $waited = microtime(true) - $started;
        $this->finishHolding($holder);

        $this->assertGreaterThan(0.5, $waited, 'The request must have waited for the lock, or the scenario did not contend.');
        $this->assertAnsweredOk($second);
        $this->assertSame($first->get_data(), $second->get_data());
        $this->assertSame($before, $this->allOrderIds());
        $this->assertCount(1, $this->ordersFor($session['ref']));
    }

    /**
     * Scenario: a submit that cannot get the lock is told to try again and creates nothing
     *   Given a guest whose session was started
     *   And another connection holds the lock of this express_ref for longer than the lock timeout
     *   When the order is requested
     *   Then it is answered 503 with code try_again
     *   And no order is created
     *   And once the lock is free, the retry creates the one order
     *
     * @test
     */
    public function it_answers_try_again_while_the_lock_stays_taken(): void
    {
        $this->readyGuestCheckout();
        $session = $this->startedSession();
        $before = $this->allOrderIds();

        $holder = $this->holdTheLock($session['ref']);
        try {
            $response = $this->startOrder();
        } finally {
            $this->releaseTheLock($holder, $session['ref']);
        }

        $this->assertSame(503, $response->get_status());
        $this->assertSame('try_again', ((array) $response->get_data())['code'] ?? null);
        $this->assertSame($before, $this->allOrderIds());

        $this->assertAnsweredOk($this->startOrder());
        $this->assertCount(1, $this->ordersFor($session['ref']));
    }

    /**
     * Scenario: there is no order without an open session
     *   Given a guest ready to check out who never started a session, or whose session's expiresAt has passed
     *   When the order is requested
     *   Then it is answered ok=false with code session_missing or session_expired and a message for the shopper
     *   And no order is created
     *
     * @test
     * @dataProvider noOpenSession
     */
    public function it_refuses_an_order_without_an_open_session(string $case, string $expectedCode): void
    {
        $this->readyGuestCheckout([Clock::class => $this->clockService()]);
        if ($case === 'expired') {
            $session = $this->startedSession();
            $this->clock->set($this->sessionExpiresAt($session['id']));
        }
        $before = $this->allOrderIds();

        $response = $this->startOrder();

        $this->assertRefused($response, $expectedCode);
        $this->assertNotSame('', trim((string) ((array) $response->get_data())['message']));
        $this->assertSame($before, $this->allOrderIds());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function noOpenSession(): array
    {
        return [
            'no session was started' => ['never_started', 'session_missing'],
            'the session expired' => ['expired', 'session_expired'],
        ];
    }

    /**
     * Scenario: a checkout priced differently from its session is refused and the session forgotten
     *   Given a guest whose session was started
     *   When the cart quantity, the shipping destination or the shipping rate changes
     *   And the order is requested
     *   Then it is answered ok=false with code cart_changed
     *   And no order is created
     *   And when the checkout is put back as it was, the next start creates a new session instead of
     *       handing out the discarded one
     *
     * @test
     * @dataProvider priceChanges
     */
    public function it_refuses_and_forgets_the_session_when_the_price_changed(string $change): void
    {
        $this->readyGuestCheckout();
        $this->startedSession();
        $before = $this->allOrderIds();
        $this->changeCheckout($change);

        $response = $this->startOrder();

        $this->assertRefused($response, 'cart_changed');
        $this->assertSame($before, $this->allOrderIds());

        $this->restoreCheckout($change);
        $this->assertSame(200, $this->startSession()->get_status());
        $this->assertCount(2, $this->fakeMollie()->sessions(), 'The refused session must have been discarded, not reused.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function priceChanges(): array
    {
        return [
            'the cart quantity changed' => ['quantity'],
            'the shipping destination changed' => ['destination'],
            'the shipping rate changed' => ['rate'],
        ];
    }

    /**
     * Scenario: stock that ran out after the wallet opened is refused before money moves
     *   Given a guest whose session was started for the last two items in stock
     *   And another shopper bought them since
     *   When the order is requested
     *   Then it is answered ok=false with WooCommerce's own out-of-stock reason
     *   And no order is created
     *
     * @test
     */
    public function it_refuses_with_woocommerces_reason_when_the_last_item_sold_out(): void
    {
        $product = $this->simpleProduct();
        $this->changeProduct($product->get_id(), static function (\WC_Product $product): void {
            $product->set_manage_stock(true);
            $product->set_stock_quantity(2);
            $product->set_stock_status('instock');
        });
        $this->readyGuestCheckout();
        $this->startedSession();
        wc_update_product_stock($product->get_id(), 0);
        $this->reloadCartAsTheNextRequestWould();
        $before = $this->allOrderIds();

        $response = $this->startOrder();

        $data = (array) $response->get_data();
        $this->assertFalse($data['ok'] ?? null, 'Expected a refusal: ' . wp_json_encode($data));
        $message = (string) ($data['message'] ?? '');
        $this->assertStringContainsString($product->get_name(), $message, 'The reason must be WooCommerce\'s, naming the product.');
        $this->assertStringContainsString('stock', $message);
        $this->assertSame($before, $this->allOrderIds());
    }

    /**
     * Scenario: an order request that did not come from the shop's own page is refused
     *   Given a guest whose session was started
     *   When the order request carries no nonce, a forged one, or a nonce for another action
     *   Then it is answered 403
     *   And no order is created and nothing is asked of Mollie
     *
     * @test
     * @dataProvider badNonces
     */
    public function it_refuses_an_order_request_without_a_valid_nonce(?string $nonceAction, ?string $literal): void
    {
        $this->readyGuestCheckout();
        $this->startedSession();
        $before = $this->allOrderIds();
        $mollieCalls = count($this->fakeMollie()->requests());
        $params = [];
        if ($nonceAction !== null) {
            $params['nonce'] = wp_create_nonce($nonceAction);
        } elseif ($literal !== null) {
            $params['nonce'] = $literal;
        }

        $response = $this->restRequest('POST', '/mollie/v1/express/order', $params);

        $this->assertSame(403, $response->get_status());
        $this->assertSame($before, $this->allOrderIds());
        $this->assertCount($mollieCalls, $this->fakeMollie()->requests());
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public function badNonces(): array
    {
        return [
            'no nonce' => [null, null],
            'a forged nonce' => [null, 'abcdef1234'],
            'a nonce for another action' => ['wp_rest', null],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // The details handed back to event.resolve()
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a guest's details come back from the checkout form, in Mollie's shape
     *   Given a guest whose checkout form holds an email, a billing address with a state and a shipping address
     *   When the order is requested
     *   Then the answer carries that email, and billingAddress and shippingAddress in Mollie's shape
     *   And the billing state is the billingAddress region
     *   And the shipping address, which has no state, carries no region
     *
     * @test
     */
    public function it_answers_a_guests_details_from_the_checkout_form(): void
    {
        $this->readyGuestCheckout();
        $billing = array_merge($this->billing(), ['state' => 'Capellen']);
        $this->fillCheckoutForm($billing, $this->shipping('LU'));
        $this->chooseRate('standard');
        $this->startedSession();

        $data = (array) $this->startOrder()->get_data();

        $this->assertTrue($data['ok'] ?? null);
        $this->assertSame(CanaryData::EMAIL, $data['email'] ?? null);
        $this->assertAddressHolds([
            'givenName' => $billing['first_name'],
            'familyName' => $billing['last_name'],
            'streetAndNumber' => $billing['address_1'],
            'streetAdditional' => $billing['address_2'],
            'postalCode' => $billing['postcode'],
            'city' => $billing['city'],
            'region' => 'Capellen',
            'country' => 'LU',
        ], (array) ($data['billingAddress'] ?? []));
        $shipping = $this->shipping('LU');
        $this->assertAddressHolds([
            'givenName' => $shipping['first_name'],
            'familyName' => $shipping['last_name'],
            'streetAndNumber' => $shipping['address_1'],
            'postalCode' => $shipping['postcode'],
            'city' => $shipping['city'],
            'country' => 'LU',
        ], (array) ($data['shippingAddress'] ?? []));
        $this->assertArrayNotHasKey('region', (array) $data['shippingAddress']);
        $this->assertArrayNotHasKey('streetAdditional', (array) $data['shippingAddress']);
    }

    /**
     * Scenario: a logged-in shopper's billing details come back from the account
     *   Given a logged-in customer whose account holds an email and a billing address
     *   And who only chose where to ship on the checkout
     *   When the order is requested
     *   Then the answer carries the account's email and billing address
     *
     * @test
     */
    public function it_answers_a_logged_in_shoppers_details_from_the_account(): void
    {
        $account = new \WC_Customer($this->customer_id);
        $this->accountBackup = $account->get_billing();
        $account->set_billing_first_name('Account');
        $account->set_billing_last_name('Holder');
        $account->set_billing_address_1('Accountstraat 1');
        $account->set_billing_postcode('L-9999');
        $account->set_billing_city('Accountville');
        $account->set_billing_country('LU');
        $account->save();
        $accountEmail = $account->get_billing_email() !== '' ? $account->get_billing_email() : $account->get_email();

        $this->bootExpress();
        $this->actAsCustomer();
        WC()->customer = new \WC_Customer(get_current_user_id(), true);
        $this->cartWith(['simple'], 2);
        foreach ($this->shipping('LU') as $field => $value) {
            WC()->customer->{"set_shipping_{$field}"}($value);
        }
        WC()->customer->save();
        $this->chooseRate('standard');
        $this->startedSession();

        $data = (array) $this->startOrder()->get_data();

        $this->assertTrue($data['ok'] ?? null, 'Expected ok: ' . wp_json_encode($data));
        $this->assertSame($accountEmail, $data['email'] ?? null);
        $this->assertAddressHolds([
            'givenName' => 'Account',
            'familyName' => 'Holder',
            'streetAndNumber' => 'Accountstraat 1',
            'postalCode' => 'L-9999',
            'city' => 'Accountville',
            'country' => 'LU',
        ], (array) ($data['billingAddress'] ?? []));
    }

    /**
     * Scenario: what the store does not hold is left for the wallet to collect
     *   Given a guest with an empty checkout form and a cart with nothing to ship
     *   When the order is requested
     *   Then it is answered ok=true without an email
     *   And without a name, street, postcode or city in any address
     *
     * @test
     */
    public function it_omits_the_details_the_store_does_not_hold(): void
    {
        $this->changeProduct($this->simpleProduct()->get_id(), static function (\WC_Product $product): void {
            $product->set_virtual(true);
        });
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple']);
        $this->fillCheckoutForm([], []);
        $this->recalculate();
        $this->startedSession();

        $data = (array) $this->startOrder()->get_data();

        $this->assertTrue($data['ok'] ?? null, 'Expected ok: ' . wp_json_encode($data));
        $this->assertArrayNotHasKey('email', $data);
        foreach (['billingAddress', 'shippingAddress'] as $type) {
            foreach (['givenName', 'familyName', 'streetAndNumber', 'postalCode', 'city', 'email'] as $field) {
                $this->assertArrayNotHasKey($field, (array) ($data[$type] ?? []), "{$type}.{$field} is not held and must be left out.");
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Nothing left behind
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: an order whose total is not the session's amount to the cent is deleted and refused
     *   Given a guest whose session was started
     *   And something changes the created order's total by one cent
     *   When the order is requested
     *   Then it is answered ok=false
     *   And no order is left behind
     *
     * @test
     */
    public function it_deletes_the_order_when_its_total_differs_from_the_session(): void
    {
        $this->readyGuestCheckout();
        $this->startedSession();
        $before = $this->allOrderIds();
        $this->addTestFilter('woocommerce_checkout_create_order', static function (WC_Order $order): void {
            $order->set_total((string) ((float) $order->get_total() + 0.01));
        }, 10, 1);

        $response = $this->startOrder();

        $this->assertFalse(((array) $response->get_data())['ok'] ?? null);
        $this->assertSame($before, $this->allOrderIds());
    }

    /**
     * Scenario: an order creation that fails half way leaves nothing behind
     *   Given a guest whose session was started
     *   And WooCommerce throws after the order was saved
     *   When the order is requested
     *   Then it is answered ok=false
     *   And no order is left behind
     *   And express.order.refused is logged with reason creation_failed
     *
     * @test
     */
    public function it_leaves_nothing_behind_when_order_creation_throws(): void
    {
        $this->readyGuestCheckout();
        $this->startedSession();
        $before = $this->allOrderIds();
        $this->addTestFilter('woocommerce_checkout_order_created', static function (): void {
            throw new \RuntimeException('Simulated failure after the order was saved.');
        }, 10, 1);

        $response = $this->startOrder();

        $this->assertFalse(((array) $response->get_data())['ok'] ?? null);
        $this->assertSame($before, $this->allOrderIds());
        $refused = $this->loggedEvents('express.order.refused');
        $this->assertCount(1, $refused);
        $this->assertSame('creation_failed', $refused[0]['context']['reason'] ?? null);
    }

    /**
     * Scenario: a failure while the new order is being stamped leaves nothing behind
     *   Given a guest whose session was started
     *   And saving the express stamps on the created order fails
     *   When the order is requested
     *   Then it is answered ok=false
     *   And no order is left behind
     *   And express.order.refused is logged with reason creation_failed
     *
     * @test
     */
    public function it_leaves_nothing_behind_when_stamping_the_order_fails(): void
    {
        $this->readyGuestCheckout();
        $this->startedSession();
        $before = $this->allOrderIds();
        $this->addTestFilter('woocommerce_before_order_object_save', static function (WC_Order $order): void {
            if ($order->get_created_via() === 'mollie_express') {
                throw new \RuntimeException('Simulated failure while stamping the order.');
            }
        }, 10, 1);

        $response = $this->startOrder();

        $this->assertFalse(((array) $response->get_data())['ok'] ?? null);
        $this->assertSame($before, $this->allOrderIds());
        $refused = $this->loggedEvents('express.order.refused');
        $this->assertCount(1, $refused);
        $this->assertSame('creation_failed', $refused[0]['context']['reason'] ?? null);
    }

    /**
     * Scenario: express checkout never takes over an order another checkout started
     *   Given a guest whose session was started
     *   And the shopper's WooCommerce session holds a pending order of the regular checkout for this same cart
     *   When the express order is requested
     *   Then a new express order is created
     *   And the regular checkout's order keeps its created_via, carries no express meta and is still the one awaiting payment
     *
     * @test
     */
    public function it_never_takes_over_an_order_another_checkout_started(): void
    {
        $this->readyGuestCheckout();
        $session = $this->startedSession();
        $regular = WC()->checkout()->create_order(['payment_method' => '']);
        $this->assertIsInt($regular);
        WC()->session->set('order_awaiting_payment', $regular);

        $this->assertAnsweredOk($this->startOrder());

        $express = $this->onlyOrderFor($session['ref']);
        $this->assertNotSame($regular, $express->get_id());
        $untouched = wc_get_order($regular);
        $this->assertSame('checkout', $untouched->get_created_via());
        $this->assertSame('', (string) $untouched->get_meta('_mollie_express_ref'));
        $this->assertSame($regular, (int) WC()->session->get('order_awaiting_payment'));
    }

    /**
     * Scenario: starting an order logs no secret and no shopper detail
     *   Given canary API keys and webhook secret, and a checkout form full of canary shopper data
     *   When a session is started and the order is requested, twice
     *   Then nothing marked secret or personal reached the log
     *
     * @test
     */
    public function it_leaks_no_canary_while_starting_an_order(): void
    {
        $this->readyGuestCheckout();
        $this->startedSession();

        $this->assertAnsweredOk($this->startOrder());
        $this->assertAnsweredOk($this->startOrder());

        $this->assertNotSame('', $this->logger()->dump(), 'The scenario must have logged something to check.');
        $this->assertNothingLeakedToLog();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function clockService(): callable
    {
        $clock = $this->clock;

        return static function () use ($clock): Clock {
            return $clock;
        };
    }

    private function assertRefused(WP_REST_Response $response, string $code): void
    {
        $data = (array) $response->get_data();
        $this->assertFalse($data['ok'] ?? null, 'Expected ok=false: ' . wp_json_encode($data));
        $this->assertSame($code, $data['code'] ?? null);
        $this->assertArrayHasKey('message', $data);
    }

    /**
     * @param array<string, string> $expected
     * @param array<string, mixed> $actual
     */
    private function assertAddressHolds(array $expected, array $actual): void
    {
        foreach ($expected as $field => $value) {
            $this->assertSame($value, $actual[$field] ?? null, "Address field {$field}: " . wp_json_encode($actual));
        }
    }

    /**
     * @return array<int, array{0: int, 1: int}> Product id and quantity per cart line, sorted.
     */
    private function cartLines(): array
    {
        $lines = [];
        foreach (WC()->cart->get_cart() as $item) {
            $lines[] = [(int) $item['product_id'], (int) $item['quantity']];
        }
        sort($lines);

        return $lines;
    }

    /**
     * @return array<int, array{0: int, 1: int}> Product id and quantity per order line, sorted.
     */
    private function orderLines(WC_Order $order): array
    {
        $lines = [];
        foreach ($order->get_items() as $item) {
            assert($item instanceof \WC_Order_Item_Product);
            $lines[] = [(int) $item->get_product_id(), (int) $item->get_quantity()];
        }
        sort($lines);

        return $lines;
    }

    private function changeCheckout(string $change): void
    {
        if ($change === 'quantity') {
            WC()->cart->set_quantity((string) array_key_first(WC()->cart->get_cart()), 3);
        } elseif ($change === 'destination') {
            $this->fillCheckoutForm($this->billing(), array_merge($this->shipping('LU'), ['postcode' => '5678']));
        } else {
            WC()->session->set('chosen_shipping_methods', [$this->rates['express']]);
        }
        $this->recalculate();
    }

    private function restoreCheckout(string $change): void
    {
        if ($change === 'quantity') {
            WC()->cart->set_quantity((string) array_key_first(WC()->cart->get_cart()), 2);
        } elseif ($change === 'destination') {
            $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        } else {
            WC()->session->set('chosen_shipping_methods', [$this->rates['standard']]);
        }
        $this->recalculate();
    }

    /**
     * A REST request loads the cart from the session with fresh product objects; under PHPUnit the
     * cart would otherwise still hold the product as it was before the scenario changed it.
     */
    private function reloadCartAsTheNextRequestWould(): void
    {
        $contents = WC()->cart->get_cart();
        foreach ($contents as $key => $item) {
            $contents[$key]['data'] = wc_get_product((int) ($item['variation_id'] ?: $item['product_id']));
        }
        WC()->cart->set_cart_contents($contents);
    }

    private function simpleProduct(): \WC_Product
    {
        $product = wc_get_product((int) wc_get_product_id_by_sku(ProductPresets::get()['simple']['sku']));
        $this->assertInstanceOf(\WC_Product::class, $product);

        return $product;
    }

    /**
     * Changes a preset product for this scenario only; tearDown() puts it back.
     */
    private function changeProduct(int $productId, callable $change): void
    {
        $product = wc_get_product($productId);
        if (!isset($this->changedProducts[$productId])) {
            $this->changedProducts[$productId] = [
                'virtual' => $product->get_virtual(),
                'manage_stock' => $product->get_manage_stock(),
                'stock_quantity' => $product->get_stock_quantity(),
                'stock_status' => $product->get_stock_status(),
            ];
        }
        $change($product);
        $product->save();
    }

    /**
     * Holds the express_ref's lock from another connection until releaseTheLock().
     */
    private function holdTheLock(string $ref): wpdb
    {
        $holder = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $taken = $holder->get_var($holder->prepare('SELECT GET_LOCK(%s, 0)', OrderLock::lockName($ref)));
        $this->assertSame('1', (string) $taken, 'The test could not take the lock it needs to hold.');

        return $holder;
    }

    private function releaseTheLock(wpdb $holder, string $ref): void
    {
        $holder->get_var($holder->prepare('SELECT RELEASE_LOCK(%s)', OrderLock::lockName($ref)));
        $holder->close();
    }

    /**
     * Holds the express_ref's lock from another connection for a number of seconds, without blocking
     * this process: the holder's query runs asynchronously and releases the lock itself.
     */
    private function holdTheLockBriefly(string $ref, int $seconds): wpdb
    {
        $holder = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $dbh = $holder->dbh;
        $this->assertInstanceOf(\mysqli::class, $dbh);
        $name = $dbh->real_escape_string(OrderLock::lockName($ref));
        $dbh->query("SELECT GET_LOCK('{$name}', 0), SLEEP({$seconds}), RELEASE_LOCK('{$name}')", MYSQLI_ASYNC);
        // Give the holder time to take the lock before this process asks for it.
        usleep(200000);

        return $holder;
    }

    private function finishHolding(wpdb $holder): void
    {
        $dbh = $holder->dbh;
        $result = $dbh->reap_async_query();
        if ($result instanceof \mysqli_result) {
            $row = $result->fetch_row();
            $this->assertSame('1', (string) ($row[0] ?? ''), 'The holder never took the lock, so nothing contended.');
            $result->free();
        }
        $holder->close();
    }
}
