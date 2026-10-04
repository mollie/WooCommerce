<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Data_Store;
use WC_Order;

/**
 * What a "pending" payment at Mollie does to the order of a method whose money arrives later.
 *
 * Mollie is faked at the HTTP edge; the webhook enters through the plugin's REST route.
 *
 * @group integration
 * @group Webhooks
 * @group PendingPaymentHold
 */
class PendingPaymentHoldTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    private const HOLD_NOTE = 'Awaiting payment confirmation.';
    private const RULE = 'PendingPaymentHold';

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
        // Every method of these scenarios is active at Mollie, so its gateway registers.
        $this->fakeMollie()->setMethods(['paybybank', 'ideal', 'banktransfer']);
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: the order waits on-hold once Mollie reports the payment as pending
     *   Given a Pay by Bank order waiting for its payment
     *   And the payment is pending at Mollie
     *   When Mollie calls the webhook
     *   Then the order is on-hold, with the note of a payment awaiting confirmation
     *   And WooCommerce no longer offers the customer to pay it
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_puts_the_order_on_hold_when_the_payment_is_pending(): void
    {
        [$order, $payment] = $this->orderWithSeededPayment('paybybank', 'pending');

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertSame('on-hold', $after->get_status());
        $this->assertCount(1, $this->notesContaining($after, self::HOLD_NOTE));
        $this->assertCount(1, $this->notesContaining($after, 'payment pending'));
        $this->assertFalse($after->needs_payment());
        $this->assertArrayNotHasKey('pay', wc_get_account_orders_actions($after));
    }

    /**
     * Scenario: the order stays payable while the payment is open
     *   Given a Pay by Bank order waiting for its payment
     *   And the payment is still open at Mollie
     *   When Mollie calls the webhook
     *   Then the order is pending and the customer is offered to pay it
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_leaves_the_order_payable_while_the_payment_is_open(): void
    {
        [$order, $payment] = $this->orderWithSeededPayment('paybybank', 'open');

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertSame('pending', $after->get_status());
        $this->assertCount(0, $this->notesContaining($after, self::HOLD_NOTE));
        $this->assertArrayHasKey('pay', wc_get_account_orders_actions($after));
    }

    /**
     * Scenario: a method set to start its orders as pending keeps them pending
     *   Given Pay by Bank whose initial order status is pending, by its setting or by a filter
     *   And the payment is pending at Mollie
     *   When Mollie calls the webhook
     *   Then the order is pending
     *
     * @test
     * @dataProvider initialStatusPendingCases
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_leaves_the_order_pending_when_the_initial_status_is_pending(string $by): void
    {
        if ($by === 'setting') {
            $this->setGatewaySettingsForTest('paybybank', ['initial_order_status' => 'pending']);
        } else {
            $this->addTestFilter(self::PLUGIN_ID . $by, static function (): string {
                return 'pending';
            });
        }
        [$order, $payment] = $this->orderWithSeededPayment('paybybank', 'pending');

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertSame('pending', $after->get_status());
        $this->assertCount(0, $this->notesContaining($after, self::HOLD_NOTE));
        $this->assertCount(1, $this->notesContaining($after, 'payment pending'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function initialStatusPendingCases(): array
    {
        return [
            'the method setting' => ['setting'],
            'the plugin-wide filter' => ['_initial_order_status'],
            'the filter of the method' => ['_initial_order_status_paybybank'],
        ];
    }

    /**
     * Scenario: a pending iDEAL payment leaves its order as it is
     *   Given an iDEAL order waiting for its payment, with the default method settings
     *   And the payment is pending at Mollie
     *   When Mollie calls the webhook
     *   Then the order is pending
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_leaves_an_ideal_order_pending_when_its_payment_is_pending(): void
    {
        [$order, $payment] = $this->orderWithSeededPayment('ideal', 'pending');

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertSame('pending', $after->get_status());
        $this->assertCount(0, $this->notesContaining($after, self::HOLD_NOTE));
        $this->assertCount(1, $this->notesContaining($after, 'payment pending'));
    }

    /**
     * Scenario: the same pending webhook delivered again changes nothing
     *   Given a Pay by Bank order whose pending payment was already reported
     *   And the order is on-hold, or was moved elsewhere since
     *   When Mollie calls the webhook again
     *   Then the order keeps its status and gets no further status change
     *
     * @test
     * @dataProvider redeliveryCases
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_does_nothing_when_the_pending_webhook_is_delivered_again(?string $movedTo, string $expected): void
    {
        [$order, $payment] = $this->orderWithSeededPayment('paybybank', 'pending');
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        if ($movedTo !== null) {
            $moved = $this->fresh($order);
            $moved->update_status($movedTo);
        }
        $before = $this->fresh($order);
        $this->assertSame($expected, $before->get_status());
        $holdNotesBefore = count($this->notesContaining($before, self::HOLD_NOTE));
        $this->hooksFired = [];

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $after = $this->fresh($order);
        $this->assertSame($expected, $after->get_status());
        $this->assertCount($holdNotesBefore, $this->notesContaining($after, self::HOLD_NOTE));
        $this->assertSame([], array_values(array_filter($this->hooksFired, static function (string $hook): bool {
            return strpos($hook, 'woocommerce_order_status_changed:') === 0;
        })));
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public function redeliveryCases(): array
    {
        return [
            'still on-hold' => [null, 'on-hold'],
            'moved to processing' => ['processing', 'processing'],
            'moved to cancelled' => ['cancelled', 'cancelled'],
        ];
    }

    /**
     * Scenario: WooCommerce's cleanup of unpaid orders leaves the held order alone
     *   Given a Pay by Bank order held after its pending payment was reported
     *   And another Pay by Bank order whose payment is still open
     *   When WooCommerce selects the unpaid orders it cancels once the hold-stock time has passed
     *   Then the held order is not among them and the other one is
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_is_kept_by_the_unpaid_order_cleanup_once_on_hold(): void
    {
        [$held, $pendingPayment] = $this->orderWithSeededPayment('paybybank', 'pending');
        [$open, $openPayment] = $this->orderWithSeededPayment('paybybank', 'open');
        $this->assertSame(200, $this->deliverWebhook($pendingPayment['id']));
        $this->assertSame(200, $this->deliverWebhook($openPayment['id']));
        $this->assertSame('on-hold', $this->fresh($held)->get_status());

        // The selection wc_cancel_unpaid_orders() makes, taken from the data store so that no other
        // order of the site is cancelled by this test.
        $unpaid = array_map('intval', WC_Data_Store::load('order')->get_unpaid_orders(strtotime('+1 day')));

        $this->assertNotContains($held->get_id(), $unpaid);
        $this->assertContains($open->get_id(), $unpaid);
        $openOrder = $this->fresh($open);
        $this->assertTrue((bool) apply_filters(
            'woocommerce_cancel_unpaid_order',
            'checkout' === $openOrder->get_created_via(),
            $openOrder
        ));
    }

    /**
     * Scenario: a held order ends like a bank-transfer order that waited on-hold
     *   Given a Pay by Bank order held after its pending payment was reported
     *   And a bank-transfer order on-hold since its payment was created
     *   When Mollie reports the same final status for both payments
     *   Then both orders have the same status
     *
     * @test
     * @dataProvider finalStatuses
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_ends_like_a_bank_transfer_order_after_the_hold(string $finalStatus): void
    {
        [$order, $payment] = $this->orderWithSeededPayment('paybybank', 'pending');
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $this->assertSame('on-hold', $this->fresh($order)->get_status());
        [$reference, $referencePayment] = $this->orderWithSeededPayment('banktransfer', 'open', 'on-hold');

        $this->fakeMollie()->setPaymentStatus($payment['id'], $finalStatus);
        $this->fakeMollie()->setPaymentStatus($referencePayment['id'], $finalStatus);
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $this->assertSame(200, $this->deliverWebhook($referencePayment['id']));

        $referenceStatus = $this->fresh($reference)->get_status();
        $this->assertNotSame('on-hold', $referenceStatus, 'The reference order did not move, so the comparison proves nothing.');
        $this->assertSame($referenceStatus, $this->fresh($order)->get_status());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function finalStatuses(): array
    {
        return [
            'paid' => ['paid'],
            'failed' => ['failed'],
            'expired' => ['expired'],
        ];
    }

    /**
     * Scenario: the decision about a pending payment is logged, and only then
     *   Given orders whose payments are pending or paid at Mollie
     *   When Mollie calls the webhook
     *   Then a pending payment leaves one decision line with its verdict
     *   And a paid payment leaves none
     *   And no line carries a secret or personal data
     *
     * @test
     * @dataProvider decisionCases
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_logs_the_decision_for_a_pending_payment_only(string $method, string $paymentStatus, ?string $verdict): void
    {
        [$order, $payment] = $this->orderWithSeededPayment($method, $paymentStatus);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $decisions = array_values(array_filter($this->loggedEvents('rule.decided'), static function (array $record): bool {
            return ($record['context']['rule'] ?? null) === self::RULE;
        }));
        $this->assertCount($verdict === null ? 0 : 1, $decisions);
        if ($verdict !== null) {
            $context = $decisions[0]['context'];
            $this->assertSame($verdict, $context['verdict'] ?? null);
            $this->assertSame($order->get_id(), $context['order'] ?? null);
            $this->assertSame(
                [],
                array_values(array_diff(array_keys($context), ['cid', 'order', 'rule', 'verdict', 'inputs'])),
                'Only the fields events.md lists for rule.decided.'
            );
            $this->assertStringContainsString('status=pending', (string) $context['inputs']);
            $this->assertStringContainsString('method=' . $method, (string) $context['inputs']);
        }
        $this->assertNothingLeakedToLog();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    public function decisionCases(): array
    {
        return [
            'pending Pay by Bank' => ['paybybank', 'pending', 'hold'],
            'pending iDEAL' => ['ideal', 'pending', 'keep'],
            'paid Pay by Bank' => ['paybybank', 'paid', null],
        ];
    }

    /**
     * The order as the checkout leaves it: placed there, linked to its payment, pending unless told
     * otherwise.
     *
     * @return array{0: WC_Order, 1: array<string, mixed>}
     */
    private function orderWithSeededPayment(string $method, string $paymentStatus, string $orderStatus = 'pending'): array
    {
        $this->boot();
        $order = $this->pendingOrder('mollie_wc_gateway_' . $method);
        $payment = $this->fakeMollie()->seedPayment([
            'amount' => ['currency' => $order->get_currency(), 'value' => $this->formattedTotal($order)],
            'method' => $method,
            'status' => $paymentStatus,
            'description' => 'Order ' . $order->get_id(),
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $order->update_meta_data('_mollie_payment_id', $payment['id']);
        $order->set_transaction_id($payment['id']);
        $order->set_created_via('checkout');
        $order->set_status($orderStatus);
        $order->save();
        $this->logger()->reset();
        $this->hooksFired = [];

        return [$this->fresh($order), $payment];
    }
}
