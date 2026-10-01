<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WordPress;

use Mollie\WooCommerce\Adapter\WooCommerce\ExpressBlocksData;
use Mollie\WooCommerce\Core\Express\SurfaceOwnership;

/**
 * Puts Mollie.js v2 and mollieExpressData on a block checkout that Express owns, and nowhere else.
 *
 * The v2 bundle publishes itself as window.Mollie unless window.Mollie is already a function or a
 * mollie.js script tag carries a 'compatible' query parameter; v1, a runtime dependency of the block
 * checkout, would then overwrite it without an error anywhere. So the handle is registered for
 * exactly the ?compatible URL, with no version that would change it, and the component only ever
 * uses window.Mollie2. v1 keeps its own registration (AssetsModule), untouched.
 *
 * v2 becomes a runtime dependency of the block script, so it is on the page before the component
 * renders. Platform reuse: this is wp_register_script / wp_localize_script and WooCommerce's own
 * page conditionals; nothing here replaces them.
 */
class ExpressAssets
{
    public const HANDLE = 'mollie-v2';

    public const SRC = 'https://js.mollie.com/v2/mollie.js?compatible';

    public const DATA = 'mollieExpressData';

    private const BLOCK_SCRIPT = 'mollie_block_index';

    private const SURFACE = 'checkout';

    public function __construct(
        private ExpressFactsBuilder $facts,
        private ExpressBlocksData $blocksData
    ) {
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

        // A null version: WordPress would otherwise append ?ver=… to the one URL that must stay exact.
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

    /**
     * The checkout page being viewed holds the checkout block: not the classic shortcode checkout,
     * and not its order-pay or order-received endpoints.
     */
    private function isBlockCheckout(): bool
    {
        return is_checkout()
            && !is_wc_endpoint_url()
            && has_block('woocommerce/checkout');
    }
}
