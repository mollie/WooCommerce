<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

class MybankInstructionStrategy implements InstructionStrategyI
{
    /**
     * @param mixed $gateway
     * @param mixed $payment
     * @param mixed $order
     * @param bool  $admin_instructions
     * @return mixed
     */
    public function execute(
        $gateway,
        $payment,
        $order = null,
        $admin_instructions = false
    ) {

        if ($payment->isPaid() && $payment->details) {
            return sprintf(
            /* translators: Placeholder 1: Mollie_WC_Gateway_MyBank consumer name, placeholder 2: Consumer Account number */
                __(
                    'Payment completed by <strong>%1$s</strong> - %2$s',
                    'mollie-payments-for-woocommerce'
                ),
                $payment->details->consumerName,
                $payment->details->consumerAccount
            );
        }

        $defaultStrategy = new DefaultInstructionStrategy();
        return $defaultStrategy->execute($gateway, $payment, $admin_instructions);
    }
}
