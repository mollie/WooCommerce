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

    public function __construct(
        private ExpressFactsBuilder $facts,
        private ExpressBlocksData $blocksData
    ) {
    }

    /**
     * @param callable(string): bool $owns Whether Express owns the surface, decided once for the request.
     *        Asked only on the block checkout and the block cart: it loads and totals the cart.
     */
    public function enqueue(callable $owns): void
    {
        $surface = $this->pageSurface();
        if ($surface === null || !$owns($surface)) {
            return;
        }
        $settings = $this->facts->settings();
        $shop = $this->facts->shopFacts();

        // Null version: a ?ver= would break the exact ?compatible URL.
        wp_register_script(self::HANDLE, self::SRC, [], null, true);
        wp_localize_script(self::HANDLE, self::DATA, $this->blocksData->build($settings, $shop, $surface));

        $blockScript = wp_scripts()->query(self::BLOCK_SCRIPT, 'registered');
        if ($blockScript instanceof \_WP_Dependency) {
            if (!in_array(self::HANDLE, $blockScript->deps, true)) {
                $blockScript->deps[] = self::HANDLE;
            }

            return;
        }
        wp_enqueue_script(self::HANDLE);
    }

    /**
     * The surface of the page now rendered; null where Express never shows.
     */
    private function pageSurface(): ?string
    {
        if (is_checkout() && !is_wc_endpoint_url() && has_block('woocommerce/checkout')) {
            return 'checkout';
        }

        return $this->isBlockCart() ? 'cart' : null;
    }

    /**
     * The classic cart is not a surface: its legacy buttons stay.
     */
    public function isBlockCart(): bool
    {
        return is_cart() && has_block('woocommerce/cart');
    }
}
