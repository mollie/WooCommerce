<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
class PaymentFactory
{
    private $mollieOrderFactory;
    private $molliePaymentFactory;
    /**
     * PaymentFactory constructor.
     */
    public function __construct(callable $mollieOrderFactory, callable $molliePaymentFactory)
    {
        $this->mollieOrderFactory = $mollieOrderFactory;
        $this->molliePaymentFactory = $molliePaymentFactory;
    }
    /**
     * @param $data
     * @return bool|MollieOrder|MolliePayment
     * @throws ApiException
     */
    public function getPaymentObject($data)
    {
        if (!is_object($data) && $data === 'order' || is_string($data) && strpos($data, 'ord_') !== \false || is_object($data) && $data->resource === 'order') {
            $mollieOrder = ($this->mollieOrderFactory)();
            $mollieOrder->setOrder($data);
            return $mollieOrder;
        }
        if (!is_object($data) && $data === 'payment' || !is_object($data) && strpos($data, 'tr_') !== \false || is_object($data) && $data->resource === 'payment') {
            $molliePayment = ($this->molliePaymentFactory)();
            $molliePayment->setPayment($data);
            return $molliePayment;
        }
        return \false;
    }
}
