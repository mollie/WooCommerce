<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture\UI;

use Mollie\WooCommerce\MerchantCapture\ManualCaptureStatus;
use Mollie\WooCommerce\Shared\SharedDataDictionary;

class StatusRenderer
{
    public function __invoke(string $molliePaymentStatus)
    {
        if ($molliePaymentStatus === ManualCaptureStatus::STATUS_AUTHORIZED) {
            (new StatusButton())(
                __('Payment authorized', 'mollie-payments-for-woocommerce'),
                SharedDataDictionary::STATUS_ON_HOLD
            );
        } elseif ($molliePaymentStatus === ManualCaptureStatus::STATUS_VOIDED) {
            (new StatusButton())(
                __('Payment voided', 'mollie-payments-for-woocommerce'),
                SharedDataDictionary::STATUS_CANCELLED
            );
        } elseif ($molliePaymentStatus === ManualCaptureStatus::STATUS_CAPTURED) {
            (new StatusButton())(
                __('Payment captured', 'mollie-payments-for-woocommerce'),
                SharedDataDictionary::STATUS_COMPLETED
            );
        } elseif ($molliePaymentStatus === ManualCaptureStatus::STATUS_WAITING) {
            (new StatusButton())(
                __('Payment waiting', 'mollie-payments-for-woocommerce'),
                SharedDataDictionary::STATUS_PENDING
            );
        }
    }
}
