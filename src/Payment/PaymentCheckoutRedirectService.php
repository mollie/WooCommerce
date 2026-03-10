<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment;

use Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies\DefaultRedirectStrategy;
use Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies\PaymentRedirectStrategyI;
use Mollie\WooCommerce\Shared\Data;
use WC_Order;
class PaymentCheckoutRedirectService
{
    /**
     * @var PaymentRedirectStrategyI
     */
    protected $strategy;
    /**
     * @var Data
     */
    protected $dataHelper;
    /**
     * PaymentCheckoutRedirectService constructor.
     */
    public function __construct($dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }
    public function setStrategy($paymentMethod)
    {
        if (!$paymentMethod->getProperty('customRedirect')) {
            $this->strategy = new DefaultRedirectStrategy();
            return;
        }
        $className = 'Mollie\WooCommerce\PaymentMethods\PaymentRedirectStrategies\\' . ucfirst($paymentMethod->getProperty('id')) . 'RedirectStrategy';
        $this->strategy = class_exists($className) ? new $className() : new DefaultRedirectStrategy();
    }
    /**
     * @throws \Exception
     */
    public function executeStrategy($paymentMethod, $order, $paymentObject, $redirectUrl)
    {
        return $this->strategy->execute($paymentMethod, $order, $paymentObject, $redirectUrl);
    }
}
