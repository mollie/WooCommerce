<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\Effect;
use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\MollieAddress;
use Mollie\WooCommerce\Core\Types\PaymentSnapshot;

/**
 * What a matched express order is given the first time its payment is seen.
 *
 * Addresses, per type: what the order holds wins; the wallet's fill only a type the order holds
 * nothing for; and an order that needs shipping keeps its shipping address, which priced it.
 * Applying the result twice changes nothing.
 */
final class FirstSightEffects
{
    public const NOTE_UNKNOWN_WALLET = 'express.payment.unknown_wallet';

    /**
     * @param array<string, array{gatewayId: string, mollieMethod: string}> $wallets The wallets table of config/express.php.
     * @param array<int, string> $registeredGatewayIds
     * @return list<Effect>
     */
    public static function for(
        PaymentSnapshot $payment,
        ExpressOrderFacts $order,
        array $wallets,
        array $registeredGatewayIds
    ): array {

        $effects = [
            Effect::setMeta('_mollie_payment_id', $payment->id()),
            Effect::setTransactionId($payment->id()),
            Effect::setMeta('_mollie_payment_mode', $payment->mode()),
        ];

        $method = (string) $payment->method();
        $gatewayId = self::gatewayFor($method, $wallets, $registeredGatewayIds);
        $effects[] = $gatewayId !== null
            ? Effect::setPaymentMethod($gatewayId)
            : Effect::addNote(self::NOTE_UNKNOWN_WALLET, ['method' => $method]);

        if (!$order->holdsBilling()) {
            array_push($effects, ...self::address('billing', $payment->billingAddress()));
        }
        if (!$order->needsShipping() && !$order->holdsShipping()) {
            array_push($effects, ...self::address('shipping', $payment->shippingAddress()));
        }

        return $effects;
    }

    /**
     * @param array<string, array{gatewayId: string, mollieMethod: string}> $wallets
     * @param array<int, string> $registeredGatewayIds
     */
    private static function gatewayFor(string $method, array $wallets, array $registeredGatewayIds): ?string
    {
        foreach ($wallets as $wallet) {
            if ($wallet['mollieMethod'] === $method && in_array($wallet['gatewayId'], $registeredGatewayIds, true)) {
                return $wallet['gatewayId'];
            }
        }

        return null;
    }

    /**
     * @return list<Effect>
     */
    private static function address(string $type, ?MollieAddress $address): array
    {
        $fields = $address === null ? [] : AddressMapping::toWooCommerce($address->fields());

        return $fields === [] ? [] : [Effect::setAddress($type, $fields)];
    }
}
