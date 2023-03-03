<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

class SofortInstructionStrategy implements InstructionStrategyI
{
    use DirectDebitInstructionTrait;

    public function execute($gateway, $payment, $order = null, $admin_instructions = false)
    {
        return $this->executeDirectDebit($gateway, $payment, $order, $admin_instructions);
    }
}
