<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Entry;

use Mollie\WooCommerce\ExpressComponent\Rules\SurfaceOwnership;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressFactsBuilder;
/**
 * Loads Mollie.js v2 with ?compatible so the checkout's v1 cannot overwrite it; the component uses window.Mollie2.
 */
class ExpressAssets
{
    public const HANDLE = 'mollie-v2';
    public const SRC = 'https://js.mollie.com/v2/mollie.js?compatible';
    public const DATA = 'mollieExpressData';
    private const BLOCK_SCRIPT = 'mollie_block_index';
    private const SURFACE = 'checkout';
    public function __construct(private ExpressFactsBuilder $facts, private \Mollie\WooCommerce\ExpressComponent\Entry\ExpressBlocksData $blocksData)
    {
    }
    public function enqueue(): void
    {
        if (!$this->isBlockCheckout()) {
            return;
        }
        $settings = $this->facts->settings();
        $shop = $this->facts->shopFacts();
        if (!SurfaceOwnership::owns($settings, $shop, self::SURFACE)) {
            return;
        }
        // Null version: a ?ver= would break the exact ?compatible URL.
        wp_register_script(self::HANDLE, self::SRC, [], null, \true);
        wp_localize_script(self::HANDLE, self::DATA, $this->blocksData->build($settings, $shop));
        $blockScript = wp_scripts()->query(self::BLOCK_SCRIPT, 'registered');
        if ($blockScript instanceof \_WP_Dependency) {
            if (!in_array(self::HANDLE, $blockScript->deps, \true)) {
                $blockScript->deps[] = self::HANDLE;
            }
            return;
        }
        wp_enqueue_script(self::HANDLE);
    }
    private function isBlockCheckout(): bool
    {
        return is_checkout() && !is_wc_endpoint_url() && has_block('woocommerce/checkout');
    }
}
