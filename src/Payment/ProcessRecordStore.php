<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment;

use Mollie\WooCommerce\Payment\Rules\Values\ProcessRecord;
use Throwable;
use WC_Order;
/**
 * write() does not save: the caller saves with the order change, inside OrderLock::withFreshOrder().
 */
final class ProcessRecordStore
{
    public const META_KEY = '_mollie_process';
    public function read(WC_Order $order): ProcessRecord
    {
        try {
            $stored = $order->get_meta(self::META_KEY);
            return is_array($stored) ? ProcessRecord::fromArray($stored) : ProcessRecord::empty();
        } catch (Throwable $unreadable) {
            return ProcessRecord::empty();
        }
    }
    public function write(WC_Order $order, ProcessRecord $record): void
    {
        $order->update_meta_data(self::META_KEY, $record->toArray());
    }
}
