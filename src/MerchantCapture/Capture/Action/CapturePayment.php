<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture\Capture\Action;

use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\MerchantCapture\ManualCaptureStatus;
use Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule;

class CapturePayment extends AbstractPaymentCaptureAction
{
    public function __invoke()
    {
        try {
            $payment = $this->order->get_payment_method();
            if (strpos($payment, 'mollie') === false) {
                return;
            }

            $paymentId = $this->order->get_meta('_mollie_payment_id');

            if (!$paymentId) {
                $this->logger->error('Missing Mollie payment ID in order ' . $this->order->get_id());
                $this->order->add_order_note(
                    __(
                        'The Mollie payment ID is missing, and we are unable to capture the funds.',
                        'mollie-payments-for-woocommerce'
                    )
                );
                return;
            }

            $paymentCapturesApi = $this->apiHelper->getApiClient($this->apiKey)->paymentCaptures;
            $captureData = [
                'amount' => [
                    'currency' => $this->order->get_currency(),
                    'value' => $this->order->get_total(),
                ],
            ];
            $this->logger->debug(
                'SEND AN ORDER CAPTURE, orderId: ' . $this->order->get_id(
                ) . ' transactionId: ' . $paymentId . 'Capture data: ' . json_encode($captureData)
            );
            $paymentCapturesApi->createForId($paymentId, $captureData);
            $this->order->update_meta_data(
                MerchantCaptureModule::ORDER_PAYMENT_STATUS_META_KEY,
                ManualCaptureStatus::STATUS_WAITING
            );
            $this->order->add_order_note(
                sprintf(
                    __(
                        'The payment capture of %s has been sent successfully, and we are currently awaiting confirmation.',
                        'mollie-payments-for-woocommerce'
                    ),
                    wc_price($this->order->get_total())
                )
            );
            $this->order->save();
        } catch (ApiException $exception) {
            $this->logger->error($exception->getMessage());
            $this->order->add_order_note(
                __(
                    'Payment Capture Failed. We encountered an issue while processing the payment capture.',
                    'mollie-payments-for-woocommerce'
                )
            );
        }
    }
}
