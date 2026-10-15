<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Shared\Values;

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
