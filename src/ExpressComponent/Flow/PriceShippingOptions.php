<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Flow;

use Mollie\WooCommerce\ExpressComponent\Rules\ShippingOptions;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShippingOptionsVerdict;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressShippingSnapshot;
use Mollie\WooCommerce\Log\EventLog;

/**
 * Mollie asks which shipping options the store offers for the address the shopper picked in the wallet.
 * The caller is admitted before this runs; nothing here changes an order.
 */
final class PriceShippingOptions
{
    private const FLOW = 'express.shipping.price';

    public function __construct(private ExpressShippingSnapshot $snapshot, private EventLog $log)
    {
    }

    /**
     * @param array{country: string, state: string, postcode: string, city: string} $address
     */
    public function price(string $secret, array $address): ShippingOptionsVerdict
    {
        $started = microtime(true);
        $this->log->step(self::FLOW . '.started', ['entry' => 'shipping_callback']);

        $found = $this->snapshot->ratesFor($secret, $address);
        $verdict = ShippingOptions::decide($found['rates'], $found['failed']);
        $refusal = $verdict->refusalType();

        // The country code is the only part of the address that is logged.
        $this->log->step('rule.decided', [
            'rule' => 'ShippingOptions',
            'verdict' => $refusal ?? 'offered',
            'inputs' => sprintf('country=%s,rates=%d,failed=%d', $address['country'], count($found['rates']), $found['failed'] ? 1 : 0),
        ]);
        $this->log->step(self::FLOW . '.finished', [
            'result' => $refusal === null ? 'offered' : 'unsupported',
            'ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        return $verdict;
    }
}
