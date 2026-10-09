<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

use RangeException;
/**
 * @psalm-suppress MissingParamType
 */
interface SettingsFieldSanitizerInterface
{
    /**
     * @param string $key
     * @param mixed $value
     * @param PaymentGateway $gateway
     *
     * @return mixed
     * @throws RangeException
     */
    public function sanitize(string $key, $value, PaymentGateway $gateway);
}
