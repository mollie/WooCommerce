<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

/**
 * An express payment is not paid with the method chosen in the checkout form, so what follows that
 * choice, such as a gateway surcharge, must not be in the cart while express prices or orders it.
 */
final class FormPaymentMethod
{
    private const SESSION_KEY = 'chosen_payment_method';

    /**
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public static function setAsideDuring(callable $work)
    {
        $session = function_exists('WC') && WC()->session instanceof \WC_Session ? WC()->session : null;
        if ($session === null) {
            return $work();
        }

        $chosen = $session->get(self::SESSION_KEY);
        $session->set(self::SESSION_KEY, '');
        try {
            return $work();
        } finally {
            $session->set(self::SESSION_KEY, $chosen);
        }
    }
}
