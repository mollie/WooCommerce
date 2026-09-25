<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\CartFacts;
/**
 * The body of POST /v2/sessions.
 *
 * The plugin authenticates with an API key, so profileId and testmode are never sent. No order
 * exists when a session is created, so the metadata is what the caller passes, the express_ref.
 * The wallet is never asked for a shipping address: a cart that ships takes it from the checkout
 * form, and one that does not needs none.
 */
final class SessionPayload
{
    private const DESCRIPTION = 'Express checkout';
    private const NEVER_ASKED = ['shipping-address'];
    /**
     * @param list<array<string, mixed>> $lines From SessionLines::fromCart().
     * @param array<string, scalar> $metadata
     * @param list<string> $requiredCustomerDetails
     * @return array<string, mixed>
     */
    public static function build(CartFacts $cart, array $lines, string $redirectUrl, string $webhookUrl, array $metadata, array $requiredCustomerDetails): array
    {
        $total = $cart->total();
        return ['amount' => $total === null ? null : ['currency' => $total->currency(), 'value' => $total->toDecimal()], 'description' => self::DESCRIPTION, 'lines' => $lines, 'redirectUrl' => $redirectUrl, 'payment' => ['webhookUrl' => $webhookUrl], 'metadata' => $metadata, 'requiredCustomerDetails' => array_values(array_diff($requiredCustomerDetails, self::NEVER_ASKED))];
    }
    /**
     * Ask the wallet only for what the store does not already hold for this shopper.
     *
     * @return list<string>
     */
    public static function requiredCustomerDetails(bool $hasEmail, bool $hasBillingAddress): array
    {
        $details = [];
        if (!$hasEmail) {
            $details[] = 'email';
        }
        if (!$hasBillingAddress) {
            $details[] = 'billing-address';
        }
        return $details;
    }
}
