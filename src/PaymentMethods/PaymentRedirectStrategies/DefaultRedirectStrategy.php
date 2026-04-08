<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies;

use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use WC_Order;
class DefaultRedirectStrategy implements \Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies\PaymentRedirectStrategyI
{
    /**
     * Redirect location after successfully completing process_payment
     *
     * @param WC_Order $order
     * @param MollieOrder|MolliePayment $payment_object
     *
     */
    public function execute(PaymentMethodI $paymentMethod, $order, $paymentObject, string $redirectUrl)
    {
        return $paymentObject->getCheckoutUrl();
    }
}
