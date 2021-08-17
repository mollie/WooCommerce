<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MolliePayment;
use WC_Order;

class BanktransferRedirectStrategy implements PaymentRedirectStrategyI
{
    /**
     * Redirect location after successfully completing process_payment
     *
     * @param WC_Order  $order
     * @param MollieOrder|MolliePayment $payment_object
     *
     * @return string
     */
    public function execute($gateway, WC_Order $order, MollieObject $paymentObject): string
    {
        if ($gateway->get_option('skip_mollie_payment_screen') === 'yes') {
            /*
             * Redirect to order received page
             */
            $redirect_url = $gateway->get_return_url($order);

            // Add utm_nooverride query string
            $redirect_url = add_query_arg([
                                              'utm_nooverride' => 1,
                                          ], $redirect_url);

            return $redirect_url;
        }

        return parent::getProcessPaymentRedirect($order, $paymentObject);
    }
}
