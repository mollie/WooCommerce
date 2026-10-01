<?php

declare(strict_types=1);

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

    // The express routes are anonymous: a valid nonce is the whole admission.
    private const RULES = [
        self::EXPRESS_SESSION => ['nonce' => true],
        self::EXPRESS_ORDER => ['nonce' => true],
    ];

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
}
