<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent\Seed;

use Mollie\WooCommerce\Adapter\WooCommerce\EffectInterpreter;
use Mollie\WooCommerce\Adapter\WordPress\OrderLock;
use Mollie\WooCommerce\Core\Types\Effect;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use RuntimeException;
use wpdb;
use WC_Order;

/**
 * The only writer of order state for new code (blueprint chokepoint 3, ADR-007).
 *
 * Today 17 files write order meta and 10 change status, with no lock anywhere — a webhook and the
 * shopper's return can process the same payment at the same time and both decide the order still
 * needs paying. The interpreter is the answer: take a per-order lock, re-read the order inside it,
 * apply the effects, save once.
 *
 * That shape only holds if three things are true, and each is a test here: the effects land on a
 * real order in a single save; applying them again changes nothing, because a webhook Mollie
 * retries must be harmless; and when the lock cannot be taken nothing at all is written, so the
 * caller can answer non-2xx and let Mollie come back rather than writing on stale facts.
 *
 * @covers \Mollie\WooCommerce\Adapter\WooCommerce\EffectInterpreter
 * @covers \Mollie\WooCommerce\Adapter\WordPress\OrderLock
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressSeed
 */
class EffectInterpreterTest extends ExpressFlowTestCase
{
    private const TRANSACTION_ID = 'tr_expressSeed01';

    /**
     * Scenario: every effect lands on a real order in one save
     *   Given a pending order
     *   When meta, the transaction id, the payment method, an address and a note are applied
     *   Then the order as re-read from the database carries all of them
     *   And the order was saved exactly once
     *   And the note was rendered from its message key, so the core needed no __()
     *   And nothing marked secret or personal reached the log
     *
     * @test
     */
    public function it_applies_every_effect_to_a_real_order_in_one_save(): void
    {
        $interpreter = $this->interpreter();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $notesBefore = $this->noteCount($order);

        $saves = $this->countingSaves(function () use ($interpreter, $order): void {
            $interpreter->apply($order, $this->effects());
        });

        $this->assertSame(1, $saves, 'The interpreter must save the order once, not once per effect.');

        $fresh = wc_get_order($order->get_id());
        $this->assertSame(self::TRANSACTION_ID, $fresh->get_meta('_mollie_payment_id'));
        $this->assertSame(self::TRANSACTION_ID, $fresh->get_transaction_id());
        $this->assertSame('mollie_wc_gateway_applepay', $fresh->get_payment_method());
        $this->assertSame(CanaryData::GIVEN_NAME, $fresh->get_billing_first_name());
        $this->assertSame('Amsterdam', $fresh->get_billing_city());
        $this->assertSame('NL', $fresh->get_billing_country());

        $this->assertSame($notesBefore + 1, $this->noteCount($fresh), 'Exactly one note belongs to one apply.');
        $this->assertOrderHasNoteContaining($fresh, 'Express checkout started');
        $this->assertNoteIsNotARawMessageKey($fresh);

        $this->assertNothingLeakedToLog();
    }

    /**
     * Scenario: applying the same effects again changes nothing
     *   Given an order the effects were already applied to
     *   When Mollie retries and the identical effects are applied a second time
     *   Then the meta, the notes and the order's own fields are exactly as they were
     *
     * @test
     */
    public function it_changes_nothing_when_the_same_effects_are_applied_again(): void
    {
        $interpreter = $this->interpreter();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');

        $interpreter->apply($order, $this->effects());
        $after = $this->fingerprint(wc_get_order($order->get_id()));

        $interpreter->apply(wc_get_order($order->get_id()), $this->effects());

        $this->assertSame(
            $after,
            $this->fingerprint(wc_get_order($order->get_id())),
            'A retried webhook must be harmless: no second note, no changed meta.'
        );
        $this->assertNothingLeakedToLog();
    }

    /**
     * Scenario: nothing is written while another request holds the order lock
     *   Given a second database connection holding this order's lock
     *   When the interpreter is asked to apply effects to that order
     *   Then it throws the retryable exception, so a webhook caller can answer non-2xx
     *   And the order is exactly as it was: no meta, no note, no transaction id
     *
     * @test
     */
    public function it_applies_nothing_and_throws_when_the_order_lock_is_held_elsewhere(): void
    {
        $interpreter = $this->interpreter();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $before = $this->fingerprint($order);

        $holder = $this->holdTheLockElsewhere((string) $order->get_id());

        try {
            $thrown = null;
            try {
                $interpreter->apply($order, $this->effects());
            } catch (RuntimeException $exception) {
                $thrown = $exception;
            }

            $this->assertNotNull($thrown, 'A lock it cannot take must be a retryable failure, not a silent skip.');
            $this->assertSame(
                $before,
                $this->fingerprint(wc_get_order($order->get_id())),
                'Nothing may be written when the lock was not taken.'
            );
        } finally {
            $this->releaseTheLock($holder, (string) $order->get_id());
        }

        $this->assertNothingLeakedToLog();
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function interpreter(): EffectInterpreter
    {
        $interpreter = $this->bootExpress()->get(EffectInterpreter::class);
        $this->assertInstanceOf(EffectInterpreter::class, $interpreter);

        return $interpreter;
    }

    /**
     * One of each effect the criterion names.
     *
     * @return array<int, Effect>
     */
    private function effects(): array
    {
        return [
            Effect::setMeta('_mollie_payment_id', self::TRANSACTION_ID),
            Effect::setTransactionId(self::TRANSACTION_ID),
            Effect::setPaymentMethod('mollie_wc_gateway_applepay'),
            Effect::setAddress('billing', [
                'first_name' => CanaryData::GIVEN_NAME,
                'last_name' => CanaryData::FAMILY_NAME,
                'address_1' => CanaryData::STREET,
                'postcode' => '1015 CS',
                'city' => 'Amsterdam',
                'country' => 'NL',
                'email' => CanaryData::EMAIL,
                'phone' => CanaryData::PHONE,
            ]),
            Effect::addNote('express.order.created', ['wallet' => 'applepay']),
        ];
    }

    /**
     * How many times WooCommerce wrote the order while the callback ran.
     */
    private function countingSaves(callable $callback): int
    {
        $saves = 0;
        $counter = static function () use (&$saves): void {
            $saves++;
        };
        add_action('woocommerce_update_order', $counter);

        try {
            $callback();
        } finally {
            remove_action('woocommerce_update_order', $counter);
        }

        return $saves;
    }

    /**
     * Everything about the order this spec says a second apply must leave alone.
     *
     * @return array<string, mixed>
     */
    private function fingerprint(WC_Order $order): array
    {
        $notes = array_map(static function ($note): string {
            return $note->content;
        }, wc_get_order_notes(['order_id' => $order->get_id()]));
        sort($notes);

        $meta = [];
        foreach ($order->get_meta_data() as $item) {
            $data = $item->get_data();
            $meta[(string) $data['key']] = $data['value'];
        }
        ksort($meta);

        return [
            'status' => $order->get_status(),
            'transaction_id' => $order->get_transaction_id(),
            'payment_method' => $order->get_payment_method(),
            'billing' => $order->get_address('billing'),
            'notes' => $notes,
            'meta' => $meta,
        ];
    }

    private function noteCount(WC_Order $order): int
    {
        return count(wc_get_order_notes(['order_id' => $order->get_id()]));
    }

    /**
     * The interpreter translates a message key through its own table; the key itself is not a
     * sentence and must never be what the merchant reads.
     */
    private function assertNoteIsNotARawMessageKey(WC_Order $order): void
    {
        foreach (wc_get_order_notes(['order_id' => $order->get_id()]) as $note) {
            $this->assertStringNotContainsString(
                'express.order.created',
                $note->content,
                'The note shows its message key instead of the translated message.'
            );
        }
    }

    /**
     * A second connection takes the same lock, which is what a concurrent request would do.
     *
     * It has to be a second connection: MySQL grants a session the lock it already holds, so
     * $wpdb would let the interpreter straight through and the test would prove nothing.
     */
    private function holdTheLockElsewhere(string $orderKey): wpdb
    {
        $lockName = OrderLock::lockName($orderKey);
        $holder = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $taken = $holder->get_var($holder->prepare('SELECT GET_LOCK(%s, 0)', $lockName));

        $this->assertSame('1', (string) $taken, 'The test could not take the lock it needs to hold.');

        return $holder;
    }

    private function releaseTheLock(wpdb $holder, string $orderKey): void
    {
        $holder->get_var($holder->prepare('SELECT RELEASE_LOCK(%s)', OrderLock::lockName($orderKey)));
        $holder->close();
    }
}
