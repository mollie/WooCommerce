<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

class ApplepayInstructionStrategy implements InstructionStrategyI
{

    public function execute(
        $gateway,
        $payment,
        $order = null,
        $admin_instructions = false
    ) {

        if ($payment->isPaid() && $payment->details) {
            return
                __(
                /* translators: Placeholder 1: PayPal consumer name, placeholder 2: PayPal email, placeholder 3: PayPal transaction ID */
                    sprintf(
                        'Payment completed by <strong>%1$s</strong> - %2$s (Apple Pay transaction ID: %3$s)',
                        $payment->details->consumerName,
                        $payment->details->consumerAccount,
                        $payment->details->paypalReference
                    ),
                    'mollie-payments-for-woocommerce'
                );
        }
        $defaultStrategy = new DefaultInstructionStrategy();
        return $defaultStrategy->execute($gateway, $payment, $admin_instructions);
    }
}
