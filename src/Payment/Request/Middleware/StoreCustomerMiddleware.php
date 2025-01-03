<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\WooCommerce\Settings\Settings;
use WC_Order;

class StoreCustomerMiddleware implements RequestMiddlewareInterface
{
    private Settings $settingsHelper;
    public function __construct(Settings $settingsHelper)
    {
        $this->settingsHelper = $settingsHelper;
    }

    public function __invoke(array $requestData, WC_Order $order, $context = null, $next): array
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
