<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Mockery;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Payment\OrderLock;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\OrphanedExpressPayments;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\ProcessRecordStore;
use Mollie\WooCommerce\Payment\Rules\Values\ProcessRecord;
use Mollie\WooCommerce\Payment\Webhooks\WebhookHandler;
use Mollie\WooCommerce\ExpressComponent\Flow\ResolveExpressPayment;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WC_Order;
use wpdb;

/**
 * The webhook of an express payment, which Mollie created from the session: resolving it to the
 * order carrying its express_ref, end to end with only Mollie faked at the HTTP layer.
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
     * Run once, just before the next GET_LOCK of the plugin's OrderLock: what another process did
     * while this one was on its way to the lock.
     *
     * @var (callable(): void)|null
     */
    private $beforeTheNextLock = null;

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
        $this->beforeTheNextLock = null;
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
     * Scenario: a payment id beyond letters and digits is still resolved
     *   Given a pending express order
     *   And Mollie created its paid payment with an id that only keeps the documented tr_ prefix
     *   When Mollie calls the webhook
     *   Then the order is paid and tracks that payment, as for any other id
     *
     * Mollie documents payment ids as ^tr_.+$, nothing narrower.
     *
     * @test
     * @dataProvider paymentIdsBeyondLettersAndDigits
     */
    public function it_pays_an_express_order_whose_payment_id_is_not_only_letters_and_digits(string $paymentId): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal', 'id' => $paymentId]);

        $status = $this->deliverWebhook($payment['id']);

        $this->assertSame(200, $status);
        $paid = $this->fresh($order);
        $this->assertTrue($paid->is_paid(), 'The express order must be paid whatever follows tr_.');
        $this->assertSame($paymentId, (string) $paid->get_meta('_mollie_payment_id'));
        $this->assertSame([], $this->loggedEvents('express.webhook.unmatched'));
    }

    /**
     * Scenario: a paid express payment Mollie could not be asked about is retried, not dropped
     *   Given a pending express order and its paid payment
     *   And Mollie fails once, with an outage or a rate limit, when the express stage asks for the payment
     *   When Mollie calls the webhook
     *   Then it is answered 503, so Mollie delivers it again, and the failure is logged without Mollie's text
     *   And the next delivery pays the order
     *
     * A payment Mollie answers 404 for still goes on to the redirectUrl fallback: see the unknown id above.
     *
     * @test
     * @dataProvider mollieFailuresWorthARetry
     */
    public function it_has_mollie_retry_a_webhook_whose_payment_could_not_be_fetched(int $mollieStatus, string $kind): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);
        $this->fakeMollie()->failNext('GET', 'payments/' . $payment['id'], $mollieStatus);

        $first = $this->deliverWebhook($payment['id']);

        $this->assertFalse($this->fresh($order)->is_paid(), 'Nothing is known about the payment yet.');
        $this->assertSame(503, $first, 'A 200 tells Mollie to stop: the paid payment would never reach its order.');
        $failed = $this->loggedEvents('webhook.failed');
        $this->assertCount(1, $failed);
        $this->assertSame(['cid', 'kind', 'mollie_id'], $this->sortedKeys($failed[0]['context']));
        $this->assertSame($kind, $failed[0]['context']['kind']);
        $this->assertSame([], $this->loggedEvents('express.webhook.unmatched'));
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $this->assertTrue($this->fresh($order)->is_paid(), 'The retried delivery must pay the order.');
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public function mollieFailuresWorthARetry(): array
    {
        return [
            'an internal error' => [500, 'outage'],
            'unavailable' => [503, 'outage'],
            'a rate limit' => [429, 'rate_limit'],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private function sortedKeys(array $context): array
    {
        $keys = array_keys($context);
        sort($keys);

        return $keys;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function paymentIdsBeyondLettersAndDigits(): array
    {
        return [
            'a hyphen' => ['tr_fake-0001'],
            'an underscore' => ['tr_fake_0001'],
            'a dot' => ['tr_fake.0001'],
        ];
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
        $this->container = $this->bootExpressOwning(['woocommerce_payment_gateways']);
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
            'no row in the wallets table' => ['ideal'],
            'googlepay, which Mollie never reports on a payment' => ['googlepay'],
            'a card payment, while no Google Pay method is registered' => ['creditcard'],
        ];
    }

    /**
     * Scenario: a payment Mollie reports as a card payment gets the Google Pay method once it exists
     *   Given Google Pay activated at Mollie and switched on
     *   And a pending express order whose provisional payment method is PayPal
     *   And a paid payment from its session that Mollie reports as creditcard
     *   When Mollie calls the webhook
     *   Then the order's payment method is Google Pay, with that method's title
     *   And it has no note about an unknown wallet
     *   And it is paid
     *
     * @test
     */
    public function it_gives_a_card_payment_the_google_pay_method_once_google_pay_exists(): void
    {
        $this->fakeMollie()->setMethods(['ideal', 'creditcard', 'banktransfer', 'paypal', 'applepay', 'googlepay']);
        $this->flushMollieMethodsCache();
        $this->setGatewaySettingsForTest('googlepay', ['enabled' => 'yes']);
        [$order, , $sessionId] = $this->expressOrder();
        $this->assertSame(self::PAYPAL_GATEWAY, $order->get_payment_method());
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'creditcard']);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $paid = $this->fresh($order);
        $this->assertSame('mollie_wc_gateway_googlepay', $paid->get_payment_method());
        $this->assertSame(
            WC()->payment_gateways()->payment_gateways()['mollie_wc_gateway_googlepay']->get_title(),
            $paid->get_payment_method_title()
        );
        $this->assertSame([], $this->notesContaining($paid, 'creditcard'), 'A Google Pay payment is not an unknown wallet.');
        $this->assertTrue($paid->is_paid());
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
     * Scenario: a mismatched payment is refused and the order's state is untouched
     *   Given a pending express order and a paid payment differing in ref, origin or amount
     *   When the webhook arrives
     *   Then 200, status and meta unchanged, unmatched logged with the reason
     *   And an order the ref found gets one note
     *
     * @test
     * @dataProvider mismatches
     */
    public function it_refuses_a_mismatched_payment_and_touches_nothing(string $case, string $expectedReason, int $newNotes): void
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
        }
        $state = $this->state($order);
        $notes = $this->notes($order);

        $status = $this->deliverWebhook($payment['id']);

        $this->assertSame(200, $status);
        $this->assertSame($state, $this->state($order));
        $this->assertCount(count($notes) + $newNotes, $this->notes($order));
        $this->assertFalse($this->fresh($order)->is_paid());
        $unmatched = $this->loggedEvents('express.webhook.unmatched');
        $this->assertCount(1, $unmatched);
        $this->assertSame($expectedReason, $unmatched[0]['context']['reason'] ?? null);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public function mismatches(): array
    {
        return [
            'metadata without a ref' => ['no ref', 'missing_ref', 0],
            'a ref no order carries' => ['unknown ref', 'unknown_ref', 0],
            'an order not created via mollie_express' => ['not express', 'not_express', 1],
            'an amount that differs from the order total' => ['amount', 'amount_mismatch', 1],
        ];
    }

    /**
     * Scenario: a second paid payment pays an order tracking an earlier unpaid one
     *   Given an order tracking an open first payment
     *   When the webhook for a paid second payment arrives
     *   Then 200, the order is paid once and tracks the second payment, no orphan
     *
     * @test
     */
    public function it_pays_the_order_with_a_second_paid_payment_while_it_tracks_an_earlier_unpaid_one(): void
    {
        [$order, $ref, $sessionId] = $this->expressOrder();
        $first = $this->tracksAnOpenFirstPayment($order, $sessionId);
        $second = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame($ref, $second['metadata']['express_ref'] ?? null, 'The second payment must carry the same express_ref.');
        $completions = $this->paymentCompletions;

        $status = $this->deliverWebhook($second['id']);

        $this->assertSame(200, $status);
        $paid = $this->fresh($order);
        $this->assertTrue($paid->is_paid(), 'A paid payment for an order that still needs payment must pay it.');
        $this->assertSame($completions + 1, $this->paymentCompletions);
        $this->assertSame($second['id'], (string) $paid->get_meta('_mollie_payment_id'));
        $this->assertSame($second['id'], $paid->get_transaction_id());
        $this->assertSame([], $this->loggedEvents('express.payment.orphaned'));
        $this->assertSame([], (new OrphanedExpressPayments())->all());
    }

    /**
     * Scenario: a paid payment does not revive an order cancelled by the merchant or without a record
     *   Given a cancelled order with cancelledBy = merchant, or no record
     *   When the webhook for its paid payment arrives
     *   Then 200, still cancelled and unpaid, open question paid_after_cancel
     *   And the payment is remembered, with one note and one orphaned error
     *
     * @test
     * @dataProvider cancellersOtherThanCleanup
     */
    public function it_does_not_revive_a_paid_payment_for_an_order_cancelled_by_the_merchant_or_without_a_record(?string $canceller): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);
        $cancelled = $this->fresh($order);
        if ($canceller !== null) {
            $records = $this->boot()->get(ProcessRecordStore::class);
            $records->write($cancelled, ProcessRecord::empty()->withCancelledBy($canceller));
        }
        $cancelled->set_status('cancelled');
        $cancelled->save();
        $this->assertSame('cancelled', $this->fresh($order)->get_status());
        $notes = $this->notes($order);
        $this->logger()->reset();

        $status = $this->deliverWebhook($payment['id']);

        $this->assertSame(200, $status);
        $after = $this->fresh($order);
        $this->assertSame('cancelled', $after->get_status(), 'An order cancelled on purpose must not be revived.');
        $this->assertFalse($after->is_paid());
        $this->assertSame(0, $this->paymentCompletions);
        $record = $after->get_meta(ProcessRecordStore::META_KEY);
        $this->assertIsArray($record, 'The open question must be written to the process record.');
        $this->assertContains(
            ['question' => 'paid_after_cancel', 'mollieId' => $payment['id']],
            $record['open'] ?? []
        );
        $this->assertRememberedWithOneNoteAndOneError($order, $notes, $payment, 'paid_after_cancel');
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public function cancellersOtherThanCleanup(): array
    {
        return [
            'the merchant' => ['merchant'],
            'no record' => [null],
        ];
    }

    /**
     * Scenario: the match is decided on the order as it is under the lock
     *   Given a pending express order and a paid payment from its session
     *   And another process cancels the order just before the webhook takes the order lock
     *   When Mollie calls the webhook
     *   Then the order is still cancelled and unpaid, as for an order found cancelled
     *   And the payment is remembered as paid_after_cancel
     *
     * The outside write for this entry point (R-04): written straight to the table, so only the read
     * under the lock can see it.
     *
     * @test
     */
    public function it_does_not_revive_an_order_cancelled_while_the_webhook_waited_for_the_lock(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);
        $raced = false;
        $this->beforeTheNextLock = function () use ($order, &$raced): void {
            $this->writeStatusAsAnotherProcess($order->get_id(), 'wc-cancelled');
            $raced = true;
        };

        $status = $this->deliverWebhook($payment['id']);

        $this->assertTrue($raced, 'The webhook never asked for the lock, so nothing raced.');
        $this->assertSame(200, $status);
        $after = $this->fresh($order);
        $this->assertSame('cancelled', $after->get_status(), 'The webhook decided on the order it read before the lock.');
        $this->assertFalse($after->is_paid());
        $this->assertSame(0, $this->paymentCompletions);
        $this->assertSame('paid_after_cancel', (new OrphanedExpressPayments())->all()[$payment['id']]['reason'] ?? null);
    }

    /**
     * Scenario: a payment the order already tracks pays it after a merchant's cancel
     *   Given an express order that tracks its payment since the webhook of its open status
     *   And the merchant cancels the order
     *   When the payment is paid and Mollie calls the webhook again
     *   Then the order is paid and nothing is remembered as an orphan
     *
     * Decided in bug:944-refactor-07: row 1, "the order tracks this payment", admits before row 3b
     * looks at who cancelled. A merchant's cancel is kept only when the paid webhook is the first seen.
     *
     * @test
     */
    public function it_pays_an_order_the_merchant_cancelled_with_a_payment_the_order_already_tracks(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->tracksAnOpenFirstPayment($order, $sessionId);
        $cancelled = $this->fresh($order);
        $cancelled->set_status('cancelled');
        $cancelled->save();
        $this->fakeMollie()->setPaymentStatus($payment['id'], 'paid', ['paidAt' => gmdate('c')]);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $this->assertTrue($this->fresh($order)->is_paid());
        $this->assertSame([], (new OrphanedExpressPayments())->all());
    }

    /**
     * Scenario: a paid payment for an order another payment paid changes nothing
     *   Given an order paid by a first payment
     *   When the webhook for a paid second payment arrives
     *   Then 200, state unchanged, no second completion
     *   And the payment is remembered, with one note and one orphaned error
     *
     * @test
     */
    public function it_remembers_a_paid_payment_for_an_order_another_payment_already_paid(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        // Open keeps the session payable.
        $first = $this->fakeMollie()->completeSession($sessionId, ['status' => 'open', 'method' => 'paypal']);
        $second = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);
        $this->fakeMollie()->setPaymentStatus($first['id'], 'paid', ['paidAt' => gmdate('c')]);
        $this->assertSame(200, $this->deliverWebhook($first['id']));
        $this->assertTrue($this->fresh($order)->is_paid(), 'The first payment must have paid the order.');
        $state = $this->state($order);
        $notes = $this->notes($order);
        $completions = $this->paymentCompletions;
        $this->logger()->reset();

        $status = $this->deliverWebhook($second['id']);

        $this->assertSame(200, $status);
        $this->assertSame($state, $this->state($order));
        $this->assertSame($completions, $this->paymentCompletions);
        $this->assertRememberedWithOneNoteAndOneError($order, $notes, $second, 'other_payment');
    }

    /**
     * Scenario: a paid payment the amount guard refuses is remembered
     *   Given a pending order and a paid payment a cent over its total
     *   When the webhook arrives
     *   Then 200, state unchanged and unpaid
     *   And the payment is remembered as amount_mismatch, with one note and one orphaned error
     *
     * @test
     */
    public function it_remembers_a_paid_payment_the_amount_guard_refuses_for_an_existing_order(): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, [
            'status' => 'paid',
            'method' => 'paypal',
            'amount' => ['currency' => 'EUR', 'value' => $this->decimal((float) $order->get_total() + 0.01)],
        ]);
        $state = $this->state($order);
        $notes = $this->notes($order);

        $status = $this->deliverWebhook($payment['id']);

        $this->assertSame(200, $status);
        $this->assertSame($state, $this->state($order));
        $this->assertFalse($this->fresh($order)->is_paid());
        $this->assertSame(0, $this->paymentCompletions);
        $this->assertRememberedWithOneNoteAndOneError($order, $notes, $payment, 'amount_mismatch');
    }

    /**
     * Scenario: a dead payment for an order tracking another one is only logged
     *   Given an order tracking an open first payment
     *   When the webhook for a failed, canceled or expired second payment arrives
     *   Then 200, nothing written, unmatched other_payment, no orphan
     *
     * @test
     * @dataProvider unsuccessfulStatuses
     */
    public function it_changes_nothing_for_a_dead_payment_on_an_order_that_tracks_another(string $paymentStatus): void
    {
        [$order, , $sessionId] = $this->expressOrder();
        $this->tracksAnOpenFirstPayment($order, $sessionId);
        $dead = $this->fakeMollie()->completeSession($sessionId, ['status' => $paymentStatus, 'method' => 'paypal']);
        $state = $this->state($order);
        $notes = $this->notes($order);
        $this->logger()->reset();

        $status = $this->deliverWebhook($dead['id']);

        $this->assertSame(200, $status);
        $this->assertSame($state, $this->state($order));
        $this->assertSame($notes, $this->notes($order));
        $this->assertSame(0, $this->paymentCompletions);
        $this->assertSame([], $this->loggedEvents('order.written'), 'Nothing may be written for a dead attempt.');
        $unmatched = $this->loggedEvents('express.webhook.unmatched');
        $this->assertCount(1, $unmatched);
        $this->assertSame('other_payment', $unmatched[0]['context']['reason'] ?? null);
        $this->assertSame([], $this->loggedEvents('express.payment.orphaned'));
        $this->assertSame([], (new OrphanedExpressPayments())->all());
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
     * Scenario: a payment without a ref is not reported as a problem
     *   Given an ordinary pending order and its paid payment, which carries no express_ref
     *   And the order does not hold the payment id yet, so the indexed lookup misses
     *   When Mollie calls the webhook
     *   Then express.webhook.unmatched is logged as info with missing_ref, and no warning is logged
     *
     * @test
     */
    public function it_logs_a_payment_without_a_ref_as_information_not_as_a_problem(): void
    {
        $this->boot();
        $ordinary = $this->pendingOrder(self::PAYPAL_GATEWAY);
        $payment = $this->paymentFor($ordinary, ['status' => 'paid', 'method' => 'paypal']);
        $this->logger()->reset();

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $unmatched = $this->loggedEvents('express.webhook.unmatched');
        $this->assertCount(1, $unmatched);
        $this->assertSame(['info', 'missing_ref'], [$unmatched[0]['level'], $unmatched[0]['context']['reason'] ?? null]);
        $this->assertSame([], $this->logger()->records('warning'), 'Nothing went wrong: it is not an express payment.');
        $this->assertSame([], (new OrphanedExpressPayments())->all());
    }

    /**
     * Scenario: a captured payment that belongs to no order is put in front of a human
     *   Given a paid express payment whose ref no order carries
     *   When Mollie calls the webhook
     *   Then express.payment.orphaned is logged as an error with the payment, its amount and the reason
     *   And the payment is remembered for the admin notice, with its amount and nothing personal
     *   And the notice names the payment and is shown only to someone who can act on it
     *
     * @test
     */
    public function it_reports_a_captured_payment_that_belongs_to_no_order(): void
    {
        [, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);
        $this->fakeMollie()->setPaymentStatus($payment['id'], 'paid', ['metadata' => ['express_ref' => self::UNKNOWN_REF]]);

        $status = $this->deliverWebhook($payment['id']);

        $this->assertSame(200, $status);
        $orphaned = $this->loggedEvents('express.payment.orphaned');
        $this->assertCount(1, $orphaned, 'A captured payment with no order must be logged as an error.');
        $this->assertSame('error', $orphaned[0]['level']);
        $context = $orphaned[0]['context'];
        $this->assertSame($payment['id'], $context['mollie_id'] ?? null);
        $this->assertSame('unknown_ref', $context['reason'] ?? null);
        $this->assertSame('paid', $context['status'] ?? null);
        $this->assertSame(
            [],
            array_diff(array_keys($context), ['cid', 'mollie_id', 'reason', 'status', 'amount', 'currency']),
            'Only ids, the reason and the money may be logged.'
        );

        $remembered = (new OrphanedExpressPayments())->all();
        $this->assertArrayHasKey($payment['id'], $remembered);
        $this->assertSame('unknown_ref', $remembered[$payment['id']]['reason']);
        $this->assertSame($payment['amount']['currency'], $remembered[$payment['id']]['currency']);

        wp_set_current_user($this->someoneWhoCanManageWooCommerce());
        ob_start();
        (new OrphanedExpressPayments())->renderNotice();
        $notice = (string) ob_get_clean();
        $this->assertStringContainsString($payment['id'], $notice);
        $this->assertStringContainsString('notice-error', $notice);

        wp_set_current_user(0);
        ob_start();
        (new OrphanedExpressPayments())->renderNotice();
        $this->assertSame('', (string) ob_get_clean(), 'A visitor is shown nothing.');
    }

    /**
     * Scenario: a payment that owes nobody anything is not put in front of a human
     *   Given an express payment whose ref no order carries, which was never captured
     *   When Mollie calls the webhook
     *   Then it is logged as unmatched, as before
     *   And nothing is remembered for the admin notice
     *
     * @test
     * @dataProvider uncapturedStatuses
     */
    public function it_reports_no_orphan_for_a_payment_that_took_no_money(string $status): void
    {
        [, , $sessionId] = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);
        $this->fakeMollie()->setPaymentStatus($payment['id'], $status, ['metadata' => ['express_ref' => self::UNKNOWN_REF]]);

        $this->deliverWebhook($payment['id']);

        $this->assertSame([], $this->loggedEvents('express.payment.orphaned'));
        $this->assertSame([], (new OrphanedExpressPayments())->all());
    }

    /**
     * A shop manager: the notice is for whoever can refund a payment or create an order.
     */
    private function someoneWhoCanManageWooCommerce(): int
    {
        $existing = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
        if ($existing !== []) {
            return (int) $existing[0];
        }

        $id = wp_insert_user([
            'user_login' => 'express-orphan-admin',
            'user_pass' => wp_generate_password(20),
            'role' => 'administrator',
        ]);
        $this->assertIsInt($id);

        return $id;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function uncapturedStatuses(): array
    {
        return [
            'open' => ['open'],
            'failed' => ['failed'],
            'canceled' => ['canceled'],
            'expired' => ['expired'],
        ];
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
     * Scenario: the wallet's billing address replaces the store's, and the shipping address does not move
     *   Given an express order whose billing and shipping came from the guest's checkout form, or from the account
     *   And the paid payment carries a different billing and a different shipping address
     *   When Mollie calls the webhook
     *   Then the order's billing address and email are the wallet's
     *   And its shipping address is exactly as it was, because that is what priced the shipping
     *   And the order is paid
     *
     * @test
     * @dataProvider heldAddresses
     */
    public function it_takes_the_wallets_billing_address_and_leaves_the_shipping_one(string $source): void
    {
        [$order, , $sessionId] = $source === 'account' ? $this->expressOrderForTheAccount() : $this->expressOrder();
        $this->assertTrue($order->needs_shipping_address(), 'The scenario needs an order with something to ship.');
        $billingBefore = $order->get_address('billing');
        $shipping = $order->get_address('shipping');
        $this->assertNotSame('', $billingBefore['address_1']);
        $walletEmail = 'other.CANARY7f3a@example.org';
        $payment = $this->fakeMollie()->completeSession($sessionId, [
            'status' => 'paid',
            'method' => 'paypal',
            'billingAddress' => CanaryData::mollieAddress(['city' => 'Rotterdam', 'email' => $walletEmail]),
            'shippingAddress' => CanaryData::mollieAddress(['city' => 'Utrecht']),
        ]);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertSame('Rotterdam', $after->get_billing_city(), 'The wallet chose this billing address.');
        $this->assertSame($walletEmail, $after->get_billing_email(), 'The wallet chose this contact.');
        $this->assertNotSame($billingBefore['city'], $after->get_billing_city());
        $this->assertSame($shipping, $after->get_address('shipping'), 'The shipping address priced the order.');
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
     *   And the order has one _mollie_payment_id, was written once per stage, and the payment was completed once
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
        $this->assertCount(2, $this->orderWrittenFor($order));
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
     * Scenario: the PayPal button writeback keeps what Mollie supplies and does not blank what it does not
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

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The plugin's own OrderLock, on a connection that lets a scenario act just before a lock is asked for.
     */
    private function boot(): ContainerInterface
    {
        if ($this->container === null) {
            $this->container = $this->bootExpress([
                OrderLock::class => function (ContainerInterface $container): OrderLock {
                    return new OrderLock($this->databaseActingBeforeALock(), $container->get(EventLog::class));
                },
            ]);
        }

        return $this->container;
    }

    private function databaseActingBeforeALock(): wpdb
    {
        $beforeTheNextLock = function (): void {
            $act = $this->beforeTheNextLock;
            $this->beforeTheNextLock = null;
            if ($act !== null) {
                $act();
            }
        };

        return new class ($GLOBALS['wpdb'], $beforeTheNextLock) extends wpdb {
            private wpdb $real;

            /** @var callable(): void */
            private $beforeTheNextLock;

            // phpcs:ignore -- delegates to the site's connection; never connects itself.
            public function __construct(wpdb $real, callable $beforeTheNextLock)
            {
                $this->real = $real;
                $this->beforeTheNextLock = $beforeTheNextLock;
            }

            public function prepare($query, ...$args)
            {
                return $this->real->prepare($query, ...$args);
            }

            public function get_var($query = null, $x = 0, $y = 0)
            {
                if (strpos((string) $query, 'SELECT GET_LOCK') === 0) {
                    ($this->beforeTheNextLock)();
                }

                return $this->real->get_var($query, $x, $y);
            }
        };
    }

    /**
     * Writes the order row directly, as another PHP process would: no hook, no cache touched.
     */
    private function writeStatusAsAnotherProcess(int $orderId, string $status): void
    {
        global $wpdb;
        $hpos = OrderUtil::custom_orders_table_usage_is_enabled();
        $updated = $wpdb->update(
            OrderUtil::get_table_for_orders(),
            [$hpos ? 'status' : 'post_status' => $status],
            [$hpos ? 'id' : 'ID' => $orderId]
        );
        $this->assertSame(1, $updated, 'The outside write did not change the order row.');
    }

    /**
     * A guest express order created as the browser does: a started session, then submit.
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
     * An express order of the fixture customer, billing from the account, shipping from checkout.
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
     * A payment at the fake Mollie for a non-express order, completed with the given outcome.
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

    /**
     * @return array<string, mixed>
     */
    private function tracksAnOpenFirstPayment(WC_Order $order, string $sessionId): array
    {
        $first = $this->fakeMollie()->completeSession($sessionId, ['status' => 'open', 'method' => 'paypal']);
        $this->assertSame(200, $this->deliverWebhook($first['id']));
        $this->assertSame($first['id'], (string) $this->fresh($order)->get_meta('_mollie_payment_id'), 'The order must track the first payment.');
        $this->assertSame('pending', $this->fresh($order)->get_status());
        $this->logger()->reset();

        return $first;
    }

    /**
     * @param array<int, string> $notesBefore
     * @param array<string, mixed> $payment
     */
    private function assertRememberedWithOneNoteAndOneError(WC_Order $order, array $notesBefore, array $payment, string $reason): void
    {
        $remembered = (new OrphanedExpressPayments())->all();
        $this->assertArrayHasKey($payment['id'], $remembered, 'The payment must be remembered for the admin notice.');
        $this->assertSame($reason, $remembered[$payment['id']]['reason']);
        $this->assertSame($payment['amount']['currency'], $remembered[$payment['id']]['currency']);

        $added = array_values(array_diff($this->notes($order), $notesBefore));
        $this->assertCount(1, $added, 'Exactly one note must name the refused payment.');
        $this->assertStringContainsString($payment['id'], $added[0]);
        $this->assertStringContainsString($payment['amount']['value'], $added[0]);
        $this->assertFalse(CanaryData::leakedIn($added[0]), 'The note must carry no personal data.');

        $orphaned = $this->loggedEvents('express.payment.orphaned');
        $this->assertCount(1, $orphaned, 'A refused paid payment must be logged as an error.');
        $this->assertSame('error', $orphaned[0]['level']);
        $context = $orphaned[0]['context'];
        $this->assertSame($payment['id'], $context['mollie_id'] ?? null);
        $this->assertSame($reason, $context['reason'] ?? null);
        $this->assertSame(
            [],
            array_diff(array_keys($context), ['cid', 'mollie_id', 'reason', 'status', 'amount', 'currency']),
            'Only ids, the reason and the money may be logged.'
        );
        $this->assertNothingLeakedToLog();
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
     * The real service with only the request reader replaced: filter_input(INPUT_POST) is empty on the CLI.
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
            $container->get(OrderLock::class),
            $container->get(EventLog::class),
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
    private function orderWrittenFor(WC_Order $order): array
    {
        return array_values(array_filter($this->loggedEvents('order.written'), static function (array $record) use ($order): bool {
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
     * Holds the order's lock from another connection for some seconds, asynchronously, so this process is not blocked.
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
