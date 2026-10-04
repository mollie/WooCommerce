<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

class CreditcardInstructionStrategy implements \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\InstructionStrategyI
{
    public function execute($gateway, $payment, $order = null, $admin_instructions = \false)
    {
        if ($payment->isPaid() && $payment->details) {
            $cardHolder = isset($payment->details->cardHolder) ? $payment->details->cardHolder : 'Unknown';
            return sprintf(
                /* translators: Placeholder 1: card holder */
                __('Payment completed by <strong>%s</strong>', 'mollie-payments-for-woocommerce'),
                $cardHolder
            );
        }
        $defaultStrategy = new \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\DefaultInstructionStrategy();
        return $defaultStrategy->execute($gateway, $payment, $admin_instructions);
    }
}
