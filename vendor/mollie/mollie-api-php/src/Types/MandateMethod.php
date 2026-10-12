<?php

namespace Mollie\Api\Types;

class MandateMethod
{
    public const DIRECTDEBIT = "directdebit";
    public const CREDITCARD = "creditcard";
    public const PAYPAL = "paypal";
    /**
     * @param string $firstPaymentMethod
     * @return string
     */
    public static function getForFirstPaymentMethod($firstPaymentMethod)
    {
        if ($firstPaymentMethod === \Mollie\Api\Types\PaymentMethod::PAYPAL) {
            return static::PAYPAL;
        }
        if (in_array($firstPaymentMethod, [\Mollie\Api\Types\PaymentMethod::APPLEPAY, \Mollie\Api\Types\PaymentMethod::CREDITCARD])) {
            return static::CREDITCARD;
        }
        return static::DIRECTDEBIT;
    }
}
