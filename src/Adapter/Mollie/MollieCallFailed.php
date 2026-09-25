<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\Mollie;

use RuntimeException;
use Throwable;
/**
 * A Mollie call failed, classified. The message is fixed and never carries Mollie's text: the SDK's
 * exception message contains Mollie's response body, so it is not passed on, and
 * neither is the previous exception.
 */
final class MollieCallFailed extends RuntimeException
{
    public const VALIDATION = 'validation';
    public const RATE_LIMIT = 'rate_limit';
    public const OUTAGE = 'outage';
    private string $kind;
    private function __construct(string $kind)
    {
        parent::__construct('The Mollie call failed: ' . $kind . '.');
        $this->kind = $kind;
    }
    /**
     * 422 is a refused payload, 429 a rate limit, anything else (other 4xx, 5xx, transport) an outage.
     */
    public static function fromThrowable(Throwable $error): self
    {
        return new self(match ((int) $error->getCode()) {
            422 => self::VALIDATION,
            429 => self::RATE_LIMIT,
            default => self::OUTAGE,
        });
    }
    /**
     * @return 'validation'|'rate_limit'|'outage'
     */
    public function kind(): string
    {
        return $this->kind;
    }
}
