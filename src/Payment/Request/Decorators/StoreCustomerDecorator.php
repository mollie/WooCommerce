<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use Mollie\WooCommerce\Settings\Settings;
use WC_Order;

class StoreCustomerDecorator implements RequestDecoratorInterface
{
    private Settings $settingsHelper;
    public function __construct(Settings $settingsHelper)
    {
        $this->settingsHelper = $settingsHelper;
    }

    public function decorate(array $requestData, WC_Order $order, $context = null): array
    {
        $storeCustomer = $this->settingsHelper->shouldStoreCustomer();
        $customerId = $order->get_meta('_mollie_customer_id');
        if (!$storeCustomer || !$customerId) {
            return $requestData;
        }
        if ($context === 'order') {
            $requestData['payment']['customerId'] = $customerId;
        } elseif ($context === 'payment') {
            $requestData['customerId'] = $customerId;
        }

        return $requestData;
    }
}
