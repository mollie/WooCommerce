<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies;

use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use WC_Order;

interface PaymentRedirectStrategyI
{
    /**
     * @param PaymentMethodI $paymentMethod
     * @param mixed          $order
     * @param mixed          $paymentObject
     * @param string         $redirectUrl
     * @return mixed
     */
    public function execute(PaymentMethodI $paymentMethod, $order, $paymentObject, string $redirectUrl);
}
