<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Entry;

use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressFactsBuilder;

/**
 * ?compatible makes Mollie.js v2 use window.Mollie2, so the checkout's v1 keeps window.Mollie.
 */
class ExpressAssets
{
    public const HANDLE = 'mollie-v2';

    public const SRC = 'https://js.mollie.com/v2/mollie.js?compatible';

    public const DATA = 'mollieExpressData';

    private const BLOCK_SCRIPT = 'mollie_block_index';

    public const SURFACE = 'checkout';

    public function __construct(
        private ExpressFactsBuilder $facts,
        private ExpressBlocksData $blocksData
    ) {
    }

    /**
     * @param callable(): bool $owns Whether Express owns the checkout, decided once for the request.
     *        Asked only on the block checkout: it loads and totals the cart.
     */
    public function enqueue(callable $owns): void
    {
        if (!$this->isBlockCheckout() || !$owns()) {
            return;
        }
        $settings = $this->facts->settings();
        $shop = $this->facts->shopFacts();

        // Null version: a ?ver= would break the exact ?compatible URL.
        wp_register_script(self::HANDLE, self::SRC, [], null, true);
        wp_localize_script(self::HANDLE, self::DATA, $this->blocksData->build($settings, $shop));

        $blockScript = wp_scripts()->query(self::BLOCK_SCRIPT, 'registered');
        if ($blockScript instanceof \_WP_Dependency) {
            if (!in_array(self::HANDLE, $blockScript->deps, true)) {
                $blockScript->deps[] = self::HANDLE;
            }

            return;
        }
        wp_enqueue_script(self::HANDLE);
    }

    private function isBlockCheckout(): bool
    {
        return is_checkout()
            && !is_wc_endpoint_url()
            && has_block('woocommerce/checkout');
    }
}
