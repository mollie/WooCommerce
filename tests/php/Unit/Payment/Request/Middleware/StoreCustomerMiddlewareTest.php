<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Request\Middleware;

use Mockery;
use Mollie\WooCommerce\Payment\Request\Middleware\StoreCustomerMiddleware;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\TestCase;

/**
 * @covers \Mollie\WooCommerce\Payment\Request\Middleware\StoreCustomerMiddleware
 */
class StoreCustomerMiddlewareTest extends TestCase
{
    private function callMiddleware(bool $allowed, array $requestData, string $context): array
    {
        $orderId = 42;

        $dataHelper = Mockery::mock(Data::class);
        $dataHelper->shouldReceive('isMollieCustomerAllowedForOrder')->with($orderId)->andReturn($allowed);

        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn($orderId);

        $sut = new StoreCustomerMiddleware($dataHelper);

        return $sut($requestData, $order, $context, static function (array $data) {
            return $data;
        });
    }

    /**
     * @test
     * @scenario When a Mollie Customer is not allowed for the order (setting disabled and not
     *           a subscription), customerId is stripped from the 'order' context payload.
     */
    public function stripsCustomerIdFromOrderContextWhenNotAllowed(): void
    {
        $result = $this->callMiddleware(false, ['payment' => ['customerId' => 'cst_123']], 'order');

        $this->assertArrayNotHasKey('customerId', $result['payment']);
    }

    /**
     * @test
     * @scenario When a Mollie Customer is not allowed for the order, customerId is stripped
     *           from the 'payment' context payload.
     */
    public function stripsCustomerIdFromPaymentContextWhenNotAllowed(): void
    {
        $result = $this->callMiddleware(false, ['customerId' => 'cst_123'], 'payment');

        $this->assertArrayNotHasKey('customerId', $result);
    }

    /**
     * @test
     * @scenario When a Mollie Customer is allowed for the order (setting enabled, or the order
     *           is subscription-related), customerId is left untouched.
     */
    public function keepsCustomerIdWhenAllowed(): void
    {
        $result = $this->callMiddleware(true, ['customerId' => 'cst_123'], 'payment');

        $this->assertSame('cst_123', $result['customerId']);
    }
}
