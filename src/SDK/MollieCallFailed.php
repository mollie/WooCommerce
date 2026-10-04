<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\SDK;

use RuntimeException;
use Throwable;
/**
 * Fixed message, no previous exception: the SDK's message contains Mollie's response body.
 */
final class MollieCallFailed extends RuntimeException
{
    public const VALIDATION = 'validation';
    public const RATE_LIMIT = 'rate_limit';
    public const OUTAGE = 'outage';
    public const NOT_FOUND = 'not_found';
    private function __construct(private string $kind)
    {
        parent::__construct('The Mollie call failed: ' . $kind . '.');
    }
    public static function fromThrowable(Throwable $error): self
    {
        return new self(match ((int) $error->getCode()) {
            422 => self::VALIDATION,
            429 => self::RATE_LIMIT,
            default => self::OUTAGE,
        });
    }
    /**
     * A read tells Mollie not holding the resource apart from an outage.
     */
    public static function fromRead(Throwable $error): self
    {
        return (int) $error->getCode() === 404 ? new self(self::NOT_FOUND) : self::fromThrowable($error);
    }
    /**
     * @return 'validation'|'rate_limit'|'outage'|'not_found'
     */
    public function kind(): string
    {
        return $this->kind;
    }
}
