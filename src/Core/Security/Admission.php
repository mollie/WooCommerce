<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Security;

use Mollie\WooCommerce\Core\Types\Admit;
use Mollie\WooCommerce\Core\Types\Refuse;
/**
 * Who may call an entry point, decided from request facts parsed once in the adapter (blueprint
 * chokepoint 1, ADR-013). It never looks at what the request asks for: the shape of the input is
 * the route's own args schema. An entry point the table does not know is refused.
 */
final class Admission
{
    public const EXPRESS_SESSION = 'express.session';
    public const EXPRESS_ORDER = 'express.order';
    /**
     * Per entry point, what must hold. The express routes are anonymous by design, so a nonce the
     * shop issued to this shopper is the whole of their admission.
     */
    private const RULES = [self::EXPRESS_SESSION => ['nonce' => \true], self::EXPRESS_ORDER => ['nonce' => \true]];
    /**
     * @return Admit|Refuse
     */
    public static function decide(string $entryPoint, bool $noncePresent, bool $nonceValid)
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
}
