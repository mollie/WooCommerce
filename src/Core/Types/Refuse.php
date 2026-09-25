<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * The entry point must stop, with a reason code for the log and the status code to answer.
 */
final class Refuse
{
    private string $code;
    private int $httpStatus;
    public function __construct(string $code, int $httpStatus)
    {
        $this->code = $code;
        $this->httpStatus = $httpStatus;
    }
    public function code(): string
    {
        return $this->code;
    }
    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
