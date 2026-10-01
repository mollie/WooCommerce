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
        array $requiredCustomerDetails
    ): array {

        $total = $cart->total();

        return [
            'amount' => $total === null ? null : ['currency' => $total->currency(), 'value' => $total->toDecimal()],
            'description' => self::DESCRIPTION,
            'lines' => $lines,
            'redirectUrl' => $redirectUrl,
            'payment' => ['webhookUrl' => $webhookUrl],
            'metadata' => $metadata,
            'requiredCustomerDetails' => array_values($requiredCustomerDetails),
        ];
    }

    /**
     * Always asked: what the shopper picks in the wallet sheet wins over what the store holds.
     *
     * @return list<string>
     */
    public static function requiredCustomerDetails(): array
    {
        return ['email', 'billing-address'];
    }
}
