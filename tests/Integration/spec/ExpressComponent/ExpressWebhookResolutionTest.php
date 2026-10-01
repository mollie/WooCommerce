<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mockery;
use Mollie\WooCommerce\Adapter\WordPress\OrderLock;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\Webhooks\WebhookHandler;
use Mollie\WooCommerce\Workflow\ResolveExpressPayment;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WC_Order;
use wpdb;

/**
 * The webhook of a payment the plugin never created (REQ-D1, D3, D4, D5, D6, D7, F2, G4, G5, G6;
 * AC-20 to AC-26, AC-33, AC-38, AC-39, AC-40).
 *
 * Mollie creates the express payment from the session, so when its webhook arrives the order knows
 * neither a transaction id nor _mollie_payment_id, and the session's redirectUrl carries an
 * express_ref instead of an order id. One new stage — ResolveExpressPayment, between the two indexed
 * lookups and the redirectUrl fallback, on both webhook paths — fetches the payment, finds the order
 * carrying the ref in its metadata, checks the match, and writes the first-sight effects under the
 * per-order lock. The existing doPaymentForOrder() then decides the status exactly as today.
 *
 * Observed end to end: the real REST route with the shop secret, the real WooCommerce order storage,
 * the real SDK and HTTP adapter, with only Mollie faked at the HTTP layer. deliverWebhook() returns
 * the status code, which is the whole contract with Mollie's retries.
 *
 * The first three scenarios are characterisation tests of the route as it is today; they pin the
 * refactor of RestApi::callback() and setBillingAddressAfterPayment() and pass before any change.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressWebhookResolution
 */
class ExpressWebhookResolutionTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const PAYPAL_GATEWAY = 'mollie_wc_gateway_paypal';

    private const UNKNOWN_REF = 'exr_ffffffffffffffffffffffffffffffff';

    private ?ContainerInterface $container = null;

    private int $paymentCompletions = 0;

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
        $this->paymentCompletions = 0;
        $this->addTestFilter('woocommerce_payment_complete', function (): void {
            $this->paymentCompletions++;
        });
    }

    public function tearDown(): void
    {
        unset($_GET['mollie_webhook_secret'], $_GET['order_id'], $_GET['key']);
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
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Characterisation: the route as it is today
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: the route answers a request without a payment id 404, Mollie's probe 200, and an unknown id 200
     *   Given the plugin is booted and the request carries the shop secret
     *   When Mollie posts no id, then its testByMollie probe, then an id no order and no payment knows
     *   Then they are answered 404, 200 and 200
     *
     * The unknown id reaches the redirectUrl fallback, whose failed payment fetch is caught inside
     * MollieOrderService and ends in 200; the route's own 500 branch is not reachable through it today.
     *
     * @test
     */
    public function it_answers_404_without_an_id_and_200_to_mollies_probe_and_an_unknown_id(): void
    {
        $this->boot();
        $route = '/mollie/v1/webhook';

        $withoutId = $this->restRequest('POST', $route, ['mollie_webhook_secret' => CanaryData::WEBHOOK_SECRET]);
        $probe = $this->restRequest('POST', $route, ['mollie_webhook_secret' => CanaryData::WEBHOOK_SECRET, 'testByMollie' => '']);
        $unknown = $this->deliverWebhook('tr_unknownToEveryone');

        $this->assertSame(404, $withoutId->get_status());
        $this->assertSame(200, $probe->get_status());
        $this->assertSame(200, $unknown);
    }

    /**
     * Scenario: a payment an order already knows resolves by the indexed lookups, transaction id first
     *   Given a pending order that knows its payment by transaction id, or only by _mollie_payment_id
     *   And another pending order whose _mollie_payment_id is the same payment, when looked up by transaction id
     *   When Mollie calls the webhook for that paid payment
     *   Then it is answered 200 and the order found first is paid, the other untouched
     *   And the express stage is never reached: no express event is logged
     *
     * @test
     * @dataProvider indexedLookups
     */
    public function it_looks_up_by_transaction_id_before_mollie_meta_and_skips_the_express_stage(string $knownBy): void
    {
        $this->boot();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $payment = $this->paymentFor($order, ['status' => 'paid', 'method' => 'ideal']);
        $bystander = null;
        if ($knownBy === 'transaction_id') {
            $order->set_transaction_id($payment['id']);
            $bystander = $this->pendingOrder('mollie_wc_gateway_ideal');
            $bystander->update_meta_data('_mollie_payment_id', $payment['id']);
            $bystander->save();
        } else {
            $order->update_meta_data('_mollie_payment_id', $payment['id']);
        }
        $order->save();

        $status = $this->deliverWebhook($payment['id']);

        $this->assertSame(200, $status);
        $this->assertTrue($this->fresh($order)->is_paid());
        if ($bystander !== null) {
            $this->assertSame('pending', $this->fresh($bystander)->get_status(), 'The transaction-id stage must win.');
        }
        $this->assertSame([], $this->loggedEvents('express.payment.matched'));
        $this->assertSame([], $this->loggedEvents('express.webhook.unmatched'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function indexedLookups(): array
    {
        return [
            'known by transaction id' => ['transaction_id'],
            'known by _mollie_payment_id only' => ['_mollie_payment_id'],
        ];
    }

    /**
     * Scenario: the PayPal button's billing writeback takes name, email and street from the payment
     *   Given a pending PayPal button order that knows its payment
     *   And the paid PayPal payment carries a billing address
     *   When Mollie calls the webhook
     *   Then the order's billing name, email, first address line, city, postcode and country are the payment's
     *
     * @test
     */
    public function it_pins_the_paypal_button_billing_mapping(): void
    {
        $this->boot();
        $order = $this->payPalButtonOrder();
        $payment = $this->paymentFor($order, ['status' => 'paid', 'method' => 'paypal', 'billingAddress' => CanaryData::mollieAddress()]);
        $this->knows($order, $payment['id']);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $billing = $this->fresh($order)->get_address('billing');
        $this->assertSame(CanaryData::GIVEN_NAME, $billing['first_name']);
        $this->assertSame(CanaryData::FAMILY_NAME, $billing['last_name']);
        $this->assertSame(CanaryData::EMAIL, $billing['email']);
        $this->assertSame(CanaryData::STREET, $billing['address_1']);
        $this->assertSame('Amsterdam', $billing['city']);
        $this->assertSame('1015 CS', $billing['postcode']);
        $this->assertSame('NL', $billing['country']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Resolving an express payment
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: a paid express payment pays the pending order carrying its express_ref
     *   Given a pending express order created at submit that knows no payment id
     *   And Mollie created a paid payment from its session, whose metadata carries the order's express_ref
     *   When Mollie calls the webhook with the secret
     *   Then it is answered 200
     *   And the order is paid
     *   And express.payment.matched is logged once for this order and payment
     *
     * @test
     */
    public function it_pays_a_pending_express_order_found_by_the_payments_express_ref(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $this->assertSame('', $order->get_transaction_id());
        $this->assertSame('', (string) $order->get_meta('_mollie_payment_id'));
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);

        $status = $this->deliverWebhook($payment['id']);

        $this->assertSame(200, $status);
        $this->assertTrue($this->fresh($order)->is_paid(), 'The express order must be paid by the webhook.');
        $matched = $this->loggedEvents('express.payment.matched');
        $this->assertCount(1, $matched);
        $this->assertSame($order->get_id(), (int) $matched[0]['context']['order']);
        $this->assertSame($payment['id'], $matched[0]['context']['mollie_id']);
    }

    /**
     * Scenario: the first resolution records the payment and gives the order the wallet's payment method
     *   Given a pending express order and a paid payment from its session, made with Apple Pay or PayPal
     *   When Mollie calls the webhook
     *   Then the order's _mollie_payment_id and transaction id are the payment id
     *   And its _mollie_payment_mode is the payment's mode
     *   And its payment method is the plugin's method for that wallet, with that method's title
     *
     * @test
     * @dataProvider wallets
     */
    public function it_records_the_payment_and_the_method_of_the_wallet_that_paid(string $mollieMethod, string $expectedGateway): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => $mollieMethod]);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $paid = $this->fresh($order);
        $this->assertSame($payment['id'], (string) $paid->get_meta('_mollie_payment_id'));
        $this->assertSame($payment['id'], $paid->get_transaction_id());
        $this->assertSame($payment['mode'], (string) $paid->get_meta('_mollie_payment_mode'));
        $this->assertSame('live', $payment['mode'], 'The harness runs live; a test-mode payment would prove less.');
        $this->assertSame($expectedGateway, $paid->get_payment_method());
        $this->assertSame(
            WC()->payment_gateways()->payment_gateways()[$expectedGateway]->get_title(),
            $paid->get_payment_method_title()
        );
        $this->assertTrue($paid->is_paid());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function wallets(): array
    {
        return [
            'Apple Pay' => ['applepay', 'mollie_wc_gateway_applepay'],
            'PayPal' => ['paypal', self::PAYPAL_GATEWAY],
        ];
    }

    /**
     * Scenario: a Mollie method without a wallet payment method keeps the provisional one, is noted, and is paid
     *   Given a pending express order whose provisional payment method is PayPal
     *   And a paid payment from its session made with a method that has no wallet row, or no registered payment method
     *   When Mollie calls the webhook
     *   Then the order keeps the provisional payment method
     *   And it has a note naming the Mollie method
     *   And it is paid
     *
     * @test
     * @dataProvider methodsWithoutAWalletPaymentMethod
     */
    public function it_keeps_the_provisional_method_when_the_wallet_has_no_payment_method(string $mollieMethod): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $provisional = $order->get_payment_method();
        $this->assertSame(self::PAYPAL_GATEWAY, $provisional);
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => $mollieMethod]);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $paid = $this->fresh($order);
        $this->assertSame($provisional, $paid->get_payment_method());
        $this->assertNotSame([], $this->notesContaining($paid, $mollieMethod), "A note must name the Mollie method '{$mollieMethod}'.");
        $this->assertTrue($paid->is_paid(), 'A paid order must never be left unfulfilled over a label.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function methodsWithoutAWalletPaymentMethod(): array
    {
        return [
            'no row in the wallets table' => ['creditcard'],
            'a row, but no registered payment method' => ['googlepay'],
        ];
    }

    /**
     * Scenario: a repeated webhook resolves by transaction id and completes nothing twice
     *   Given an express order that the first webhook for its payment resolved and paid
     *   When Mollie calls the webhook for the same payment again
     *   Then it is answered 200
     *   And it fetches the payment exactly as often as the repeated webhook of an ordinary paid order
     *     known by transaction id: the express stage did not run
     *   And the payment was completed once, and the order got no further note
     *
     * @test
     */
    public function it_resolves_a_repeated_webhook_by_transaction_id_without_the_express_stage(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $this->assertTrue($this->fresh($order)->is_paid());
        $this->assertSame(1, $this->paymentCompletions);
        $notesAfterFirst = $this->notes($order);
        $control = $this->pendingOrder(self::PAYPAL_GATEWAY);
        $controlPayment = $this->paymentFor($control, ['status' => 'paid', 'method' => 'paypal']);
        $this->knows($control, $controlPayment['id']);
        $this->assertSame(200, $this->deliverWebhook($controlPayment['id']));
        $this->assertTrue($this->fresh($control)->is_paid());

        $controlBefore = $this->paymentFetches($controlPayment['id']);
        $this->assertSame(200, $this->deliverWebhook($controlPayment['id']));
        $controlFetches = $this->paymentFetches($controlPayment['id']) - $controlBefore;
        $before = $this->paymentFetches($payment['id']);
        $completionsBefore = $this->paymentCompletions;
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $secondFetches = $this->paymentFetches($payment['id']) - $before;

        $this->assertSame($controlFetches, $secondFetches, 'A repeated webhook must not fetch the payment for the express stage.');
        $this->assertSame($completionsBefore, $this->paymentCompletions, 'The repeated webhook must not complete the payment again.');
        $this->assertSame($notesAfterFirst, $this->notes($order));
        $this->assertCount(1, $this->loggedEvents('express.payment.matched'));
    }

    /**
     * Scenario: a payment that does not match its order is refused and the order is untouched
     *   Given a pending express order and a paid payment from its session
     *   And the payment and the order differ in one way: no ref in the metadata, a ref no order carries,
     *     an order not created via mollie_express, an amount that differs, or an order tracking another payment
     *   When Mollie calls the webhook
     *   Then it is answered 200
     *   And the order's status, meta and notes are exactly as before
     *   And express.webhook.unmatched is logged with that case's reason
     *
     * @test
     * @dataProvider mismatches
     */
    public function it_refuses_a_mismatched_payment_and_touches_nothing(string $case, string $expectedReason): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $outcome = ['status' => 'paid', 'method' => 'paypal'];
        if ($case === 'amount') {
            $outcome['amount'] = ['currency' => 'EUR', 'value' => $this->decimal((float) $order->get_total() + 0.01)];
        }
        $payment = $this->fakeMollie()->completeSession($sessionId, $outcome);
        switch ($case) {
            case 'no ref':
                $this->fakeMollie()->setPaymentStatus($payment['id'], 'paid', ['metadata' => ['source' => 'elsewhere']]);
                break;
            case 'unknown ref':
                $this->fakeMollie()->setPaymentStatus($payment['id'], 'paid', ['metadata' => ['express_ref' => self::UNKNOWN_REF]]);
                break;
            case 'not express':
                $order->set_created_via('checkout');
                $order->save();
                break;
            case 'other payment':
                $order->update_meta_data('_mollie_payment_id', 'tr_anotherAttempt');
                $order->save();
                break;
        }
        $state = $this->state($order);
        $notes = $this->notes($order);

        $status = $this->deliverWebhook($payment['id']);

        $this->assertSame(200, $status);
        $this->assertSame($state, $this->state($order));
        $this->assertSame($notes, $this->notes($order));
        $this->assertFalse($this->fresh($order)->is_paid());
        $unmatched = $this->loggedEvents('express.webhook.unmatched');
        $this->assertCount(1, $unmatched);
        $this->assertSame($expectedReason, $unmatched[0]['context']['reason'] ?? null);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function mismatches(): array
    {
        return [
            'metadata without a ref' => ['no ref', 'missing_ref'],
            'a ref no order carries' => ['unknown ref', 'unknown_ref'],
            'an order not created via mollie_express' => ['not express', 'not_express'],
            'an amount that differs from the order total' => ['amount', 'amount_mismatch'],
            'an order already tracking a different payment' => ['other payment', 'other_payment'],
        ];
    }

    /**
     * Scenario: payment metadata naming an ordinary pending order does not pay it
     *   Given an ordinary pending order placed through the classic checkout
     *   And a paid payment whose metadata names that order by id, or carries a ref that order was given
     *   When Mollie calls the webhook
     *   Then it is answered 200
     *   And the ordinary order is still pending, unpaid, with no payment id
     *
     * @test
     * @dataProvider ordinaryOrderPointers
     */
    public function it_does_not_pay_an_ordinary_order_named_by_the_payment(string $pointer): void
    {
        [, , $sessionId] = $this->expressOrder();
        $ordinary = $this->pendingOrder(self::PAYPAL_GATEWAY);
        $this->assertNotSame('mollie_express', $ordinary->get_created_via());
        $payment = $this->fakeMollie()->completeSession($sessionId, [
            'status' => 'paid',
            'method' => 'paypal',
            'amount' => ['currency' => $ordinary->get_currency(), 'value' => $this->formattedTotal($ordinary)],
        ]);
        if ($pointer === 'order id') {
            $metadata = ['order_id' => $ordinary->get_id()];
        } else {
            $ordinary->update_meta_data('_mollie_express_ref', self::UNKNOWN_REF);
            $ordinary->save();
            $metadata = ['express_ref' => self::UNKNOWN_REF];
        }
        $this->fakeMollie()->setPaymentStatus($payment['id'], 'paid', ['metadata' => $metadata]);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($ordinary);
        $this->assertSame('pending', $after->get_status());
        $this->assertFalse($after->is_paid());
        $this->assertSame('', (string) $after->get_meta('_mollie_payment_id'));
        $this->assertSame(0, $this->paymentCompletions);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function ordinaryOrderPointers(): array
    {
        return [
            'metadata names the order id' => ['order id'],
            'the ordinary order carries the metadata ref' => ['ref'],
        ];
    }

    /**
     * Scenario: a webhook no stage can resolve is answered 200 and logged with ids only
     *   Given a paid express payment whose ref no order carries
     *   When Mollie calls the webhook
     *   Then it is answered 200, so Mollie stops retrying
     *   And express.webhook.unmatched is logged once, as a warning, with the reason and the payment id
     *   And with no field other than the correlation id, the payment id and the reason
     *
     * @test
     */
    public function it_answers_200_and_logs_unmatched_with_ids_only(): void
    {
        [, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);
        $this->fakeMollie()->setPaymentStatus($payment['id'], 'paid', ['metadata' => ['express_ref' => self::UNKNOWN_REF]]);

        $status = $this->deliverWebhook($payment['id']);

        $this->assertSame(200, $status);
        $unmatched = $this->loggedEvents('express.webhook.unmatched');
        $this->assertCount(1, $unmatched);
        $this->assertSame('warning', $unmatched[0]['level']);
        $context = $unmatched[0]['context'];
        $this->assertSame($payment['id'], $context['mollie_id'] ?? null);
        $this->assertSame('unknown_ref', $context['reason'] ?? null);
        $this->assertSame([], array_diff(array_keys($context), ['cid', 'mollie_id', 'reason']), 'Only ids and the reason may be logged.');
    }

    /**
     * Scenario: a failed, cancelled or expired express payment leaves the order unpaid, knowing the payment
     *   Given a pending express order
     *   And the payment from its session failed, was canceled or expired
     *   When Mollie calls the webhook
     *   Then it is answered 200
     *   And the order is not paid and was never completed
     *   And its _mollie_payment_id and transaction id are the payment id
     *
     * @test
     * @dataProvider unsuccessfulStatuses
     */
    public function it_leaves_a_failed_cancelled_or_expired_payment_unpaid(string $paymentStatus): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => $paymentStatus, 'method' => 'paypal']);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertFalse($after->is_paid());
        $this->assertNull($after->get_date_paid());
        $this->assertSame(0, $this->paymentCompletions);
        $this->assertSame($payment['id'], (string) $after->get_meta('_mollie_payment_id'));
        $this->assertSame($payment['id'], $after->get_transaction_id(), 'The first sight records the transaction id, not payment_complete().');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function unsuccessfulStatuses(): array
    {
        return [
            'failed' => ['failed'],
            'canceled' => ['canceled'],
            'expired' => ['expired'],
        ];
    }

    /**
     * Scenario: a guest who paid without filling the billing form gets the wallet's billing details
     *   Given a guest express order with a shipping address from the form and no billing details or email
     *   And the paid payment carries the wallet's billing address, with region, phone and a second address line
     *   When Mollie calls the webhook
     *   Then the order's billing address and email are the payment's, region as state
     *   And its shipping address is unchanged
     *
     * @test
     */
    public function it_writes_the_wallets_billing_details_to_a_guest_order_that_had_none(): void
    {
        [$order, , $sessionId] = $this->expressOrder([]);
        $this->assertSame('', $order->get_billing_email());
        $this->assertSame('', $order->get_billing_address_1());
        $shipping = $order->get_address('shipping');
        $payment = $this->fakeMollie()->completeSession($sessionId, [
            'status' => 'paid',
            'method' => 'paypal',
            'billingAddress' => CanaryData::mollieAddress(),
        ]);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertSame(CanaryData::EMAIL, $after->get_billing_email());
        $this->assertSame(CanaryData::GIVEN_NAME, $after->get_billing_first_name());
        $this->assertSame(CanaryData::FAMILY_NAME, $after->get_billing_last_name());
        $this->assertSame(CanaryData::STREET, $after->get_billing_address_1());
        $this->assertSame(CanaryData::STREET_ADDITIONAL, $after->get_billing_address_2());
        $this->assertSame(CanaryData::PHONE, $after->get_billing_phone());
        $this->assertSame('Noord-Holland', $after->get_billing_state());
        $this->assertSame('1015 CS', $after->get_billing_postcode());
        $this->assertSame('Amsterdam', $after->get_billing_city());
        $this->assertSame('NL', $after->get_billing_country());
        $this->assertSame($shipping, $after->get_address('shipping'));
        $this->assertTrue($after->is_paid());
    }

    /**
     * Scenario: the addresses the store held are kept, and a shipping order's shipping address never changes
     *   Given an express order whose billing and shipping came from the guest's checkout form, or from the account
     *   And the paid payment carries a different billing and a different shipping address
     *   When Mollie calls the webhook
     *   Then the order's billing and shipping addresses are exactly as they were
     *   And the order is paid
     *
     * @test
     * @dataProvider heldAddresses
     */
    public function it_keeps_the_stores_addresses_and_a_shipping_orders_shipping_address(string $source): void
    {
        [$order, , $sessionId] = $source === 'account' ? $this->expressOrderForTheAccount() : $this->expressOrder();
        $this->assertTrue($order->needs_shipping_address(), 'The scenario needs an order with something to ship.');
        $billing = $order->get_address('billing');
        $shipping = $order->get_address('shipping');
        $this->assertNotSame('', $billing['address_1']);
        $payment = $this->fakeMollie()->completeSession($sessionId, [
            'status' => 'paid',
            'method' => 'paypal',
            'billingAddress' => CanaryData::mollieAddress(['city' => 'Rotterdam', 'email' => 'other.CANARY7f3a@example.org']),
            'shippingAddress' => CanaryData::mollieAddress(['city' => 'Utrecht']),
        ]);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertSame($billing, $after->get_address('billing'));
        $this->assertSame($shipping, $after->get_address('shipping'));
        $this->assertTrue($after->is_paid());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function heldAddresses(): array
    {
        return [
            'a guest who filled the checkout form' => ['form'],
            'a logged-in customer with an account address' => ['account'],
        ];
    }

    /**
     * Scenario: an order that needs shipping never takes the wallet's shipping address, even when it holds none
     *   Given an express order with something to ship whose shipping address is empty
     *   And the paid payment carries a shipping address from the wallet
     *   When Mollie calls the webhook
     *   Then the order's shipping address is still empty: its shipping cost was not calculated for the wallet's
     *   And the order is paid
     *
     * @test
     */
    public function it_never_writes_the_wallets_shipping_address_on_an_order_that_needs_shipping(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        foreach (['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone'] as $field) {
            $order->{"set_shipping_{$field}"}('');
        }
        $order->save();
        $before = $this->fresh($order);
        $this->assertTrue($before->needs_shipping_address(), 'The scenario needs an order with something to ship.');
        $this->assertSame('', $before->get_shipping_address_1());
        $shipping = $before->get_address('shipping');
        $payment = $this->fakeMollie()->completeSession($sessionId, [
            'status' => 'paid',
            'method' => 'applepay',
            'shippingAddress' => CanaryData::mollieAddress(['city' => 'Utrecht']),
        ]);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertSame($shipping, $after->get_address('shipping'));
        $this->assertTrue($after->is_paid());
    }

    /**
     * Scenario: a second resolution of the same first sight, run after the first waited for the lock, writes nothing again
     *   Given a pending express order and a paid payment from its session
     *   And another connection holds the order's lock for one second
     *   When Mollie calls the webhook, which waits for the lock and then resolves the payment
     *   And a second resolution of the same payment runs the same rails afterwards, as a return would
     *   Then the webhook waited and was answered 200
     *   And the order has one _mollie_payment_id, the effects were applied once, the payment was completed once
     *   And the second resolution added no note
     *
     * @test
     */
    public function it_converges_on_one_write_when_a_second_resolution_waits_for_the_order_lock(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);

        $holder = $this->holdTheLockBriefly((string) $order->get_id(), 1);
        $started = microtime(true);
        $status = $this->deliverWebhook($payment['id']);
        $waited = microtime(true) - $started;
        $this->finishHolding($holder);
        $notesAfterWebhook = $this->notes($order);

        $resolved = $this->container->get(ResolveExpressPayment::class)->resolve($payment['id']);
        $this->assertInstanceOf(WC_Order::class, $resolved);
        $this->container->get(MollieOrderService::class)->doPaymentForOrder($this->fresh($order));

        $this->assertGreaterThan(0.5, $waited, 'The webhook must have waited for the lock, or the scenario did not contend.');
        $this->assertSame(200, $status);
        $after = $this->fresh($order);
        $this->assertCount(1, $after->get_meta('_mollie_payment_id', false));
        $this->assertCount(1, $this->effectsAppliedTo($order));
        $this->assertSame(1, $this->paymentCompletions);
        $this->assertSame($notesAfterWebhook, $this->notes($order));
        $this->assertTrue($after->is_paid());
    }

    /**
     * Scenario: a webhook that cannot take the order lock is answered 503 and writes nothing, and Mollie's retry pays
     *   Given a pending express order and a paid payment from its session
     *   And another connection holds the order's lock for longer than the lock timeout
     *   When Mollie calls the webhook
     *   Then it is answered 503, so Mollie retries
     *   And the order has no payment id and is not paid
     *   And once the lock is free, the retried webhook is answered 200 and pays the order
     *
     * @test
     */
    public function it_answers_503_and_writes_nothing_while_the_order_lock_stays_taken(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);

        $holder = $this->holdTheLock((string) $order->get_id());
        try {
            $status = $this->deliverWebhook($payment['id']);
        } finally {
            $this->releaseTheLock($holder, (string) $order->get_id());
        }

        $this->assertSame(503, $status);
        $blocked = $this->fresh($order);
        $this->assertSame('', (string) $blocked->get_meta('_mollie_payment_id'));
        $this->assertSame('', $blocked->get_transaction_id());
        $this->assertFalse($blocked->is_paid());

        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $this->assertTrue($this->fresh($order)->is_paid());
    }

    /**
     * Scenario: the legacy WC-API webhook path resolves an express payment through the same stage
     *   Given a pending express order and a paid payment from its session
     *   When Mollie calls the WC-API webhook with the secret, the order's id and key, and the payment id
     *   Then the order is paid, with the payment id and the wallet's payment method, as on the REST path
     *   And express.payment.matched is logged for it
     *
     * @test
     */
    public function it_resolves_an_express_payment_on_the_legacy_wc_api_path(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'applepay']);
        $_GET['mollie_webhook_secret'] = CanaryData::WEBHOOK_SECRET;
        $_GET['order_id'] = (string) $order->get_id();
        $_GET['key'] = $order->get_order_key();

        $this->legacyWebhookService($payment['id'])->onWebhookAction();

        $after = $this->fresh($order);
        $this->assertTrue($after->is_paid());
        $this->assertSame($payment['id'], (string) $after->get_meta('_mollie_payment_id'));
        $this->assertSame($payment['id'], $after->get_transaction_id());
        $this->assertSame('mollie_wc_gateway_applepay', $after->get_payment_method());
        $this->assertCount(1, $this->loggedEvents('express.payment.matched'));
    }

    /**
     * Scenario: the PayPal button writeback keeps what Mollie supplies and no longer blanks what it does not
     *   Given a pending PayPal button order holding a billing state
     *   And the paid PayPal payment carries a phone and a second address line, and no region
     *   When Mollie calls the webhook
     *   Then the order's billing phone and second address line are the payment's
     *   And its billing state is the one it held
     *
     * @test
     */
    public function it_writes_phone_and_second_line_for_a_paypal_button_order(): void
    {
        $this->boot();
        $order = $this->payPalButtonOrder();
        $order->set_billing_state('LU-L');
        $order->save();
        $billingAddress = CanaryData::mollieAddress();
        unset($billingAddress['region']);
        $payment = $this->paymentFor($order, ['status' => 'paid', 'method' => 'paypal', 'billingAddress' => $billingAddress]);
        $this->knows($order, $payment['id']);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertSame(CanaryData::PHONE, $after->get_billing_phone());
        $this->assertSame(CanaryData::STREET_ADDITIONAL, $after->get_billing_address_2());
        $this->assertSame('LU-L', $after->get_billing_state());
    }

    /**
     * Scenario: a full webhook resolution with canary data leaves no canary in the log
     *   Given the harness's canary keys and webhook secret
     *   And a guest express order paid with canary billing and shipping addresses
     *   When Mollie calls the webhook, with debug logging on
     *   Then the order is paid
     *   And no key, secret, name, email, phone or street reached the log
     *
     * @test
     */
    public function it_leaks_no_canary_during_a_full_webhook_resolution(): void
    {
        [$order, , $sessionId] = $this->expressOrder([]);
        $this->logger()->reset();
        $payment = $this->fakeMollie()->completeSession($sessionId, [
            'status' => 'paid',
            'method' => 'applepay',
            'billingAddress' => CanaryData::mollieAddress(),
            'shippingAddress' => CanaryData::mollieAddress(['city' => 'Rotterdam']),
        ]);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $this->assertTrue($this->fresh($order)->is_paid());
        $this->assertNotSame([], $this->loggedEvents('express.payment.matched'), 'The resolution must have run.');
        $this->assertNothingLeakedToLog();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function boot(): ContainerInterface
    {
        if ($this->container === null) {
            $this->container = $this->bootExpress();
        }

        return $this->container;
    }

    /**
     * An express order created the way the browser creates it: a started session, then submit. A
     * guest with a cart that ships to LU on the 'standard' rate; the billing form is the fixture's
     * unless given.
     *
     * @param array<string, string>|null $billing The billing form; [] for a guest who filled none.
     * @return array{0: WC_Order, 1: string, 2: string} The order, its express_ref, its session id.
     */
    private function expressOrder(?array $billing = null): array
    {
        $this->boot();
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm($billing ?? $this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');

        return $this->submittedOrder();
    }

    /**
     * An express order of the fixture customer, whose billing address comes from the account and
     * whose shipping address was chosen on the checkout.
     *
     * @return array{0: WC_Order, 1: string, 2: string}
     */
    private function expressOrderForTheAccount(): array
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

        $this->boot();
        $this->actAsCustomer();
        WC()->customer = new \WC_Customer(get_current_user_id(), true);
        $this->cartWith(['simple'], 2);
        foreach ($this->shipping('LU') as $field => $value) {
            WC()->customer->{"set_shipping_{$field}"}($value);
        }
        WC()->customer->save();
        $this->chooseRate('standard');

        return $this->submittedOrder();
    }

    /**
     * @return array{0: WC_Order, 1: string, 2: string}
     */
    private function submittedOrder(): array
    {
        $session = $this->startedSession();
        $this->assertAnsweredOk($this->startOrder());
        $this->logger()->reset();

        return [$this->onlyOrderFor($session['ref']), $session['ref'], $session['id']];
    }

    /**
     * A payment at the fake Mollie for an order the plugin did not create through express: a session
     * for the order's total, completed with the given outcome.
     *
     * @param array<string, mixed> $outcome
     * @return array<string, mixed>
     */
    private function paymentFor(WC_Order $order, array $outcome): array
    {
        $total = $this->formattedTotal($order);
        $payload = [
            'amount' => ['currency' => 'EUR', 'value' => $total],
            'description' => 'Order ' . $order->get_id(),
            'lines' => [[
                'description' => 'Everything',
                'quantity' => 1,
                'unitPrice' => ['currency' => 'EUR', 'value' => $total],
                'totalAmount' => ['currency' => 'EUR', 'value' => $total],
            ]],
            'redirectUrl' => 'https://shop.example/checkout/order-received/',
            'payment' => ['webhookUrl' => 'https://shop.example/wp-json/mollie/v1/webhook'],
            'metadata' => ['order_id' => $order->get_id()],
        ];
        $client = $this->boot()->get('SDK.api_helper')->getApiClient(CanaryData::LIVE_API_KEY);
        $session = $client->performHttpCall('POST', 'sessions', (string) wp_json_encode($payload));

        return $this->fakeMollie()->completeSession($session->id, $outcome);
    }

    private function knows(WC_Order $order, string $paymentId): void
    {
        $order->update_meta_data('_mollie_payment_id', $paymentId);
        $order->set_transaction_id($paymentId);
        $order->save();
    }

    private function payPalButtonOrder(): WC_Order
    {
        $order = $this->pendingOrder(self::PAYPAL_GATEWAY);
        $order->update_meta_data('_mollie_payment_method_button', 'PayPalButton');
        $order->save();

        return $order;
    }

    /**
     * MollieOrderService with only its request-reading seam replaced: filter_input(INPUT_POST) is
     * always empty on the CLI. Everything else, the express stage included, is the real service.
     */
    private function legacyWebhookService(string $paymentId): MollieOrderService
    {
        $container = $this->boot();
        $service = Mockery::mock(MollieOrderService::class, [
            $container->get('SDK.HttpResponse'),
            $container->get(LoggerInterface::class),
            $container->get(PaymentFactory::class),
            $container->get('settings.data_helper'),
            $container->get('shared.plugin_id'),
            $container,
            $container->get(WebhookHandler::class),
            $container->get(ResolveExpressPayment::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('getPaymentIdFromRequest')->andReturn($paymentId);

        return $service;
    }

    private function fresh(WC_Order $order): WC_Order
    {
        clean_post_cache($order->get_id());
        wp_cache_delete(WC_Order::generate_meta_cache_key($order->get_id(), 'orders'), 'orders');
        $fresh = wc_get_order($order->get_id());
        $this->assertInstanceOf(WC_Order::class, $fresh);

        return $fresh;
    }

    /**
     * The status and every meta value, read fresh.
     *
     * @return array{status: string, meta: array<string, mixed>}
     */
    private function state(WC_Order $order): array
    {
        $fresh = $this->fresh($order);
        $meta = [];
        foreach ($fresh->get_meta_data() as $item) {
            $data = $item->get_data();
            $meta[$data['key']][] = $data['value'];
        }
        ksort($meta);

        return [
            'status' => $fresh->get_status(),
            'transaction_id' => $fresh->get_transaction_id(),
            'payment_method' => $fresh->get_payment_method(),
            'meta' => $meta,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function notes(WC_Order $order): array
    {
        return array_map(static function ($note): string {
            return (string) $note->content;
        }, wc_get_order_notes(['order_id' => $order->get_id()]));
    }

    /**
     * @return array<int, string>
     */
    private function notesContaining(WC_Order $order, string $needle): array
    {
        return array_values(array_filter($this->notes($order), static function (string $note) use ($needle): bool {
            return stripos($note, $needle) !== false;
        }));
    }

    private function paymentFetches(string $paymentId): int
    {
        return count(array_filter($this->fakeMollie()->requests('GET', 'payments/' . $paymentId), static function (array $request) use ($paymentId): bool {
            return $request['path'] === 'payments/' . $paymentId;
        }));
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<mixed>}>
     */
    private function effectsAppliedTo(WC_Order $order): array
    {
        return array_values(array_filter($this->loggedEvents('effects.applied'), static function (array $record) use ($order): bool {
            return (int) ($record['context']['order'] ?? 0) === $order->get_id();
        }));
    }

    /**
     * Holds the order's lock from another connection until releaseTheLock().
     */
    private function holdTheLock(string $orderKey): wpdb
    {
        $holder = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $taken = $holder->get_var($holder->prepare('SELECT GET_LOCK(%s, 0)', OrderLock::lockName($orderKey)));
        $this->assertSame('1', (string) $taken, 'The test could not take the lock it needs to hold.');

        return $holder;
    }

    private function releaseTheLock(wpdb $holder, string $orderKey): void
    {
        $holder->get_var($holder->prepare('SELECT RELEASE_LOCK(%s)', OrderLock::lockName($orderKey)));
        $holder->close();
    }

    /**
     * Holds the order's lock from another connection for a number of seconds, without blocking this
     * process: the holder's query runs asynchronously and releases the lock itself.
     */
    private function holdTheLockBriefly(string $orderKey, int $seconds): wpdb
    {
        $holder = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $dbh = $holder->dbh;
        $this->assertInstanceOf(\mysqli::class, $dbh);
        $name = $dbh->real_escape_string(OrderLock::lockName($orderKey));
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
