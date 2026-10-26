<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;

/**
 * The order status a Mollie order status leads to, for an order created through the Orders API.
 *
 * The e2e suite walks the same table as for the Payments API with its "order-api" project
 * (tests/qa/tests/05-transaction); tests/Integration/e2e-equivalence.md has the list.
 *
 * @group integration
 * @group Webhooks
 * @group OrdersApiWebhook
 */
class OrdersApiWebhookTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
        $this->fakeMollie()->setMethods(['ideal', 'klarna']);
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: the webhook of a Mollie order moves the WooCommerce order
     *   Given an order placed at the checkout through the Orders API, pending
     *   And the Mollie order, or its payment, reached a status
     *   When Mollie calls the REST webhook with the Mollie order id
     *   Then the WooCommerce order has the status that stands for it
     *
     * @test
     * @dataProvider mollieOrderStatuses
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     *
     * @param array<int, string> $meta The settled marks the order carries afterwards.
     */
    public function it_moves_the_order_to_the_status_the_mollie_order_calls_for(
        string $method,
        string $mollieOrderStatus,
        string $paymentStatus,
        string $expectedStatus,
        array $meta,
        int $paymentsCompleted
    ): void {
        [$order, $mollieOrder] = $this->checkoutOrderWithMollieOrder($method, $mollieOrderStatus, $paymentStatus);

        $this->assertSame(200, $this->deliverWebhook($mollieOrder['id']));

        $after = $this->fresh($order);
        $this->assertSame($expectedStatus, $after->get_status());
        $this->assertNotSame([], $this->fakeMollie()->requests('GET', 'orders/' . $mollieOrder['id']), 'The webhook asked Mollie about the order.');
        $this->assertSame($mollieOrder['id'], $after->get_meta('_mollie_order_id'), 'The order stays linked to its Mollie order.');
        foreach (['_mollie_paid_and_processed', '_mollie_authorized'] as $metaKey) {
            $this->assertSame(in_array($metaKey, $meta, true) ? '1' : '', (string) $after->get_meta($metaKey), $metaKey);
        }
        $this->assertSame($paymentsCompleted, $this->fired('woocommerce_payment_complete'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: array<int, string>, 5: int}>
     */
    public function mollieOrderStatuses(): array
    {
        return [
            'paid' => ['ideal', 'paid', 'paid', 'processing', ['_mollie_paid_and_processed'], 1],
            'authorized' => ['klarna', 'authorized', 'authorized', 'processing', ['_mollie_authorized', '_mollie_paid_and_processed'], 1],
            // What the e2e cases reach by failing, cancelling or expiring the payment on Mollie's
            // page: the Mollie order stays as it was created, so the shopper can pay it again.
            'failed payment' => ['ideal', 'created', 'failed', 'pending', [], 0],
            'canceled payment' => ['ideal', 'created', 'canceled', 'pending', [], 0],
            'expired payment' => ['ideal', 'created', 'expired', 'pending', [], 0],
            // The Mollie order itself ending, which no e2e case waits for.
            'canceled order' => ['ideal', 'canceled', 'canceled', 'pending', [], 0],
            'expired order' => ['ideal', 'expired', 'expired', 'cancelled', [], 0],
        ];
    }

    /**
     * The order as the checkout leaves it through the Orders API.
     *
     * @return array{0: WC_Order, 1: array<string, mixed>}
     */
    private function checkoutOrderWithMollieOrder(string $method, string $mollieOrderStatus, string $paymentStatus): array
    {
        $this->boot();
        $order = $this->pendingOrder('mollie_wc_gateway_' . $method);
        $mollieOrder = $this->fakeMollie()->seedOrder([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($order)],
            'method' => $method,
            'status' => $mollieOrderStatus,
            'paymentStatus' => $paymentStatus,
            'orderNumber' => (string) $order->get_id(),
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        // MollieOrder::setActiveMolliePayment(): the transaction id is the Mollie order's.
        $order->set_transaction_id($mollieOrder['id']);
        $order->update_meta_data('_mollie_order_id', $mollieOrder['id']);
        $order->update_meta_data('_mollie_payment_id', $mollieOrder['paymentId']);
        $order->save();
        $this->logger()->reset();
        $this->hooksFired = [];

        return [$this->fresh($order), $mollieOrder];
    }
}
