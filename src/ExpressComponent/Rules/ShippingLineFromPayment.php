<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShippingLine;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShippingTaxRate;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
/**
 * The shipping option the shopper chose in the wallet, as the line the order gets once.
 * Mollie records no tax on it: the option's price includes tax, split here per rate that taxes the order's
 * shipping the way WooCommerce splits a price that includes tax (compound rates first, then the regular rates
 * in proportion).
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
        $taxes = [];
        foreach (self::split($gross->minorUnits(), $order->shippingTaxRates()) as $rateId => $minor) {
            $taxes[$rateId] = Money::fromMinorUnits((int) round($minor), $gross->currency());
        }
        return new ShippingLine($fee->reference(), $fee->label(), $gross, $taxes);
    }
    /**
     * @param list<ShippingTaxRate> $rates
     * @return array<int, float> Unrounded tax in minor units per rate id, in the order of $rates.
     */
    private static function split(int $gross, array $rates): array
    {
        $taxes = [];
        $regular = [];
        $compound = [];
        foreach ($rates as $rate) {
            $taxes[$rate->rateId()] = 0.0;
            if ($rate->compound()) {
                $compound[] = $rate;
                continue;
            }
            $regular[] = $rate;
        }
        $beforeCompound = (float) $gross;
        foreach (array_reverse($compound) as $rate) {
            $tax = $beforeCompound - $beforeCompound / (1 + $rate->percent() / 100);
            $taxes[$rate->rateId()] += $tax;
            $beforeCompound -= $tax;
        }
        $regularPercent = 0.0;
        foreach ($regular as $rate) {
            $regularPercent += $rate->percent();
        }
        foreach ($regular as $rate) {
            $taxes[$rate->rateId()] += $beforeCompound * ($rate->percent() / 100) / (1 + $regularPercent / 100);
        }
        return $taxes;
    }
}
