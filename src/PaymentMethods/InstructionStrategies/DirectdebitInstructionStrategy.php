<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

class DirectdebitInstructionStrategy implements \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\InstructionStrategyI
{
    use \Mollie\WooCommerce\PaymentMethods\InstructionStrategies\DirectDebitInstructionTrait;
    /**
     * @param mixed $gateway
     * @param mixed $payment
     * @param mixed $order
     * @param bool  $admin_instructions
     * @return mixed
     */
    public function execute($gateway, $payment, $order = null, $admin_instructions = \false)
    {
        return $this->executeDirectDebit($gateway, $payment, $order, $admin_instructions);
    }
}
