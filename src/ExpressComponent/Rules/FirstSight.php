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
     * @param array<string, array{gatewayId: string, paidAs?: string, paidWallet?: string}> $wallets
     * @param array<int, string> $registeredGatewayIds
     */
    public static function decide(
        PaymentSnapshot $payment,
        ExpressOrderFacts $order,
        array $wallets,
        array $registeredGatewayIds
    ): FirstSightData {

        $method = (string) $payment->method();
        $gatewayId = self::walletGateway($method, $payment->wallet(), $wallets, $registeredGatewayIds);

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
     * The registered gateway of the wallet a payment was made with: by the method Mollie reports, or,
     * for a card payment, by details.wallet. A card payment without a wallet belongs to no wallet.
     *
     * @param array<string, array{gatewayId: string, paidAs?: string, paidWallet?: string}> $wallets
     * @param array<int, string> $registeredGatewayIds
     */
    public static function walletGateway(
        string $method,
        ?string $wallet,
        array $wallets,
        array $registeredGatewayIds
    ): ?string
    {
        foreach ($wallets as $row) {
            $byMethod = isset($row['paidAs']) && $row['paidAs'] === $method;
            $byWallet = $method === 'creditcard' && $wallet !== null && ($row['paidWallet'] ?? null) === $wallet;
            if (($byMethod || $byWallet) && in_array($row['gatewayId'], $registeredGatewayIds, true)) {
                return $row['gatewayId'];
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
