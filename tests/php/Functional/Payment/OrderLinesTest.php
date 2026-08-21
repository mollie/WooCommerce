<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Functional\Payment;

use Mockery;
use Mollie\WooCommerce\Payment\LineItems\OrderLines;
use Mollie\WooCommerce\Payment\LineItems\PaymentLines;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\TestCase;

/**
 * @covers \Mollie\WooCommerce\Payment\LineItems\OrderLines
 * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines
 */
class OrderLinesTest extends TestCase
{
    /** @var Data&\Mockery\MockInterface */
    private $dataHelper;
    private OrderLines $sut;
    private PaymentLines $paymentLinesSut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataHelper = Mockery::mock(Data::class);
        $this->sut = new OrderLines($this->dataHelper, 'mollie-test');
        $this->paymentLinesSut = new PaymentLines($this->dataHelper, 'mollie-test');
    }

    /**
     * @scenario get_item_quantity() returns 15 (int) for a cart item with quantity 1.5 (scale factor 10)
     * @covers \Mollie\WooCommerce\Payment\LineItems\OrderLines::get_item_quantity
     */
    public function test_get_item_quantity_scales_fractional_to_integer(): void
    {
        // Arrange
        $cartItem = ['quantity' => 1.5];

        // When
        $result = $this->callPrivate($this->sut, 'get_item_quantity', $cartItem);

        // Then
        self::assertSame(15, $result);
    }

    /**
     * @scenario get_item_price() returns line_subtotal_incl_tax / 15 for qty 1.5, so (unitPrice × 15) − discountAmount == totalAmount
     * @covers \Mollie\WooCommerce\Payment\LineItems\OrderLines::get_item_price
     */
    public function test_get_item_price_divides_by_scaled_quantity(): void
    {
        // Arrange
        $cartItem = [
            'quantity' => 1.5,
            'line_subtotal' => 10.0,
            'line_subtotal_tax' => 2.0,
        ];

        // When
        $result = $this->callPrivate($this->sut, 'get_item_price', $cartItem);

        // Then
        self::assertEqualsWithDelta(0.8, $result, 1e-10);
    }

    /**
     * @scenario get_item_quantity() returns 4 (int) for a cart item with quantity 0.4 (scale factor 10)
     * @covers \Mollie\WooCommerce\Payment\LineItems\OrderLines::get_item_quantity
     */
    public function test_get_item_quantity_scales_point_four_to_four(): void
    {
        // Arrange
        $cartItem = ['quantity' => 0.4];

        // When
        $result = $this->callPrivate($this->sut, 'get_item_quantity', $cartItem);

        // Then
        self::assertSame(4, $result);
    }

    /**
     * @scenario get_item_quantity() returns 1 (not 0) for a cart item with quantity below 0.5 that rounds to zero (repeating-decimal fallback path)
     * @covers \Mollie\WooCommerce\Payment\LineItems\OrderLines::get_item_quantity
     */
    public function test_get_item_quantity_clamps_repeating_decimal_fallback_to_one(): void
    {
        // Arrange — 1/3 ≈ 0.3333… has no exact match in d=0..4; fallback round()→0 → clamped to 1
        $cartItem = ['quantity' => (float)(1 / 3)];

        // When
        $result = $this->callPrivate($this->sut, 'get_item_quantity', $cartItem);

        // Then
        self::assertSame(1, $result);
    }

    /**
     * @scenario get_item_quantity() returns the original integer unchanged for a cart item with integer quantity 3 (d=0 matches immediately)
     * @covers \Mollie\WooCommerce\Payment\LineItems\OrderLines::get_item_quantity
     */
    public function test_get_item_quantity_preserves_integer_quantity_unchanged(): void
    {
        // Arrange
        $cartItem = ['quantity' => 3];

        // When
        $result = $this->callPrivate($this->sut, 'get_item_quantity', $cartItem);

        // Then
        self::assertSame(3, $result);
    }

    /**
     * @scenario Both OrderLines and PaymentLines exhibit identical normalisation behaviour for the same input quantity
     * @covers \Mollie\WooCommerce\Payment\LineItems\OrderLines::get_item_quantity
     * @covers \Mollie\WooCommerce\Payment\LineItems\PaymentLines::get_item_quantity
     */
    public function test_payment_lines_normalises_quantity_identically(): void
    {
        // Arrange
        $cartItem = [
            'quantity' => 1.5,
            'line_subtotal' => 10.0,
            'line_subtotal_tax' => 2.0,
        ];

        // When
        $orderQty   = $this->callPrivate($this->sut, 'get_item_quantity', $cartItem);
        $paymentQty = $this->callPrivate($this->paymentLinesSut, 'get_item_quantity', $cartItem);
        $orderPrice   = $this->callPrivate($this->sut, 'get_item_price', $cartItem);
        $paymentPrice = $this->callPrivate($this->paymentLinesSut, 'get_item_price', $cartItem);

        // Then
        self::assertSame(15, $orderQty);
        self::assertSame(15, $paymentQty);
        self::assertEqualsWithDelta(0.8, $orderPrice, 1e-10);
        self::assertEqualsWithDelta(0.8, $paymentPrice, 1e-10);
    }

    private function callPrivate(object $obj, string $method, ...$args)
    {
        return (new \ReflectionMethod($obj, $method))->invoke($obj, ...$args);
    }
}
