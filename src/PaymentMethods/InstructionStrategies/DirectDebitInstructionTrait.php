<?php

namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

trait DirectDebitInstructionTrait
{
    public function executeDirectDebit(
        $gateway,
        $payment,
        $order = null,
        $admin_instructions = false
    ) {

        if ($payment->isPaid() && $payment->details) {
            $consumerName = $payment->details->consumerName ?? '';
            $consumerAccount = $payment->details->consumerAccount ? substr($payment->details->consumerAccount, -4) : '';
            $consumerBic = $payment->details->consumerBic ?? '';
            return sprintf(
            /* translators: Placeholder 1: consumer name, placeholder 2: consumer IBAN, placeholder 3: consumer BIC */
                __('Payment completed by <strong>%1$s</strong> (IBAN (last 4 digits): %2$s, BIC: %3$s)', 'mollie-payments-for-woocommerce'),
                $consumerName,
                $consumerAccount,
                $consumerBic
            );
        }
        $defaultStrategy = new DefaultInstructionStrategy();
        return $defaultStrategy->execute($gateway, $payment, $admin_instructions);
    }
}
