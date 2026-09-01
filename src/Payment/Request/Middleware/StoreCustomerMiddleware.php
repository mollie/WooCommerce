<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\WooCommerce\Shared\Data;
use WC_Order;

/**
 * Class StoreCustomerMiddleware
 *
 * Middleware to handle the storage of customer information.
 *
 * @package Mollie\WooCommerce\Payment\Request\Middleware
 */
class StoreCustomerMiddleware implements RequestMiddlewareInterface
{
    /**
     * @var Data The data helper instance.
     */
    private Data $dataHelper;

    /**
     * StoreCustomerMiddleware constructor.
     *
     * @param Data $dataHelper The data helper instance.
     */
    public function __construct(Data $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    /**
     * Invoke the middleware.
     *
     * @param array<string, mixed> $requestData The request data to be modified.
     * @param WC_Order $order The WooCommerce order object.
     * @param string $context Additional context for the middleware.
     * @param callable $next The next middleware to be called.
     * @return array<string, mixed> The modified request data.
     */
    public function __invoke(array $requestData, WC_Order $order, string $context, callable $next): array
    {
        $allowCustomer = $this->dataHelper->isMollieCustomerAllowedForOrder($order->get_id());
        if (!$allowCustomer) {
            if ($context === 'order') {
                unset($requestData['payment']['customerId']);
            } elseif ($context === 'payment') {
                unset($requestData['customerId']);
            }
        }
        return $next($requestData, $order, $context);
    }
}
