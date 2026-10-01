<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent\Seed;

use Mollie\WooCommerce\Payment\OrderLock;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\RememberedSession;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\FirstSightData;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderWriter;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Psr\Container\ContainerInterface;
use WC_Order;

/**
 * The three writes express makes to an order: stamp, first sight, cancel as abandoned.
 * Each saves once and only on change, adds a note once, writes only allowlisted address fields.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressOrderWriter
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressSeed
 */
class ExpressOrderWriterTest extends ExpressFlowTestCase
{
    private const PAYMENT_ID = 'tr_expressWriter01';

    private const EXPRESS_REF = 'exr_0123456789abcdef0123456789abcdef';

    private const SESSION_ID = 'sess_expressWriter01';

    private const EXPIRES_AT = 1790000600;

    private const NOTE_CREATED = 'Express checkout started';

    private const NOTE_ABANDONED = 'Express checkout was started and not completed';

    private const NOTE_UNKNOWN_WALLET = 'Express checkout was paid with the Mollie method creditcard, which has no payment method in this shop; the order keeps its payment method.';

    private ?ContainerInterface $container = null;

    /**
     * Scenario: a new express order is stamped in one save, with one note
     *   Given a pending order just created by the order factory
     *   When the writer stamps it with the remembered session, the mode, the provisional gateway and the wallet
     *   Then it was created via mollie_express
     *   And it carries the express ref, the session id, the session expiry and the payment mode as meta
     *   And it is pending, with the provisional gateway and that gateway's title
     *   And it carries the note "Express checkout started" once
     *   And the order was saved exactly once, and order.written was logged once
     *
     * @test
     */
    public function it_stamps_a_new_order_in_one_save_with_one_note(): void
    {
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $notesBefore = $this->noteCount($order);

        $saves = $this->countingSaves(function () use ($order): void {
            $this->stamp($order->get_id());
        });

        $this->assertSame(1, $saves, 'The writer must save the order once, not once per field.');
        $fresh = wc_get_order($order->get_id());
        $this->assertSame('mollie_express', $fresh->get_created_via());
        $this->assertSame(self::EXPRESS_REF, (string) $fresh->get_meta('_mollie_express_ref'));
        $this->assertSame(self::SESSION_ID, (string) $fresh->get_meta('_mollie_express_session_id'));
        $this->assertSame((string) self::EXPIRES_AT, (string) $fresh->get_meta('_mollie_express_expires_at'));
        $this->assertSame('live', (string) $fresh->get_meta('_mollie_payment_mode'));
        $this->assertSame('pending', $fresh->get_status());
        $this->assertSame('mollie_wc_gateway_applepay', $fresh->get_payment_method());
        $this->assertSame($this->gatewayTitle('mollie_wc_gateway_applepay'), $fresh->get_payment_method_title());
        $this->assertSame($notesBefore + 1, $this->noteCount($fresh));
        $this->assertSame([self::NOTE_CREATED], $this->notesContaining($fresh, self::NOTE_CREATED));
        $this->assertCount(1, $this->orderWritten($order->get_id()));
        $this->assertNothingLeakedToLog();
    }

    /**
     * Scenario: the first sight of a matched payment is recorded in one save
     *   Given a pending express order
     *   And a first-sight verdict naming the payment, its mode, the wallet's gateway and the wallet's billing address
     *   When the writer records it
     *   Then _mollie_payment_id and the transaction id are the payment id
     *   And _mollie_payment_mode is the payment's mode
     *   And the payment method is the wallet's gateway, with its title
     *   And the billing address is the wallet's
     *   And the order was saved exactly once, with no note added
     *   And nothing marked secret or personal reached the log
     *
     * @test
     */
    public function it_records_first_sight_in_one_save(): void
    {
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $notesBefore = $this->noteCount($order);

        $saves = $this->countingSaves(function () use ($order): void {
            $this->recordFirstSight($order->get_id(), $this->firstSight('mollie_wc_gateway_applepay', null));
        });

        $this->assertSame(1, $saves);
        $fresh = wc_get_order($order->get_id());
        $this->assertSame(self::PAYMENT_ID, (string) $fresh->get_meta('_mollie_payment_id'));
        $this->assertSame(self::PAYMENT_ID, $fresh->get_transaction_id());
        $this->assertSame('live', (string) $fresh->get_meta('_mollie_payment_mode'));
        $this->assertSame('mollie_wc_gateway_applepay', $fresh->get_payment_method());
        $this->assertSame($this->gatewayTitle('mollie_wc_gateway_applepay'), $fresh->get_payment_method_title());
        $this->assertSame(CanaryData::GIVEN_NAME, $fresh->get_billing_first_name());
        $this->assertSame(CanaryData::EMAIL, $fresh->get_billing_email());
        $this->assertSame('Amsterdam', $fresh->get_billing_city());
        $this->assertSame('NL', $fresh->get_billing_country());
        $this->assertSame($notesBefore, $this->noteCount($fresh), 'A matched wallet adds no note.');
        $this->assertCount(1, $this->orderWritten($order->get_id()));
        $this->assertNothingLeakedToLog();
    }

    /**
     * Scenario: a payment whose method has no gateway keeps the provisional one and is noted
     *   Given a pending express order with its provisional gateway
     *   And a first-sight verdict with no gateway, naming the Mollie method creditcard
     *   When the writer records it
     *   Then the payment id, the transaction id and the mode are recorded
     *   And the payment method is still the provisional one
     *   And one note names the Mollie method, byte for byte
     *
     * @test
     */
    public function it_notes_the_mollie_method_and_keeps_the_payment_method_when_no_gateway_matches(): void
    {
        $order = $this->pendingOrder('mollie_wc_gateway_paypal');

        $this->recordFirstSight($order->get_id(), $this->firstSight(null, 'creditcard'));

        $fresh = wc_get_order($order->get_id());
        $this->assertSame(self::PAYMENT_ID, (string) $fresh->get_meta('_mollie_payment_id'));
        $this->assertSame(self::PAYMENT_ID, $fresh->get_transaction_id());
        $this->assertSame('mollie_wc_gateway_paypal', $fresh->get_payment_method());
        $this->assertSame([self::NOTE_UNKNOWN_WALLET], $this->notesContaining($fresh, 'Express checkout was paid with'));
    }

    /**
     * Scenario: only the allowlisted address fields are written
     *   Given a pending express order with nothing to ship
     *   And a first-sight verdict whose shipping fields hold a city and a field named "total"
     *   When the writer records it
     *   Then the shipping city is written
     *   And the order's shipping total is unchanged, since "total" is not an address field
     *
     * @test
     */
    public function it_writes_only_the_allowlisted_address_fields(): void
    {
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $shippingTotal = $order->get_shipping_total();

        $this->recordFirstSight($order->get_id(), new FirstSightData(
            self::PAYMENT_ID,
            'live',
            'mollie_wc_gateway_applepay',
            null,
            [],
            ['city' => 'Rotterdam', 'total' => '999.00']
        ));

        $fresh = wc_get_order($order->get_id());
        $this->assertSame('Rotterdam', $fresh->get_shipping_city());
        $this->assertSame($shippingTotal, $fresh->get_shipping_total());
    }

    /**
     * Scenario: an abandoned express order is cancelled with one note
     *   Given a pending express order
     *   When the writer cancels it as abandoned
     *   Then it is cancelled
     *   And it carries the note "Express checkout was started and not completed" once
     *   And the order was saved exactly once, and order.written was logged once
     *
     * @test
     */
    public function it_cancels_an_abandoned_order_with_one_note(): void
    {
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');

        $saves = $this->countingSaves(function () use ($order): void {
            $this->cancelAbandoned($order->get_id());
        });

        $this->assertGreaterThanOrEqual(1, $saves);
        $fresh = wc_get_order($order->get_id());
        $this->assertSame('cancelled', $fresh->get_status());
        $this->assertSame([self::NOTE_ABANDONED], $this->notesContaining($fresh, self::NOTE_ABANDONED));
        $this->assertCount(1, $this->orderWritten($order->get_id()));
    }

    /**
     * Scenario: making the same write twice changes nothing the second time
     *   Given an order one of the three writes was already made to
     *   When the identical write is made a second time, as a retried webhook or a repeated run would
     *   Then the order is not saved again
     *   And its status, meta, notes, payment method and addresses are exactly as after the first write
     *   And order.written was logged once, for the first write only
     *
     * @test
     * @dataProvider writes
     */
    public function it_saves_once_adds_one_note_and_logs_once_when_the_same_write_is_made_twice(string $write): void
    {
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $orderId = $order->get_id();

        $this->write($write, $orderId);
        $afterFirst = $this->fingerprint(wc_get_order($orderId));

        $saves = $this->countingSaves(function () use ($write, $orderId): void {
            $this->write($write, $orderId);
        });

        $this->assertSame(0, $saves, 'An unchanged order must not be saved again.');
        $this->assertSame($afterFirst, $this->fingerprint(wc_get_order($orderId)), 'A repeated write must be harmless: no second note, no changed meta.');
        $this->assertCount(1, $this->orderWritten($orderId), 'Only a call that wrote something logs order.written.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function writes(): array
    {
        return [
            'stamp a new order' => ['stamp'],
            'record first sight, wallet with a gateway' => ['first sight'],
            'record first sight, method without a gateway' => ['first sight, unknown wallet'],
            'cancel an abandoned order' => ['cancel'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function boot(): ContainerInterface
    {
        if ($this->container === null) {
            $this->container = $this->bootExpress();
        }

        return $this->container;
    }

    private function writer(): ExpressOrderWriter
    {
        $writer = $this->boot()->get(ExpressOrderWriter::class);
        $this->assertInstanceOf(ExpressOrderWriter::class, $writer);

        return $writer;
    }

    private function lock(): OrderLock
    {
        $lock = $this->boot()->get(OrderLock::class);
        $this->assertInstanceOf(OrderLock::class, $lock);

        return $lock;
    }

    /**
     * The writer is always given the order the lock read fresh, as the flows give it.
     */
    private function stamp(int $orderId): void
    {
        $writer = $this->writer();
        $session = new RememberedSession(self::SESSION_ID, self::EXPRESS_REF, 'fingerprint', self::EXPIRES_AT);
        $this->lock()->withFreshOrder($orderId, static function (WC_Order $fresh) use ($writer, $session): void {
            $writer->stampNewOrder($fresh, $session, 'live', 'mollie_wc_gateway_applepay', 'applepay');
        });
    }

    private function recordFirstSight(int $orderId, FirstSightData $firstSight): void
    {
        $writer = $this->writer();
        $this->lock()->withFreshOrder($orderId, static function (WC_Order $fresh) use ($writer, $firstSight): void {
            $writer->recordFirstSight($fresh, $firstSight);
        });
    }

    private function cancelAbandoned(int $orderId): void
    {
        $writer = $this->writer();
        $this->lock()->withFreshOrder($orderId, static function (WC_Order $fresh) use ($writer): void {
            $writer->cancelAbandoned($fresh);
        });
    }

    private function write(string $write, int $orderId): void
    {
        switch ($write) {
            case 'stamp':
                $this->stamp($orderId);
                break;
            case 'first sight':
                $this->recordFirstSight($orderId, $this->firstSight('mollie_wc_gateway_applepay', null));
                break;
            case 'first sight, unknown wallet':
                $this->recordFirstSight($orderId, $this->firstSight(null, 'creditcard'));
                break;
            case 'cancel':
                $this->cancelAbandoned($orderId);
                break;
            default:
                $this->fail("Unknown write {$write}.");
        }
    }

    /**
     * A first-sight verdict with the canary billing address in WooCommerce field names, no shipping.
     */
    private function firstSight(?string $gatewayId, ?string $unmatchedMethod): FirstSightData
    {
        return new FirstSightData(
            self::PAYMENT_ID,
            'live',
            $gatewayId,
            $unmatchedMethod,
            [
                'first_name' => CanaryData::GIVEN_NAME,
                'last_name' => CanaryData::FAMILY_NAME,
                'address_1' => CanaryData::STREET,
                'postcode' => '1015 CS',
                'city' => 'Amsterdam',
                'country' => 'NL',
                'email' => CanaryData::EMAIL,
                'phone' => CanaryData::PHONE,
            ],
            null
        );
    }

    private function gatewayTitle(string $gatewayId): string
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $this->assertArrayHasKey($gatewayId, $gateways, "The gateway {$gatewayId} is not registered in this test.");

        return $gateways[$gatewayId]->get_title();
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
     * The order.written events logged for one order.
     *
     * @return array<int, array{level: string, message: string, context: array<mixed>}>
     */
    private function orderWritten(int $orderId): array
    {
        return array_values(array_filter($this->logger()->records(), static function (array $record) use ($orderId): bool {
            return $record['message'] === 'order.written' && (int) ($record['context']['order'] ?? 0) === $orderId;
        }));
    }

    /**
     * Everything about the order a second write must leave alone.
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
            'created_via' => $order->get_created_via(),
            'transaction_id' => $order->get_transaction_id(),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'billing' => $order->get_address('billing'),
            'shipping' => $order->get_address('shipping'),
            'notes' => $notes,
            'meta' => $meta,
        ];
    }

    private function noteCount(WC_Order $order): int
    {
        return count(wc_get_order_notes(['order_id' => $order->get_id()]));
    }

    /**
     * @return array<int, string>
     */
    private function notesContaining(WC_Order $order, string $needle): array
    {
        $notes = array_map(static function ($note): string {
            return (string) $note->content;
        }, wc_get_order_notes(['order_id' => $order->get_id()]));

        return array_values(array_filter($notes, static function (string $note) use ($needle): bool {
            return strpos($note, $needle) !== false;
        }));
    }
}
