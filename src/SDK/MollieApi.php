<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\SDK;

use Mollie\WooCommerce\Shared\Values\ExpressSession;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;

interface MollieApi
{
    /**
     * @param array<string, mixed> $payload Body of POST /v2/sessions.
     * @throws MollieCallFailed
     */
    public function createSession(array $payload, string $idempotencyKey): ExpressSession;

    public function session(string $sessionId): ExpressSession;

    /**
     * @throws MollieCallFailed
     */
    public function payment(string $paymentId): PaymentSnapshot;
}
