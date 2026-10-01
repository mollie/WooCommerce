<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\CartFacts;

/**
 * The body of POST /v2/sessions.
 *
 * The plugin authenticates with an API key, so profileId and testmode are never sent. No order
 * exists when a session is created, so the metadata is what the caller passes, the express_ref.
 * Which customer details the wallet is asked for depends on one thing only: whether the cart ships
 * (see requiredCustomerDetails).
 */
final class SessionPayload
{
    private const DESCRIPTION = 'Express checkout';

    /**
     * @param list<array<string, mixed>> $lines From SessionLines::fromCart().
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
     * The contact details and the billing address are always asked of the wallet, whatever the store
     * already holds: the sheet governs which address and which contact the shopper picks, and what
     * comes back from it takes precedence.
     *
     * @return list<string>
     */
    public static function requiredCustomerDetails(): array
    {
        return ['email', 'billing-address'];
    }
}
