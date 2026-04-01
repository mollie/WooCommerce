<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies;

use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
class DefaultRedirectStrategy implements \Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies\PaymentRedirectStrategyI
{
    /**
     * Redirect location after successfully completing process_payment
     *
     * @param PaymentMethodI $paymentMethod
     * @param mixed          $order
     * @param mixed          $paymentObject
     * @param string         $redirectUrl
     * @return mixed
     */
    public function execute(PaymentMethodI $paymentMethod, $order, $paymentObject, string $redirectUrl)
    {
        return $paymentObject->getCheckoutUrl();
    }
}
