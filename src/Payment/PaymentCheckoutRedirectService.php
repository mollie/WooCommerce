<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies\DefaultRedirectStrategy;
use Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies\PaymentRedirectStrategyI;
use Mollie\WooCommerce\Utils\Data;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;

class PaymentCheckoutRedirectService
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
     * @var PaymentRedirectStrategyI
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
        if (!$gateway->paymentMethod->getProperty('customRedirect')) {
            $this->strategy = new DefaultRedirectStrategy();
        } else {
            $className = 'Mollie\\WooCommerce\\PaymentMethods\\PaymentRedirectStrategies\\' .ucfirst($gateway->paymentMethod->getProperty('id')) . 'RedirectStrategy';
            $this->strategy = class_exists($className) ? new $className() : new DefaultRedirectStrategy();
        }
    }

    public function executeStrategy($gateway, WC_Order $order, $paymentObject)
    {
        return $this->strategy->execute($gateway, $order, $paymentObject);
    }
}
