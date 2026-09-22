<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerce\Adapter\WordPress\ExpressUrls;
use Mollie\WooCommerceTests\Integration\Common\Doubles\RedirectCaptured;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use WC_Order;

/**
 * wc-api/mollie_express_return?ref=…: where the shopper lands after the wallet (REQ-C5, D2; AC-18, AC-22).
 *
 * The session's redirectUrl was fixed before any order existed, so it carries the express_ref, not
 * an order id and key. The handler finds the order by that ref and only decides where to send the
 * shopper: to the order-received page when the order exists (paid, or still awaiting the webhook),
 * back to the checkout with a notice when it does not or its payment failed. It never changes the
 * order: only the webhook does that. The ref is a capability, compared with hash_equals, and an
 * unknown ref gets the same answer as a failed payment.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressOrderLifecycle
 */
class ExpressReturnTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const RETURN_HOOK = 'woocommerce_api_' . ExpressUrls::RETURN_API;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->addTestFilter('wp_redirect', RedirectCaptured::listen(), PHP_INT_MAX, 1);
    }

    public function tearDown(): void
    {
        unset($_GET['ref'], $_REQUEST['ref']);
        if (function_exists('wc_clear_notices') && WC()->session) {
            wc_clear_notices();
        }
        $this->tearDownExpressCheckout();

        parent::tearDown();
    }

    /**
     * Scenario: a shopper returning for a known ref goes to the order-received page, and the order is untouched
     *   Given an express order created at submit, still awaiting the webhook, or already paid by it
     *   When the shopper returns with the order's express_ref
     *   Then they are redirected to that order's order-received page
     *   And the order's status and meta are exactly as before
     *   And express.return.shown is logged
     *
     * @test
     * @dataProvider knownOrders
     */
    public function it_redirects_a_known_ref_to_the_order_received_page(bool $paidByWebhook): void
    {
        [$order, $ref] = $this->expressOrder();
        if ($paidByWebhook) {
            $order->payment_complete('tr_fakePaid');
        }
        $before = $this->state($order);

        $location = $this->returnWith($ref);

        $this->assertStringStartsWith(wc_get_order($order->get_id())->get_checkout_order_received_url(), $location);
        $this->assertSame($before, $this->state($order));
        $this->assertCount(1, $this->loggedEvents('express.return.shown'));
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public function knownOrders(): array
    {
        return [
            'the webhook has not arrived yet' => [false],
            'the webhook already paid the order' => [true],
        ];
    }

    /**
     * Scenario: an unknown ref goes back to the checkout with a notice
     *   Given an express order created at submit
     *   When a shopper returns with a ref no order carries, or one that differs from the order's in one character
     *   Then they are redirected to the checkout
     *   And a notice tells them the payment was not completed
     *   And the order's status and meta are exactly as before
     *
     * @test
     * @dataProvider unknownRefs
     */
    public function it_sends_an_unknown_ref_back_to_the_checkout_with_a_notice(string $case): void
    {
        [$order, $ref] = $this->expressOrder();
        $before = $this->state($order);
        $unknown = $case === 'nearly' ? substr($ref, 0, -1) . (substr($ref, -1) === 'a' ? 'b' : 'a') : 'exr_' . str_repeat('0', 32);

        $location = $this->returnWith($unknown);

        $this->assertStringStartsWith(wc_get_checkout_url(), $location);
        $this->assertStringNotContainsString('order-received', $location);
        $this->assertGreaterThan(0, wc_notice_count(), 'The shopper must be told why they are back on the checkout.');
        $this->assertSame($before, $this->state($order));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function unknownRefs(): array
    {
        return [
            'a ref no order carries' => ['unknown'],
            'the order\'s ref with one character changed' => ['nearly'],
        ];
    }

    /**
     * Scenario: a failed payment goes back to the checkout with a notice
     *   Given an express order whose payment the webhook recorded as failed
     *   When the shopper returns with the order's express_ref
     *   Then they are redirected to the checkout with a notice
     *   And the order's status and meta are exactly as before
     *
     * @test
     */
    public function it_sends_a_failed_payment_back_to_the_checkout_with_a_notice(): void
    {
        [$order, $ref] = $this->expressOrder();
        $order->update_status('failed');
        $before = $this->state($order);

        $location = $this->returnWith($ref);

        $this->assertStringStartsWith(wc_get_checkout_url(), $location);
        $this->assertStringNotContainsString('order-received', $location);
        $this->assertGreaterThan(0, wc_notice_count());
        $this->assertSame($before, $this->state($order));
    }

    /**
     * Scenario: the return alone never marks an order paid
     *   Given an express order created at submit
     *   And Mollie holds its payment as paid, but the webhook has not arrived
     *   When the shopper returns with the order's express_ref
     *   Then they are redirected to the order-received page
     *   And the order is still pending with its meta unchanged
     *   And the return asked nothing of Mollie
     *
     * @test
     */
    public function it_does_not_mark_the_order_paid_on_the_return_alone(): void
    {
        [$order, $ref, $sessionId] = $this->expressOrder();
        $this->fakeMollie()->completeSession($sessionId, ['status' => 'paid', 'method' => 'paypal']);
        $before = $this->state($order);
        $mollieCalls = count($this->fakeMollie()->requests());

        $location = $this->returnWith($ref);

        $this->assertStringStartsWith(wc_get_order($order->get_id())->get_checkout_order_received_url(), $location);
        $this->assertSame('pending', wc_get_order($order->get_id())->get_status());
        $this->assertSame($before, $this->state($order));
        $this->assertCount($mollieCalls, $this->fakeMollie()->requests());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * An express order created the way the browser creates it: a started session, then submit.
     *
     * @return array{0: WC_Order, 1: string, 2: string} The order, its express_ref, its session id.
     */
    private function expressOrder(): array
    {
        $this->bootExpressOwning([self::RETURN_HOOK]);
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
        $session = $this->startedSession();
        $this->assertAnsweredOk($this->startOrder());
        wc_clear_notices();

        return [$this->onlyOrderFor($session['ref']), $session['ref'], $session['id']];
    }

    /**
     * The shopper's browser following the session's redirectUrl.
     */
    private function returnWith(string $ref): string
    {
        $_GET['ref'] = $ref;
        $_REQUEST['ref'] = $ref;

        try {
            do_action(self::RETURN_HOOK);
        } catch (RedirectCaptured $redirect) {
            return $redirect->location();
        }
        $this->fail('The return handler must redirect the shopper.');
    }

    /**
     * What the return handler must not change: the status and every meta value, read fresh.
     *
     * @return array{status: string, meta: array<string, mixed>}
     */
    private function state(WC_Order $order): array
    {
        clean_post_cache($order->get_id());
        $fresh = wc_get_order($order->get_id());
        $meta = [];
        foreach ($fresh->get_meta_data() as $item) {
            $data = $item->get_data();
            $meta[$data['key']][] = $data['value'];
        }
        ksort($meta);

        return ['status' => $fresh->get_status(), 'meta' => $meta];
    }
}
