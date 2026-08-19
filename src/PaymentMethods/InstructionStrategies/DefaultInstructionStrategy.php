<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

class DefaultInstructionStrategy implements \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\InstructionStrategyI
{
    public function execute($gateway, $payment, $order = null, $admin_instructions = \false)
    {
        if ($payment->isOpen() || $payment->isPending()) {
            if ($admin_instructions) {
                // Message to admin
                return __("We have not received a definite payment status.", "mollie-payments-for-woocommerce");
            } else {
                // Message to customer
                return __("We have not received a definite payment status. You will receive an email as soon as we receive a confirmation of the bank/merchant.", "mollie-payments-for-woocommerce");
            }
        } elseif ($payment->isPaid()) {
            return sprintf(
                /* translators: Placeholder 1: payment method */
                __('Payment completed with <strong>%s</strong>', 'mollie-payments-for-woocommerce'),
                $gateway->get_title()
            );
        }
        return null;
    }
}
