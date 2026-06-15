<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Request\Middleware;

use Mockery;
use Mollie\WooCommerce\Payment\LineItems\LineItemProvider;
use Mollie\WooCommerce\Payment\Request\Middleware\OrderLinesMiddleware;
use Mollie\WooCommerceTests\TestCase;
use WC_Order;

use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Payment\Request\Middleware\OrderLinesMiddleware
 */
class OrderLinesMiddlewareTest extends TestCase
{
    /** @var LineItemProvider&\Mockery\MockInterface */
    private $orderLines;

    /** @var LineItemProvider&\Mockery\MockInterface */
    private $paymentLines;

    private OrderLinesMiddleware $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderLines = Mockery::mock(LineItemProvider::class);
        $this->paymentLines = Mockery::mock(LineItemProvider::class);
        $this->sut = new OrderLinesMiddleware($this->orderLines, $this->paymentLines);
    }

    /**
     * @scenario When get_option() returns an array without a hide_order_lines key (e.g. Klarna, Billie), no PHP Warning is emitted and $hideOrderLines evaluates to false.
     * @covers \Mollie\WooCommerce\Payment\Request\Middleware\OrderLinesMiddleware::__invoke
     */
    public function testInvokeWithMissingHideOrderLinesKeyIncludesPaymentLines(): void
    {
        when('get_option')->justReturn(['other_setting' => 'value']);
        $order = new WC_Order();
        $expectedLines = [['name' => 'item1']];
        $this->paymentLines->shouldReceive('order_lines')->once()->with($order)->andReturn($expectedLines);

        $warningFired = false;
        set_error_handler(static function (int $errno) use (&$warningFired): bool {
            if ($errno === E_WARNING) {
                $warningFired = true;
            }
            return true;
        }, E_WARNING);

        $result = ($this->sut)(['method' => 'klarna'], $order, 'payment', static fn($data, $o, $c) => $data);

        restore_error_handler();

        self::assertFalse($warningFired, 'No PHP Warning should be emitted when hide_order_lines key is absent.');
        self::assertSame($expectedLines, $result['lines']);
    }

    /**
     * @scenario When get_option() returns an array with hide_order_lines set to 'yes', $hideOrderLines evaluates to true and order lines are skipped.
     * @covers \Mollie\WooCommerce\Payment\Request\Middleware\OrderLinesMiddleware::__invoke
     */
    public function testInvokeWithHideOrderLinesYesSkipsPaymentLines(): void
    {
        when('get_option')->justReturn(['hide_order_lines' => 'yes']);
        $order = new WC_Order();
        $this->paymentLines->shouldNotReceive('order_lines');

        $result = ($this->sut)(['method' => 'ideal'], $order, 'payment', static fn($data, $o, $c) => $data);

        self::assertArrayNotHasKey('lines', $result);
    }

    /**
     * @scenario When get_option() returns an array with hide_order_lines set to any value other than 'yes' (e.g. 'no', '0', ''), $hideOrderLines evaluates to false and order lines are included.
     * @covers \Mollie\WooCommerce\Payment\Request\Middleware\OrderLinesMiddleware::__invoke
     */
    public function testInvokeWithHideOrderLinesNonYesIncludesPaymentLines(): void
    {
        when('get_option')->justReturn(['hide_order_lines' => 'no']);
        $order = new WC_Order();
        $expectedLines = [['name' => 'item1']];
        $this->paymentLines->shouldReceive('order_lines')->once()->with($order)->andReturn($expectedLines);

        $result = ($this->sut)(['method' => 'ideal'], $order, 'payment', static fn($data, $o, $c) => $data);

        self::assertSame($expectedLines, $result['lines']);
    }

    /**
     * @scenario When get_option() returns false (option does not exist at all), no PHP Warning is emitted and $hideOrderLines evaluates to false.
     * @covers \Mollie\WooCommerce\Payment\Request\Middleware\OrderLinesMiddleware::__invoke
     */
    public function testInvokeWithGetOptionReturnsFalseIncludesPaymentLines(): void
    {
        when('get_option')->justReturn(false);
        $order = new WC_Order();
        $expectedLines = [['name' => 'item1']];
        $this->paymentLines->shouldReceive('order_lines')->once()->with($order)->andReturn($expectedLines);

        $warningFired = false;
        set_error_handler(static function (int $errno) use (&$warningFired): bool {
            if ($errno === E_WARNING) {
                $warningFired = true;
            }
            return true;
        }, E_WARNING);

        $result = ($this->sut)(['method' => 'ideal'], $order, 'payment', static fn($data, $o, $c) => $data);

        restore_error_handler();

        self::assertFalse($warningFired, 'No PHP Warning should be emitted when get_option() returns false.');
        self::assertSame($expectedLines, $result['lines']);
    }
}