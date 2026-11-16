<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

class ApplepayInstructionStrategy implements \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\InstructionStrategyI
{
    public function execute($gateway, $payment, $order = null, $admin_instructions = \false)
    {
        if ($payment->isPaid() && $payment->details) {
            return sprintf(
                /* translators: Placeholder 1: PayPal consumer name, placeholder 2: PayPal email, placeholder 3: PayPal transaction ID */
                __('Payment completed by <strong>%1$s</strong> - %2$s (Apple Pay transaction ID: %3$s)', 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
                $payment->details->consumerAccount,
                $payment->details->paypalReference
            );
        }
        $defaultStrategy = new \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\DefaultInstructionStrategy();
        return $defaultStrategy->execute($gateway, $payment, $admin_instructions);
    }
}
