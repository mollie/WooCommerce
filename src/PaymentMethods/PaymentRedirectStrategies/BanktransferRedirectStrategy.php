<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies;

use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;

class BanktransferRedirectStrategy implements PaymentRedirectStrategyI
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
