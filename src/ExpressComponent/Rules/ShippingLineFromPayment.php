<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShippingLine;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;

/**
 * The shipping option the shopper chose in the wallet, as the line the order gets once.
 * Mollie records no VAT on it: the option's price includes VAT, split here at the order's shipping rate.
 */
final class ShippingLineFromPayment
{
    public static function decide(ExpressOrderFacts $order, PaymentSnapshot $payment): ?ShippingLine
    {
        $fee = $payment->shippingFee();
        if ($fee === null || !$order->needsShipping() || $order->holdsShippingLine()) {
            return null;
        }

        $gross = $fee->amount();
        $net = (int) round($gross->minorUnits() / (1 + $order->shippingTaxRate() / 100));

        return new ShippingLine(
            $fee->reference(),
            $fee->label(),
            $gross,
            Money::fromMinorUnits($net, $gross->currency())
        );
    }
}
