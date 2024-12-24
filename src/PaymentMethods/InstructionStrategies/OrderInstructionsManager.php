<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\InstructionStrategies;

class OrderInstructionsManager
{
    protected $strategy;
    public function setStrategy($deprecatedGatewayHelper)
    {
        if (!$deprecatedGatewayHelper->paymentMethod()->getProperty('instructions')) {
            $this->strategy = new DefaultInstructionStrategy();
        } else {
            $className = 'Mollie\\WooCommerce\\PaymentMethods\\InstructionStrategies\\' . ucfirst($deprecatedGatewayHelper->paymentMethod()->getProperty('id')) . 'InstructionStrategy';
            $this->strategy = class_exists($className) ? new $className() : new DefaultInstructionStrategy();
        }
    }

    public function executeStrategy(
        $deprecatedGatewayHelper,
        $payment,
        $order = null,
        $admin_instructions = false
    ) {

        return $this->strategy->execute($deprecatedGatewayHelper, $payment, $order, $admin_instructions);
    }
}
