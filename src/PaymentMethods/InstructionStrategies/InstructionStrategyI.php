<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

interface InstructionStrategyI
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
        $order,
        $admin_instructions = false
    );
}
