<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\PaymentMethods\InstructionStrategies\DefaultInstructionStrategy;

class OrderInstructionsService
{
    protected $strategy;
    public function setStrategy($gateway)
    {
        if (!$gateway->paymentMethod->getProperty('instructions')) {
            $this->strategy = new DefaultInstructionStrategy();
        } else {
            $className = 'Mollie\\WooCommerce\\PaymentMethods\\InstructionStrategies\\' . ucfirst($gateway->paymentMethod->getProperty('id')) . 'InstructionsStrategy';
            $this->strategy = class_exists($className) ? new $className() : new DefaultInstructionStrategy();
        }
    }
    public function executeStrategy(
        MolliePaymentGateway $gateway,
        $payment,
        $admin_instructions = false,
        $order = null
    ) {
        return $this->strategy->execute($gateway, $payment, $admin_instructions, $order);
    }
}
