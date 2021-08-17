<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\DefaultFieldsStrategy;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\PaymentFieldsStrategyI;
use Mollie\WooCommerce\Utils\Data;
use Psr\Log\LoggerInterface as Logger;

class PaymentFieldsService
{
    protected $gateway;
    /**
     * @var NoticeInterface
     */
    protected $notice;
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var PaymentFieldsStrategyI
     */
    protected $strategy;
    /**
     * @var Data
     */
    protected $dataHelper;


    /**
     * PaymentService constructor.
     */
    public function __construct($dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }
    public function setStrategy($gateway)
    {
        if (!$gateway->paymentMethod->getProperty('paymentFields')) {
            $this->strategy = new DefaultFieldsStrategy();
        } else {
            $className = 'Mollie\\WooCommerce\\PaymentMethods\\PaymentFieldsStrategies\\' .ucfirst($gateway->paymentMethod->getProperty('id')) . 'FieldsStrategy';
            $this->strategy = class_exists($className) ? new $className() : new DefaultFieldsStrategy();
        }
    }

    public function executeStrategy($gateway)
    {
        return $this->strategy->execute($gateway, $this->dataHelper);
    }
}
