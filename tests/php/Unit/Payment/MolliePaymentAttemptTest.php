<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment;

use Mockery;
use Mollie\WooCommerce\Payment\MolliePaymentAttempt;
use Mollie\WooCommerceTests\TestCase;
use WC_Order;

/**
 * @covers \Mollie\WooCommerce\Payment\MolliePaymentAttempt
 */
class MolliePaymentAttemptTest extends TestCase
{
    private const ORDER_ID = 42;

    /**
     * @dataProvider paymentRecordsProvider
     * @test
     */
    public function wasCreatedForOrder_reads_the_order_id_mollie_stored_in_the_payment_metadata(
        $payment,
        bool $expected
    ): void {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(self::ORDER_ID);

        self::assertSame($expected, MolliePaymentAttempt::wasCreatedForOrder($order, $payment));
    }

    public function paymentRecordsProvider(): array
    {
        return [
            'metadata object names this order (int)' => [
                (object) ['id' => 'tr_a', 'metadata' => (object) ['order_id' => 42]],
                true,
            ],
            'metadata object names this order (string, as Mollie returns it)' => [
                (object) ['id' => 'tr_a', 'metadata' => (object) ['order_id' => '42']],
                true,
            ],
            'metadata as array names this order' => [
                (object) ['id' => 'tr_a', 'metadata' => ['order_id' => 42]],
                true,
            ],
            'metadata names another order' => [
                (object) ['id' => 'tr_a', 'metadata' => (object) ['order_id' => 43]],
                false,
            ],
            'metadata names another order whose id merely starts the same' => [
                (object) ['id' => 'tr_a', 'metadata' => (object) ['order_id' => '420']],
                false,
            ],
            'order id with trailing garbage is not this order' => [
                (object) ['id' => 'tr_a', 'metadata' => (object) ['order_id' => '42abc']],
                false,
            ],
            'payment without metadata' => [
                (object) ['id' => 'tr_a'],
                false,
            ],
            'payment with null metadata' => [
                (object) ['id' => 'tr_a', 'metadata' => null],
                false,
            ],
            'metadata without order_id' => [
                (object) ['id' => 'tr_a', 'metadata' => (object) ['order_number' => '42']],
                false,
            ],
            'metadata is a free-text string mentioning the id' => [
                (object) ['id' => 'tr_a', 'metadata' => 'order 42'],
                false,
            ],
            'order_id is not a scalar' => [
                (object) ['id' => 'tr_a', 'metadata' => (object) ['order_id' => [42]]],
                false,
            ],
            'payment is not an object' => [
                null,
                false,
            ],
        ];
    }

    /**
     * @test
     */
    public function isCurrentAttempt_accepts_only_the_ids_the_order_is_linked_to(): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_transaction_id')->andReturn('tr_current');
        $order->shouldReceive('get_meta')->with('_mollie_payment_id', true)->andReturn('tr_current');
        $order->shouldReceive('get_meta')->with('_mollie_order_id', true)->andReturn('ord_current');

        self::assertTrue(MolliePaymentAttempt::isCurrentAttempt($order, 'tr_current'));
        self::assertTrue(MolliePaymentAttempt::isCurrentAttempt($order, 'ord_current'));
        self::assertFalse(MolliePaymentAttempt::isCurrentAttempt($order, 'tr_other'));
        self::assertFalse(MolliePaymentAttempt::isCurrentAttempt($order, ''));
    }
}
