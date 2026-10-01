<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

/**
 * Breaks one rule: the request is parsed once at the edge, never read in the core (ADR-013).
 */
final class ReadsTheRequest
{
    public static function decide(): string
    {
        return isset($_POST['express_ref']) ? (string) $_POST['express_ref'] : '';
    }
}
