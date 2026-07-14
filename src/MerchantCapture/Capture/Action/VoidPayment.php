<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\MerchantCapture\Capture\Action;

use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\MerchantCapture\ManualCaptureStatus;
use Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule;
class VoidPayment extends \Mollie\WooCommerce\MerchantCapture\Capture\Action\AbstractPaymentCaptureAction
{
    public function __invoke()
    {
        $paymentId = $this->order->get_meta('_mollie_payment_id');
        $paymentCapturesApi = $this->apiHelper->getApiClient($this->apiKey)->payments;
        try {
            $paymentCapturesApi->cancel($paymentId);
            $this->order->update_meta_data(MerchantCaptureModule::ORDER_PAYMENT_STATUS_META_KEY, ManualCaptureStatus::STATUS_VOIDED);
            $this->order->save();
        } catch (ApiException $exception) {
            $this->logger->error($exception->getMessage());
            $this->order->add_order_note(__('Payment cancelation failed. We encountered an issue while canceling the pre-authorized payment.', 'mollie-payments-for-woocommerce'));
        }
    }
}
