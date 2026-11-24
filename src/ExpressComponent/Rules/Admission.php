<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\Shared\Values\Admit;
use Mollie\WooCommerce\Shared\Values\Refuse;
/**
 * Input shape is validated by the route's args schema, not here.
 */
final class Admission
{
    public const EXPRESS_SESSION = 'express.session';
    public const EXPRESS_ORDER = 'express.order';
    private const SHIPPING_EVENT = 'shipping-address-changed';
    // An express route is admitted with a verified nonce.
    private const RULES = [self::EXPRESS_SESSION => ['nonce' => \true], self::EXPRESS_ORDER => ['nonce' => \true]];
    /**
     * @return Admit|Refuse
     */
    public static function admit(string $entryPoint, bool $noncePresent, bool $nonceValid)
    {
        $rule = self::RULES[$entryPoint] ?? null;
        if ($rule === null) {
            return new Refuse('unknown_entry_point', 403);
        }
        if ($rule['nonce'] && !$noncePresent) {
            return new Refuse('nonce_missing', 403);
        }
        if ($rule['nonce'] && !$nonceValid) {
            return new Refuse('nonce_invalid', 403);
        }
        return new Admit();
    }
    /**
     * Mollie's shipping callback has no cookie and no nonce: the secret in its URL, the session id and the event admit it.
     *
     * @return Admit|Refuse
     */
    public static function admitShippingCallback(bool $secretPresent, bool $secretMatchesSavedCart, bool $sessionMatchesSavedCart, string $event)
    {
        if (!$secretPresent) {
            return new Refuse('secret_missing', 403);
        }
        if (!$secretMatchesSavedCart) {
            return new Refuse('secret_invalid', 403);
        }
        if (!$sessionMatchesSavedCart) {
            return new Refuse('session_mismatch', 403);
        }
        if ($event !== self::SHIPPING_EVENT) {
            return new Refuse('event_unsupported', 403);
        }
        return new Admit();
    }
}
