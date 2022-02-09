<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

class DirectdebitInstructionStrategy implements InstructionStrategyI
{

    public function execute(
        $gateway,
        $payment,
        $order = null,
        $admin_instructions = false
    ) {

        if ($payment->isPaid() && $payment->details) {
            return sprintf(
            /* translators: Placeholder 1: consumer name, placeholder 2: consumer IBAN, placeholder 3: consumer BIC */
                __('Payment completed by <strong>%1$s</strong> (IBAN (last 4 digits): %2$s, BIC: %3$s)', 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
                substr($payment->details->consumerAccount, -4),
                $payment->details->consumerBic
            );
        }
        $defaultStrategy = new DefaultInstructionStrategy();
        return $defaultStrategy->execute($gateway, $payment, $admin_instructions);
    }
}
