<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\WooCommerce\Gateway\MolliePaymentGatewayI;
use Mollie\WooCommerce\PaymentMethods\InstructionStrategies\DefaultInstructionStrategy;

class OrderInstructionsService
{
    protected $strategy;
    public function setStrategy($gateway)
    {
        if (!$gateway->paymentMethod()->getProperty('instructions')) {
            $this->strategy = new DefaultInstructionStrategy();
        } else {
            $className = 'Mollie\\WooCommerce\\PaymentMethods\\InstructionStrategies\\' . ucfirst($gateway->paymentMethod()->getProperty('id')) . 'InstructionStrategy';
            $this->strategy = class_exists($className) ? new $className() : new DefaultInstructionStrategy();
        }
    }

    public function executeStrategy(
        MolliePaymentGatewayI $gateway,
        $payment,
        $order = null,
        $admin_instructions = false
    ) {

        return $this->strategy->execute($gateway, $payment, $order, $admin_instructions);
    }
}
