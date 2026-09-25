<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Doubles;

use Mollie\WooCommerce\SDK\HttpResponse;

/**
 * Records the HTTP status codes the webhook service sets instead of writing them to a response.
 *
 * The production HttpResponse is a no-op under CLI (it guards on PHP_SAPI), so a test that only
 * inspects the order can never tell a 401 from a 200. The status code is not cosmetic: it is the
 * entire contract with Mollie's delivery system — a non-2xx makes Mollie retry the webhook, a 200
 * makes it stop. "The order was left alone" and "the caller was rejected" are different outcomes,
 * and only this double can tell them apart.
 */
class RecordingHttpResponse extends HttpResponse
{
    /**
     * @var array<int>
     */
    private array $codes = [];

    public function setHttpResponseCode($statusCode): void
    {
        $this->codes[] = (int) $statusCode;
    }

    /**
     * Every code set during the request, in order.
     *
     * @return array<int>
     */
    public function codes(): array
    {
        return $this->codes;
    }

    /**
     * The code the request ended on, or 200 when nothing was set — the webhook entry points only
     * call setHttpResponseCode() to signal a failure and otherwise fall through to an implicit 200.
     */
    public function lastCode(): int
    {
        return $this->codes === [] ? 200 : (int) end($this->codes);
    }

    public function reset(): void
    {
        $this->codes = [];
    }
}
