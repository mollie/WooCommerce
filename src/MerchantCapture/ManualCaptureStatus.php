<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\MerchantCapture;

class ManualCaptureStatus
{
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_WAITING = 'waiting';
}
