<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies;

use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use WC_Order;

class DefaultRedirectStrategy implements PaymentRedirectStrategyI
{
    /**
     * Redirect location after successfully completing process_payment
     *
     * @param WC_Order $order
     * @param MollieOrder|MolliePayment $payment_object
     *
     * @throws \Exception
     */
    public function execute(PaymentMethodI $paymentMethod, $order, $paymentObject, string $redirectUrl): string
    {
        $checkoutUrl = $paymentObject->getCheckoutUrl();

        if ($checkoutUrl) {
            return $checkoutUrl;
        }

        throw new \Exception(__('There was a problem with the payment. Please, try another payment method', 'mollie-payments-for-woocommerce'));
    }
}
