<?php

use Mollie\Api\Exceptions\ApiException;

class Mollie_WC_Helper_PaymentFactory
{
    /**
     * @param $data
     * @return bool|Mollie_WC_Payment_Order|Mollie_WC_Payment_Payment
     * @throws ApiException
     */
    public static function getPaymentObject($data)
    {
        if ((!is_object($data) && $data == 'order')
            || (!is_object($data) && strpos($data, 'ord_') !== false)
            || (is_object($data) && $data->resource == 'order')
        ) {
            $dataHelper = Mollie_WC_Plugin::getDataHelper();
            $refundLineItemsBuilder = new Mollie_WC_Payment_RefundLineItemsBuilder($dataHelper);
            $apiHelper = Mollie_WC_Plugin::getApiHelper();
            $settingsHelper = Mollie_WC_Plugin::getSettingsHelper();

            $orderItemsRefunded = new Mollie_WC_Payment_OrderItemsRefunder(
                $refundLineItemsBuilder,
                $dataHelper,
                $apiHelper->getApiClient($settingsHelper->isTestModeEnabled())->orders
            );

            return new Mollie_WC_Payment_Order($orderItemsRefunded, $data);
        }

        if ((!is_object($data) && $data == 'payment')
            || (!is_object($data) && strpos($data, 'tr_') !== false)
            || (is_object($data) && $data->resource == 'payment')
        ) {
            return new Mollie_WC_Payment_Payment($data);
        }

        return false;
    }
}

