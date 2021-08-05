<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Payment\OrderItemsRefunder;
use Mollie\WooCommerce\Plugin;

class PaymentFactory
{
    /**
     * @param $data
     * @return bool|MollieOrder|MolliePayment
     * @throws ApiException
     */
    public static function getPaymentObject($data)
    {
        if ((!is_object($data) && $data == 'order')
            || (!is_object($data) && strpos($data, 'ord_') !== false)
            || (is_object($data) && $data->resource == 'order')
        ) {
            $dataHelper = Plugin::getDataHelper();
            $refundLineItemsBuilder = new RefundLineItemsBuilder($dataHelper);
            $apiHelper = Plugin::getApiHelper();
            $settingsHelper = Plugin::getSettingsHelper();

            $orderItemsRefunded = new OrderItemsRefunder(
                $refundLineItemsBuilder,
                $dataHelper,
                $apiHelper->getApiClient($settingsHelper->isTestModeEnabled())->orders
            );

            return new MollieOrder($orderItemsRefunded, $data);
        }

        if ((!is_object($data) && $data == 'payment')
            || (!is_object($data) && strpos($data, 'tr_') !== false)
            || (is_object($data) && $data->resource == 'payment')
        ) {
            return new MolliePayment($data);
        }

        return false;
    }
}
