<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\WooCommerce\Settings\Settings;
use WC_Order;
/**
 * Class StoreCustomerMiddleware
 *
 * Middleware to handle the storage of customer information.
 *
 * @package Mollie\WooCommerce\Payment\Request\Middleware
 */
class StoreCustomerMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
{
    /**
     * @var Settings The settings helper instance.
     */
    private Settings $settingsHelper;
    /**
     * StoreCustomerMiddleware constructor.
     *
     * @param Settings $settingsHelper The settings helper instance.
     */
    public function __construct(Settings $settingsHelper)
    {
        $this->settingsHelper = $settingsHelper;
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
        $storeCustomer = $this->settingsHelper->shouldStoreCustomer();
        if (!$storeCustomer) {
            if ($context === 'order') {
                unset($requestData['payment']['customerId']);
            } elseif ($context === 'payment') {
                unset($requestData['customerId']);
            }
        }
        return $next($requestData, $order, $context);
    }
}
