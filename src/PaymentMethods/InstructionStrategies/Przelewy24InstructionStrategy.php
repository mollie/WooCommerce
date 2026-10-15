<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

class Przelewy24InstructionStrategy implements \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\InstructionStrategyI
{
    public function execute($gateway, $payment, $order = null, $admin_instructions = \false)
    {
        if ($payment->isPaid() && $payment->details) {
            return sprintf(
                /* translators: Placeholder 1: customer billing email */
                __('Payment completed by <strong>%s</strong>.', 'mollie-payments-for-woocommerce'),
                $payment->details->billingEmail
            );
        }
        $defaultStrategy = new \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\DefaultInstructionStrategy();
        return $defaultStrategy->execute($gateway, $payment, $admin_instructions);
    }
}
