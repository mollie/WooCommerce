<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\Mollie;

use Mollie\WooCommerce\Core\Types\ExpressSession;
use Mollie\WooCommerce\Core\Types\PaymentSnapshot;

/**
 * Everything new code may ask of Mollie. One of the two interfaces the blueprint allows, because
 * it is one of the two things worth faking. Implementations return plain immutable snapshots.
 */
interface MollieApi
{
    /**
     * Creates a Checkout Session. A repeated call with the same key and payload returns the first session.
     *
     * @param array<string, mixed> $payload The body of POST /v2/sessions.
     * @param string $idempotencyKey Built with Core\Security\IdempotencyKey::for().
     *
     * @throws MollieCallFailed When Mollie refuses the payload or cannot be reached.
     */
    public function createSession(array $payload, string $idempotencyKey): ExpressSession;

    public function session(string $sessionId): ExpressSession;

    public function payment(string $paymentId): PaymentSnapshot;
}
