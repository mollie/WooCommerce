<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;

/**
 * Body of POST /v2/sessions. No profileId or testmode: the plugin authenticates with an API key.
 */
final class SessionPayload
{
    private const DESCRIPTION = 'Express checkout';

    /**
     * @param list<array<string, mixed>> $lines
     * @param array<string, scalar> $metadata
     * @param list<string> $requiredCustomerDetails
     * @return array<string, mixed>
     */
    public static function build(
        CartFacts $cart,
        array $lines,
        string $redirectUrl,
        string $webhookUrl,
        array $metadata,
        array $requiredCustomerDetails,
        ?string $shippingCallbackUrl = null
    ): array {

        $total = $cart->total();

        $payload = [
            'amount' => $total === null ? null : ['currency' => $total->currency(), 'value' => $total->toDecimal()],
            'description' => self::DESCRIPTION,
            'lines' => $lines,
            'redirectUrl' => $redirectUrl,
            'payment' => ['webhookUrl' => $webhookUrl],
            'metadata' => $metadata,
            'requiredCustomerDetails' => array_values($requiredCustomerDetails),
        ];
        // Mollie takes options or a callbackUrl, and adds the chosen option's shipping_fee line itself.
        if ($shippingCallbackUrl !== null) {
            $payload['shipping'] = ['callbackUrl' => $shippingCallbackUrl];
        }

        return $payload;
    }

    /**
     * Always asked: what the shopper picks in the wallet sheet wins over what the store holds.
     * The shipping address is asked for when something ships, which Mollie requires once shipping is set.
     *
     * @return list<string>
     */
    public static function requiredCustomerDetails(bool $needsShipping): array
    {
        return $needsShipping ? ['email', 'billing-address', 'shipping-address'] : ['email', 'billing-address'];
    }
}
