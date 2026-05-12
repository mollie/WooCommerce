<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies;

use Mollie\Api\Resources\Order as MollieApiOrder;
use Mollie\Api\Resources\Payment as MollieApiPayment;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use WC_Order;

class DefaultRedirectStrategy implements PaymentRedirectStrategyI
{
    /**
     * Redirect location after successfully completing process_payment
     *
     * @param WC_Order $order
     * @param MollieApiOrder|MollieApiPayment $paymentObject
     *
     */
    public function execute(PaymentMethodI $paymentMethod, $order, $paymentObject, string $redirectUrl)
    {
        return $paymentObject->getCheckoutUrl();
    }
}
