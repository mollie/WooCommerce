<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Decorator;

use Mollie\WooCommerce\Payment\Request\Decorators\RequestDecoratorInterface;
use Mollie\WooCommerce\Settings\Settings;
use WC_Order;

class StoreCustomerDecorator implements RequestDecoratorInterface
{
    private Settings $settingsHelper;
    public function __construct(Settings $settingsHelper)
    {
        $this->settingsHelper = $settingsHelper;
    }


    public function decorate(array $requestData, WC_Order $order): array
    {

        $storeCustomer = $this->settingsHelper->shouldStoreCustomer();
        $customerId = $order->get_meta('_mollie_customer_id');

        if ($storeCustomer && $customerId) {
            $requestData['payment']['customerId'] = $customerId;
        }

        return $requestData;
    }
}
