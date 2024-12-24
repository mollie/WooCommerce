<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\DefaultFieldsStrategy;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\PaymentFieldsStrategyI;
use Mollie\WooCommerce\Shared\Data;

class PaymentFieldsManager
{
    /**
     * @var PaymentFieldsStrategyI
     */
    protected $strategy;
    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * PaymentFieldsManager constructor.
     */
    public function __construct($dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    public function setStrategy($paymentMethod)
    {
        if (!$paymentMethod->getProperty('paymentFields')) {
            $this->strategy = new DefaultFieldsStrategy();
        } else {
            $className = 'Mollie\\WooCommerce\\PaymentMethods\\PaymentFieldsStrategies\\' . ucfirst($paymentMethod->getProperty('id')) . 'FieldsStrategy';
            $this->strategy = class_exists($className) ? new $className() : new DefaultFieldsStrategy();
        }
    }

    public function executeStrategy($gateway)
    {
        return $this->strategy->execute($gateway, $this->dataHelper);
    }

    public function getStrategyMarkup($gateway)
    {
        return $this->strategy->getFieldMarkup($gateway, $this->dataHelper);
    }
}
