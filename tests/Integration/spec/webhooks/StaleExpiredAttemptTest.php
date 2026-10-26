<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;

/**
 * An earlier payment attempt that expires while the order is already paying again.
 *
 * "expired" is not a terminal status for the dispatcher (a canceled or failed attempt the order
 * no longer tracks is dropped before any handler runs), so the expiry handler itself is the only
 * thing between a late expiry and a cancelled order.
 *
 * @group integration
 * @group Webhooks
 */
class StaleExpiredAttemptTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @scenario an expired webhook for an earlier attempt arrives while a newer attempt is pending
     *   Given an unpaid order whose first attempt was replaced by a second, still open, attempt
     *   When the first attempt expires at Mollie and its webhook arrives
     *   Then the order stays pending and keeps the second attempt as the one it tracks
     *   And one note tells the merchant the expiry was not acted on because of the pending attempt
     *
     * @test
     * @group REQ-355
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookExpired
     */
    public function it_keeps_an_order_paying_again_when_its_earlier_attempt_expires(): void
    {
        // Given
        [$order, $first, $second] = $this->orderPayingAgain();
        $notesBefore = count($this->notes($order));

        // When
        $this->expireAtMollie($order, $first);
        $this->assertSame(200, $this->deliverWebhook($first['id']));

        // Then
        $fresh = $this->fresh($order);
        $this->assertSame('pending', $fresh->get_status());
        $this->assertSame($second['id'], $fresh->get_meta('_mollie_payment_id', true));
        $notes = $this->notesContaining($fresh, $second['id']);
        $this->assertCount(1, $notes, 'One note names the pending attempt the expiry gave way to.');
        $this->assertStringContainsString($first['id'], $notes[0]);
        $this->assertCount($notesBefore + 1, $this->notes($fresh), 'The expiry leaves exactly one note.');
    }

    /**
     * An unpaid order that tracks its second attempt, the way the plugin leaves it after a method
     * switch on the pay page: the first attempt is still open at Mollie but no longer referenced.
     *
     * @return array{0: WC_Order, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function orderPayingAgain(): array
    {
        [$order, $first] = $this->orderWithPayment('open', 'mollie_wc_gateway_creditcard');
        $second = $this->paymentFor($order, ['status' => 'open', 'method' => 'paypal']);
        $order = $this->fresh($order);
        $order->set_payment_method('mollie_wc_gateway_paypal');
        $order->update_meta_data('_mollie_payment_id', $second['id']);
        $order->set_transaction_id('');
        $order->save();

        return [$this->fresh($order), $first, $second];
    }

    /**
     * The webhook finds an attempt the order no longer tracks through its redirect URL, as the plugin builds it.
     *
     * @param array<string, mixed> $attempt
     */
    private function expireAtMollie(WC_Order $order, array $attempt): void
    {
        $redirectUrl = add_query_arg(['order_id' => $order->get_id(), 'key' => $order->get_order_key()], home_url('/'));
        $this->fakeMollie()->setPaymentStatus($attempt['id'], 'expired', ['redirectUrl' => $redirectUrl]);
    }
}
