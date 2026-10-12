<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;

/**
 * Money that goes back without WooCommerce asking for it: a refund made in the Mollie dashboard, a
 * chargeback by the shopper's bank. The plugin learns of both from the webhook of the paid payment.
 *
 * The e2e suite covers the dashboard refund in tests/qa/tests/06-refund ("refundViaMollieDashboard",
 * each case waiting up to 30 minutes for Mollie's webhook); nothing covers the chargeback.
 * tests/Integration/e2e-equivalence.md has the list.
 *
 * @group integration
 * @group Webhooks
 * @group RefundWebhook
 */
class RefundAndChargebackWebhookTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    /**
     * @var array<int, string>
     */
    private array $pluginHooksFired = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
        $this->pluginHooksFired = [];
        foreach (['refunds_processed', 'chargebacks_processed'] as $hook) {
            $this->addTestFilter(self::PLUGIN_ID . '_' . $hook, function () use ($hook): void {
                $this->pluginHooksFired[] = $hook;
            });
        }
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: a full refund made in the Mollie dashboard marks the order refunded
     *   Given a paid order that is being processed
     *   When the whole amount is refunded in the Mollie dashboard and Mollie calls the webhook
     *   Then the order is refunded, for its whole total
     *   And a note names the refund, which is remembered as processed
     *
     * @test
     * @dataProvider dashboardRefundMethods
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::processRefunds
     */
    public function it_marks_the_order_refunded_for_a_full_refund_made_in_the_mollie_dashboard(string $method): void
    {
        [$order, $paymentId] = $this->paidOrder($method);
        $refund = $this->fakeMollie()->refundFromDashboard($paymentId, $this->formattedTotal($order));

        $this->assertSame(200, $this->deliverWebhook($paymentId));

        $after = $this->fresh($order);
        $this->assertSame('refunded', $after->get_status());
        $this->assertSame((float) $order->get_total(), (float) $after->get_total_refunded());
        $this->assertSame([$refund['id']], array_values((array) $after->get_meta('_mollie_processed_refund_ids')));
        $this->assertCount(1, $this->notesContaining($after, "New refund {$refund['id']} processed in Mollie Dashboard!"));
        $this->assertCount(1, $this->notesContaining($after, 'payment _order_status_refunded via Mollie (' . $paymentId));
        $this->assertSame(['refunds_processed'], $this->pluginHooksFired);
    }

    /**
     * Scenario: a partial refund made in the Mollie dashboard is noted and leaves the order as it is
     *   Given a paid order that is being processed
     *   When part of the amount is refunded in the Mollie dashboard and Mollie calls the webhook
     *   Then the order is still being processed and WooCommerce records no refund
     *   And a note names the refund, which is remembered as processed
     *
     * @test
     * @dataProvider dashboardRefundMethods
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::processRefunds
     */
    public function it_only_notes_a_partial_refund_made_in_the_mollie_dashboard(string $method): void
    {
        [$order, $paymentId] = $this->paidOrder($method);
        $refund = $this->fakeMollie()->refundFromDashboard($paymentId, '1.00');

        $this->assertSame(200, $this->deliverWebhook($paymentId));

        $after = $this->fresh($order);
        $this->assertSame('processing', $after->get_status());
        $this->assertSame(0.0, (float) $after->get_total_refunded());
        $this->assertSame([$refund['id']], array_values((array) $after->get_meta('_mollie_processed_refund_ids')));
        $this->assertCount(1, $this->notesContaining($after, "New refund {$refund['id']} processed in Mollie Dashboard!"));
        $this->assertSame(['refunds_processed'], $this->pluginHooksFired);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function dashboardRefundMethods(): array
    {
        return [
            'ideal' => ['ideal'],
            'creditcard' => ['creditcard'],
        ];
    }

    /**
     * Scenario: a refund is processed once, however often Mollie calls
     *   Given a refund made in the Mollie dashboard that the webhook already processed
     *   When Mollie calls the webhook again
     *   Then nothing is written: no second note, no hook
     *   And a second refund, made later, is the only one the next call processes
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::processRefunds
     */
    public function it_processes_each_dashboard_refund_once(): void
    {
        [$order, $paymentId] = $this->paidOrder('ideal');
        $first = $this->fakeMollie()->refundFromDashboard($paymentId, '1.00');
        $this->deliverWebhook($paymentId);
        $notesAfterFirst = $this->notes($this->fresh($order));
        $this->pluginHooksFired = [];

        $this->assertSame(200, $this->deliverWebhook($paymentId));

        $this->assertSame($notesAfterFirst, $this->notes($this->fresh($order)));
        $this->assertSame([], $this->pluginHooksFired);

        $second = $this->fakeMollie()->refundFromDashboard($paymentId, '2.00');
        $this->assertSame(200, $this->deliverWebhook($paymentId));

        $after = $this->fresh($order);
        $processed = array_values((array) $after->get_meta('_mollie_processed_refund_ids'));
        sort($processed);
        $expected = [$first['id'], $second['id']];
        sort($expected);
        $this->assertSame($expected, $processed);
        $this->assertCount(1, $this->notesContaining($after, "New refund {$first['id']} processed"));
        $this->assertCount(1, $this->notesContaining($after, "New refund {$second['id']} processed"));
        $this->assertSame(['refunds_processed'], $this->pluginHooksFired);
    }

    /**
     * Scenario: a refund of a Mollie order is found on the order itself
     *   Given a paid order created through the Orders API
     *   When its payment is refunded in full in the Mollie dashboard and Mollie calls the webhook
     *   Then the order is refunded and the refund is remembered as processed
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::processRefunds
     */
    public function it_marks_an_orders_api_order_refunded_for_a_full_dashboard_refund(): void
    {
        $this->boot();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $mollieOrder = $this->fakeMollie()->seedOrder([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'method' => 'ideal',
            'status' => 'paid',
            'paymentStatus' => 'paid',
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $order->set_transaction_id($mollieOrder['id']);
        $order->update_meta_data('_mollie_order_id', $mollieOrder['id']);
        $order->update_meta_data('_mollie_payment_id', $mollieOrder['paymentId']);
        $order->save();
        $this->assertSame(200, $this->deliverWebhook($mollieOrder['id']));
        $this->assertSame('processing', $this->fresh($order)->get_status(), 'The order must be paid before it is refunded.');

        $refund = $this->fakeMollie()->refundFromDashboard($mollieOrder['paymentId'], $this->formattedTotal($order));
        $this->assertSame(200, $this->deliverWebhook($mollieOrder['id']));

        $after = $this->fresh($order);
        $this->assertSame('refunded', $after->get_status());
        $this->assertSame([$refund['id']], array_values((array) $after->get_meta('_mollie_processed_refund_ids')));
        $this->assertSame(['refunds_processed'], $this->pluginHooksFired);
    }

    /**
     * Scenario: a chargeback puts the order on hold for a human, once
     *   Given a paid order that is being processed
     *   When the shopper's bank takes the money back and Mollie calls the webhook
     *   Then the order is on hold, with a note naming the chargeback and one asking for a review
     *   And the chargeback is remembered, so the next call writes nothing
     *
     * @test
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::processChargebacks
     */
    public function it_puts_the_order_on_hold_for_a_chargeback_once(): void
    {
        [$order, $paymentId] = $this->paidOrder('creditcard');
        $chargeback = $this->fakeMollie()->chargeBack($paymentId, $this->formattedTotal($order));

        $this->assertSame(200, $this->deliverWebhook($paymentId));

        $after = $this->fresh($order);
        $this->assertSame('on-hold', $after->get_status());
        $this->assertSame([$chargeback['id']], array_values((array) $after->get_meta('_mollie_processed_chargeback_ids')));
        $this->assertCount(1, $this->notesContaining($after, "New chargeback {$chargeback['id']} processed!"));
        $this->assertCount(1, $this->notesContaining($after, 'payment charged back via Mollie (' . $paymentId));
        $this->assertSame(['chargebacks_processed'], $this->pluginHooksFired);

        $notesAfterFirst = $this->notes($after);
        $this->pluginHooksFired = [];
        $this->assertSame(200, $this->deliverWebhook($paymentId));

        $again = $this->fresh($order);
        $this->assertSame('on-hold', $again->get_status());
        $this->assertSame($notesAfterFirst, $this->notes($again));
        $this->assertSame([], $this->pluginHooksFired);
    }

    /**
     * An order the paid webhook already moved to processing.
     *
     * @return array{0: WC_Order, 1: string}
     */
    private function paidOrder(string $method): array
    {
        $this->boot();
        $order = $this->pendingOrder('mollie_wc_gateway_' . $method);
        $payment = $this->fakeMollie()->seedPayment([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'method' => $method,
            'status' => 'paid',
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $order->update_meta_data('_mollie_payment_id', $payment['id']);
        $order->set_transaction_id($payment['id']);
        $order->save();
        $this->assertSame(200, $this->deliverWebhook($payment['id']));
        $paid = $this->fresh($order);
        $this->assertSame('processing', $paid->get_status(), 'The order must be paid before money goes back.');
        $this->pluginHooksFired = [];

        return [$paid, $payment['id']];
    }
}
