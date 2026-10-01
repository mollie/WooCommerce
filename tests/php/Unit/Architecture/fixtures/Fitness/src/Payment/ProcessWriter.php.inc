<?php

namespace Fixture\Payment;

final class ProcessWriter
{
    public function write($order, array $record)
    {
        $order->update_meta_data('_mollie_process', $record);
    }
}
