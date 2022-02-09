<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies;

use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use WC_Order;

class BanktransferRedirectStrategy implements PaymentRedirectStrategyI
{
    /**
     * Redirect location after successfully completing process_payment
     *
     * @param PaymentMethodI $paymentMethod
     * @param WC_Order  $order
     * @param MollieOrder|MolliePayment $payment_object
     *
     * @return string
     */
    public function execute(PaymentMethodI $paymentMethod, $order, $paymentObject, string $redirectUrl): string
    {
        if ($paymentMethod->getProperty('skip_mollie_payment_screen') === 'yes') {
            return add_query_arg(
                [
                    'utm_nooverride' => 1,
                ],
                $redirectUrl
            );
        }

        return $paymentObject->getCheckoutUrl();
    }
}
