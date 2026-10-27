<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;

/**
 * A checkout on the Orders API from the shopper's click to the paid order: the real gateway, the
 * real SDK and HTTP adapter against the fake Mollie, then the webhook for the Mollie order.
 *
 * PaymentCreationIntegrationTest proves the same request shape against a Mockery double;
 * OrdersApiWebhookTest proves every order status for a seeded Mollie order. This is the one
 * round trip the e2e "order-api" shards drive in a browser (tests/qa/project-shards.ts).
 *
 * @group integration
 * @group PaymentCreation
 */
class OrdersApiCheckoutTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
        $this->setOptionForTest(self::PLUGIN_ID . '_api_switch', 'order');
        $this->fakeMollie()->setMethods(['ideal', 'klarna', 'creditcard', 'paypal', 'applepay']);
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @scenario a shopper pays an order through the Orders API and Mollie reports the outcome
     *   Given a shop on the Orders API and a pending order for the chosen method
     *   When the shopper starts the payment
     *   Then one Mollie order is created for the order total, its lines adding up, naming the order
     *   And no payment is created on the Payments API
     *   And the order records the Mollie order and its payment, and the shopper goes to the Mollie checkout
     *   When Mollie reports the order's outcome and calls the webhook
     *   Then the order takes the status WooCommerce gives that outcome
     *
     * @test
     * @dataProvider ordersApiOutcomes
     * @group REQ-302
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::processAsMollieOrder
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::onWebhookAction
     */
    public function it_pays_an_order_through_the_orders_api(string $method, string $orderStatus, string $paymentStatus, string $wcStatus): void
    {
        // Given
        $this->boot();
        $order = $this->pendingOrder('mollie_wc_gateway_' . $method);

        // When
        $result = $this->gateway($method)->process_payment($order->get_id());

        // Then
        $created = $this->fakeMollie()->requests('POST', 'orders');
        $this->assertCount(1, $created, 'Exactly one Mollie order is created.');
        $this->assertSame([], $this->fakeMollie()->requests('POST', 'payments'), 'The Payments API is not used.');
        $sent = $created[0]['body'];
        $this->assertSame($this->formattedTotal($order), $sent['amount']['value']);
        $this->assertSame($method, $sent['method']);
        $this->assertSame((string) $order->get_id(), (string) $sent['metadata']['order_id']);
        $this->assertStringContainsString('mollie_webhook_secret=', (string) $sent['webhookUrl']);
        $this->assertNotSame([], $sent['lines']);
        $this->assertSame(201, $created[0]['status'], 'The fake accepted the order: its lines add up to the amount.');

        $mollieOrder = $this->fakeMollie()->orders()[array_key_last($this->fakeMollie()->orders())];
        $this->assertSame('success', $result['result']);
        $this->assertSame('https://www.mollie.com/checkout/order/' . $mollieOrder['id'], $result['redirect']);
        $started = $this->fresh($order);
        $this->assertSame($mollieOrder['id'], $started->get_meta('_mollie_order_id', true));
        $this->assertSame($mollieOrder['paymentId'], $started->get_meta('_mollie_payment_id', true));
        $this->assertSame($mollieOrder['id'], $started->get_transaction_id());
        $this->assertSame('pending', $started->get_status());

        // When
        $this->fakeMollie()->setOrderStatus($mollieOrder['id'], $orderStatus);
        $this->fakeMollie()->setPaymentStatus($mollieOrder['paymentId'], $paymentStatus);
        $this->assertSame(200, $this->deliverWebhook($mollieOrder['id']));

        // Then
        $this->assertSame($wcStatus, $this->fresh($order)->get_status());
    }

    /**
     * Method, Mollie order status, its payment's status, WooCommerce status, as the e2e rows of
     * tests/qa/tests/05-transaction/_test-data/eur-checkout.data.ts expect them on the Orders API.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public function ordersApiOutcomes(): array
    {
        return [
            'iDEAL paid' => ['ideal', 'paid', 'paid', 'processing'],
            'Klarna authorized' => ['klarna', 'authorized', 'authorized', 'processing'],
            'iDEAL expired' => ['ideal', 'expired', 'expired', 'cancelled'],
        ];
    }

    private function gateway(string $method): \WC_Payment_Gateway
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $this->assertArrayHasKey('mollie_wc_gateway_' . $method, $gateways, 'The method registers as a gateway.');

        return $gateways['mollie_wc_gateway_' . $method];
    }
}
