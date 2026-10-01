<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\FirstSightData;
use Mollie\WooCommerce\Shared\Values\MollieAddress;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;

/**
 * The wallet's billing and contact details win over the order's: the shopper chose them in the sheet.
 */
final class FirstSight
{
    /**
     * @param array<string, array{gatewayId: string, paidAs: string}> $wallets
     * @param array<int, string> $registeredGatewayIds
     */
    public static function decide(
        PaymentSnapshot $payment,
        ExpressOrderFacts $order,
        array $wallets,
        array $registeredGatewayIds
    ): FirstSightData {

        $method = (string) $payment->method();
        $gatewayId = self::gatewayFor($method, $wallets, $registeredGatewayIds);

        $shipping = null;
        if (!$order->needsShipping() && !$order->holdsShipping()) {
            $shipping = self::address($payment->shippingAddress());
            $shipping = $shipping === [] ? null : $shipping;
        }

        return new FirstSightData(
            $payment->id(),
            $payment->mode(),
            $gatewayId,
            $gatewayId === null ? $method : null,
            self::address($payment->billingAddress()),
            $shipping
        );
    }

    /**
     * @param array<string, array{gatewayId: string, paidAs: string}> $wallets
     * @param array<int, string> $registeredGatewayIds
     */
    private static function gatewayFor(string $method, array $wallets, array $registeredGatewayIds): ?string
    {
        foreach ($wallets as $wallet) {
            if ($wallet['paidAs'] === $method && in_array($wallet['gatewayId'], $registeredGatewayIds, true)) {
                return $wallet['gatewayId'];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function address(?MollieAddress $address): array
    {
        return $address === null ? [] : AddressMapping::toWooCommerce($address->fields());
    }
}
