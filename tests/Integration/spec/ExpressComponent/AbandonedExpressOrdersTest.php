<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerce\Core\Clock;
use Mollie\WooCommerceTests\Integration\Common\Doubles\SettableClock;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use WC_Order;

/**
 * Cleanup of express orders the shopper started and never finished (REQ-E1 to E4; AC-27 to AC-30).
 *
 * It runs on the plugin's existing Action Scheduler action mollie_woocommerce_cancel_unpaid_orders.
 * It selects only pending orders created via mollie_express whose session expiry plus the grace
 * period has passed, asks Mollie about the payment when the order knows one and otherwise about the
 * session, and cancels only when Mollie positively says the order can no longer be paid. Paid,
 * authorized, pending, open or completed keeps the order however old it is; so does not being able
 * to reach Mollie.
 *
 * The orders are created the way the browser creates them, a session and then submit. The plugin's
 * clock is then moved past the order's expiry and grace; the fake Mollie is told what happened.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressOrderLifecycle
 */
class AbandonedExpressOrdersTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const CLEANUP_ACTION = 'mollie_woocommerce_cancel_unpaid_orders';

    private const ABANDON_NOTE = 'Express checkout was started and not completed';

    private SettableClock $clock;

    private int $graceSeconds = 0;

    private int $requestsBeforeCleanup = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->clock = new SettableClock();
    }

    public function tearDown(): void
    {
        $this->tearDownExpressCheckout();

        parent::tearDown();
    }

    /**
     * Scenario: an order whose session Mollie reports expired is cancelled once expiry and grace have passed
     *   Given a pending express order whose session Mollie reports expired
     *   And the time is one second past the session's expiry plus the grace period
     *   When the cleanup action runs
     *   Then the order is cancelled
     *   And it carries the note that express checkout was started and not completed
     *   And express.abandoned.cancelled is logged
     *
     * @test
     */
    public function it_cancels_an_order_whose_session_mollie_reports_expired(): void
    {
        $order = $this->expressOrder();
        $this->fakeMollie()->expireSession($order['session']);
        $this->clock->set($order['expiresAt'] + $this->graceSeconds + 1);

        $this->runCleanup();

        $cancelled = wc_get_order($order['id']);
        $this->assertSame('cancelled', $cancelled->get_status());
        $this->assertOrderHasNoteContaining($cancelled, self::ABANDON_NOTE);
        $this->assertCount(1, $this->eventsFor('express.abandoned.cancelled', $order['id']));
    }

    /**
     * Scenario: an order whose known payment can no longer succeed is cancelled, whatever the session says
     *   Given a pending express order past its expiry and grace that knows its payment
     *   And Mollie reports that payment failed, canceled or expired, while the session is still open
     *   When the cleanup action runs
     *   Then the order is cancelled with the abandon note
     *
     * @test
     * @dataProvider finalPayments
     */
    public function it_cancels_an_order_whose_known_payment_can_no_longer_succeed(string $paymentStatus): void
    {
        $order = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($order['session'], ['status' => 'failed', 'method' => 'paypal']);
        $this->fakeMollie()->setPaymentStatus($payment['id'], $paymentStatus);
        $this->assertSame('open', $this->fakeMollie()->sessions()[$order['session']]['status'], 'The session must not answer for the payment.');
        $known = wc_get_order($order['id']);
        $known->update_meta_data('_mollie_payment_id', $payment['id']);
        $known->save();
        $this->clock->set($order['expiresAt'] + $this->graceSeconds + 1);

        $this->runCleanup();

        $cancelled = wc_get_order($order['id']);
        $this->assertSame('cancelled', $cancelled->get_status());
        $this->assertOrderHasNoteContaining($cancelled, self::ABANDON_NOTE);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function finalPayments(): array
    {
        return [
            'payment failed' => ['failed'],
            'payment canceled' => ['canceled'],
            'payment expired' => ['expired'],
        ];
    }

    /**
     * Scenario: an order is not looked at before its expiry and grace have passed
     *   Given a pending express order whose session Mollie reports expired, one second short of its expiry plus grace
     *   And, as a control, an older abandoned express order that is past its expiry plus grace
     *   When the cleanup action runs
     *   Then the control is cancelled
     *   And the younger order is still pending, and Mollie was asked about the control's session, never the other order's
     *
     * @test
     */
    public function it_waits_until_expiry_and_grace_have_passed(): void
    {
        $control = $this->expressOrder(-600);
        $order = $this->expressOrderOfAnotherShopper();
        $this->assertGreaterThan(0, $this->graceSeconds, 'config/express.php must set abandonGraceSeconds.');
        $this->fakeMollie()->expireSession($control['session']);
        $this->fakeMollie()->expireSession($order['session']);
        $this->clock->set($order['expiresAt'] + $this->graceSeconds - 1);
        $this->assertLessThan($this->clock->now(), $control['expiresAt'] + $this->graceSeconds);

        $this->runCleanup();

        $this->assertSame('cancelled', wc_get_order($control['id'])->get_status(), 'The control must be cancelled, or the scenario proves nothing.');
        $this->assertSame('pending', wc_get_order($order['id'])->get_status());
        $this->assertContains('sessions/' . $control['session'], $this->sessionLookups());
        $this->assertNotContains('sessions/' . $order['session'], $this->sessionLookups(), 'Mollie must not be asked about an order cleanup may not select.');
    }

    /**
     * Scenario: an order whose payment could still succeed, or did, is kept however old it is
     *   Given a pending express order thirty days past its expiry and grace
     *   And Mollie reports its session completed, or its payment paid, authorized or pending
     *   When the cleanup action runs
     *   Then the order is still pending and carries no abandon note
     *   And express.abandoned.kept is logged
     *
     * @test
     * @dataProvider stillPayable
     */
    public function it_keeps_an_order_whose_payment_could_still_succeed(string $paymentStatus, bool $orderKnowsPayment): void
    {
        $order = $this->expressOrder();
        $payment = $this->fakeMollie()->completeSession($order['session'], ['status' => $paymentStatus, 'method' => 'paypal']);
        if ($orderKnowsPayment) {
            // What the webhook's first sight of the payment leaves on the order.
            $known = wc_get_order($order['id']);
            $known->update_meta_data('_mollie_payment_id', $payment['id']);
            $known->save();
        }
        $this->clock->set($order['expiresAt'] + $this->graceSeconds + 30 * DAY_IN_SECONDS);

        $this->runCleanup();

        $kept = wc_get_order($order['id']);
        $this->assertSame('pending', $kept->get_status());
        $this->assertNoAbandonNote($kept);
        $this->assertCount(1, $this->eventsFor('express.abandoned.kept', $order['id']));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function stillPayable(): array
    {
        return [
            'session completed, payment not yet known to the order' => ['paid', false],
            'payment paid' => ['paid', true],
            'payment authorized' => ['authorized', true],
            'payment pending' => ['pending', true],
        ];
    }

    /**
     * Scenario: an order is not cancelled when Mollie cannot be reached
     *   Given a pending express order past its expiry and grace, whose session Mollie would report expired
     *   And Mollie answers the lookup with an outage
     *   When the cleanup action runs
     *   Then the order is still pending and carries no abandon note
     *   And express.abandoned.kept is logged
     *
     * @test
     */
    public function it_keeps_the_order_when_mollie_cannot_be_reached(): void
    {
        $order = $this->expressOrder();
        $this->fakeMollie()->expireSession($order['session']);
        $this->clock->set($order['expiresAt'] + $this->graceSeconds + 1);
        $this->fakeMollie()->failNext('GET', 'sessions', 503);

        $this->runCleanup();

        $kept = wc_get_order($order['id']);
        $this->assertSame('pending', $kept->get_status());
        $this->assertNoAbandonNote($kept);
        $this->assertCount(1, $this->eventsFor('express.abandoned.kept', $order['id']));
    }

    /**
     * Scenario: cleanup never selects an order that is not a pending express order
     *   Given an order created via the checkout that carries the same express meta, pending and long expired
     *   Or an express order that is no longer pending
     *   And, as a control, an abandoned pending express order of another shopper
     *   And Mollie reports both sessions expired
     *   When the cleanup action runs, thirty days later
     *   Then the control is cancelled
     *   And the other order keeps its status, and Mollie was asked about the control's session, never the other order's
     *
     * @test
     * @dataProvider notSelectable
     */
    public function it_never_selects_an_order_that_is_not_a_pending_express_order(string $case): void
    {
        $order = $this->expressOrder();
        $control = $this->expressOrderOfAnotherShopper();
        $this->fakeMollie()->expireSession($order['session']);
        $this->fakeMollie()->expireSession($control['session']);
        $subject = wc_get_order($order['id']);
        if ($case === 'created_via checkout') {
            $subject->set_created_via('checkout');
        } else {
            $subject->set_status('on-hold');
        }
        $subject->save();
        $statusBefore = $subject->get_status();
        $this->clock->set(max($order['expiresAt'], $control['expiresAt']) + $this->graceSeconds + 30 * DAY_IN_SECONDS);

        $this->runCleanup();

        $this->assertSame('cancelled', wc_get_order($control['id'])->get_status(), 'The control must be cancelled, or the scenario proves nothing.');
        $this->assertSame($statusBefore, wc_get_order($order['id'])->get_status());
        $this->assertContains('sessions/' . $control['session'], $this->sessionLookups());
        $this->assertNotContains('sessions/' . $order['session'], $this->sessionLookups(), 'Mollie must not be asked about an order cleanup may not select.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function notSelectable(): array
    {
        return [
            'a pending order created via the checkout' => ['created_via checkout'],
            'an express order on hold' => ['not pending'],
        ];
    }

    /**
     * Scenario: cleanup logs no secret and no shopper detail
     *   Given canary API keys and webhook secret, and an express order placed with canary shopper data
     *   When cleanup looks the order up at Mollie and cancels it
     *   Then nothing marked secret or personal reached the log
     *
     * @test
     */
    public function it_leaks_no_canary_during_cleanup(): void
    {
        $order = $this->expressOrder();
        $this->fakeMollie()->expireSession($order['session']);
        $this->clock->set($order['expiresAt'] + $this->graceSeconds + 1);

        $this->runCleanup();
        $this->assertSame('cancelled', wc_get_order($order['id'])->get_status());

        $this->assertNothingLeakedToLog();
    }

    /**
     * Scenario: the expiry setting of the wallet's own payment method does not cancel an express order
     *   Given the merchant turned on "expiry time" (10 minutes) for PayPal, the express order's provisional method
     *   And a pending express order last modified an hour ago, whose session has not expired
     *   And an ordinary pending PayPal order last modified an hour ago
     *   When the plugin's cleanup action runs
     *   Then the express order is still pending: only the express cleanup, which asks Mollie first, may cancel it
     *   And the ordinary order is cancelled exactly as before (REQ-E2, REQ-E4)
     *
     * @test
     */
    public function it_is_not_cancelled_by_the_expiry_setting_of_its_payment_method(): void
    {
        $order = $this->expressOrder();
        $ordinary = $this->pendingOrder('mollie_wc_gateway_paypal');
        $this->setGatewaySettingsForTest('paypal', ['activate_expiry_days_setting' => 'yes', 'order_dueDate' => '10']);
        foreach ([$order['id'], $ordinary->get_id()] as $orderId) {
            $stale = wc_get_order($orderId);
            $stale->set_date_modified(time() - 3600);
            $stale->save();
        }
        $this->bootExpressOwning(['init', self::CLEANUP_ACTION]);
        do_action('init');

        $this->runCleanup();

        $this->assertSame('pending', wc_get_order($order['id'])->get_status(), 'The expiry setting cancelled an express order Mollie may still pay.');
        $this->assertSame('cancelled', wc_get_order($ordinary->get_id())->get_status(), 'An ordinary unpaid order must still expire as before.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * A pending express order created the way the browser creates it, at the real time. A negative
     * offset makes Mollie create the session that many seconds earlier, so it expires sooner.
     *
     * @return array{id: int, session: string, ref: string, expiresAt: int}
     */
    private function expressOrder(int $mollieTimeOffset = 0): array
    {
        $this->fakeMollie()->setNow($mollieTimeOffset === 0 ? null : time() + $mollieTimeOffset);
        $clock = $this->clock;
        $container = $this->bootExpressOwning([self::CLEANUP_ACTION], [
            Clock::class => static function () use ($clock): Clock {
                return $clock;
            },
        ]);
        $this->graceSeconds = (int) ($container->get('express.config')['abandonGraceSeconds'] ?? 0);
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
        $session = $this->startedSession();
        $this->assertAnsweredOk($this->startOrder());
        $order = $this->onlyOrderFor($session['ref']);
        $this->assertSame('pending', $order->get_status());

        return [
            'id' => $order->get_id(),
            'session' => $session['id'],
            'ref' => $session['ref'],
            'expiresAt' => $this->sessionExpiresAt($session['id']),
        ];
    }

    /**
     * A second pending express order, placed by a new shopper so it gets its own session.
     *
     * @return array{id: int, session: string, ref: string, expiresAt: int}
     */
    private function expressOrderOfAnotherShopper(): array
    {
        $this->newShopper();

        return $this->expressOrder();
    }

    /**
     * The events the last cleanup run logged about one order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventsFor(string $event, int $orderId): array
    {
        return array_values(array_filter($this->loggedEvents($event), static function (array $record) use ($orderId): bool {
            return (int) ($record['context']['order'] ?? 0) === $orderId;
        }));
    }

    /**
     * The sessions the last cleanup run asked Mollie about, as request paths.
     *
     * @return array<int, string>
     */
    private function sessionLookups(): array
    {
        $sinceCleanup = array_slice($this->fakeMollie()->requests(), $this->requestsBeforeCleanup);
        $lookups = array_filter($sinceCleanup, static function (array $request): bool {
            return $request['method'] === 'GET' && strpos((string) $request['path'], 'sessions') === 0;
        });

        return array_values(array_map(static function (array $request): string {
            return (string) $request['path'];
        }, $lookups));
    }

    private function runCleanup(): void
    {
        $this->logger()->reset();
        $this->fakeMollie()->setNow(null);
        $this->requestsBeforeCleanup = count($this->fakeMollie()->requests());
        do_action(self::CLEANUP_ACTION);
    }

    private function assertNoAbandonNote(WC_Order $order): void
    {
        foreach (wc_get_order_notes(['order_id' => $order->get_id()]) as $note) {
            $this->assertStringNotContainsString(self::ABANDON_NOTE, (string) $note->content);
        }
    }
}
