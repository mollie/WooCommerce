<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\MollieAddress;
use Mollie\WooCommerce\Core\Types\PaymentSnapshot;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\FirstSightData;

/**
 * What a matched express order is given the first time its payment is seen.
 *
 * Addresses, per type: the wallet's billing address and contact details win over whatever the order
 * holds, because the sheet is where the shopper chose them.
 * Writing the result twice changes nothing.
 */
final class FirstSight
{
    /**
     * @param array<string, array{gatewayId: string, paidAs: string}> $wallets The wallets table of config/express.php.
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

        // Upstream wins: the wallet's billing address and email replace what the order was given.
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
     * @return array<string, string> In WooCommerce field names; empty when the wallet gave none.
     */
    private static function address(?MollieAddress $address): array
    {
        return $address === null ? [] : AddressMapping::toWooCommerce($address->fields());
    }
}
