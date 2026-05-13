<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Activation\Migrations;

/**
 * Rewrites every stored `mollie_wc_gateway_*_settings` row to match the
 * simplified title/logo model introduced in 8.1.7.
 *
 * Lore — why this exists:
 *
 * Pre-8.1.7, three independent flags governed how a payment method's title and
 * logo were displayed at checkout: `use_api_title`, `enable_custom_logo` and
 * `display_logo`. Their precedence was ambiguous (e.g. `use_api_title=yes`
 * together with a non-empty stored `title`, or `enable_custom_logo=no` with a
 * stored `iconFileUrl` left behind from a previous upload) and merchants
 * routinely ended up with missing or stale logos — iDEAL and Wero were the
 * most frequent offenders in support tickets.
 *
 * The new model collapses this to "empty field = Mollie API default, filled
 * field = merchant override". `use_api_title` and `enable_custom_logo` are
 * gone; only `title`, `upload_logo` (driving `iconFileUrl`/`iconFilePath`) and
 * `display_logo` remain. This migrator translates whatever combination a site
 * has on disk into the new shape so that runtime code — which no longer reads
 * the removed keys — sees a consistent state on the first request after upgrade.
 *
 * Discovery uses a direct `wp_options` LIKE query rather than iterating
 * registered gateways: it picks up settings for gateways that aren't currently
 * enabled (or whose classes have since been renamed), and it doesn't need the
 * WooCommerce gateway bootstrap to have run. The body operates on option
 * arrays via `get_option`/`update_option` for the same reason.
 *
 * Idempotency: the version gate in `ActivationModule::runMigrations()` is the
 * primary defence against re-runs, but each branch below is also a no-op when
 * the relevant key is already absent, so a manual replay (or a downgrade-then-
 * upgrade cycle) produces no further writes. `display_logo` is never touched.
 */
class PaymentMethodSettingsMigrator implements MigratorInterface
{
    public function targetVersion(): string
    {
        return '8.1.7';
    }

    public function migrate(): void
    {
        global $wpdb;
        // Pull every gateway settings row in one query — including ones for
        // gateways that aren't currently registered (deactivated extensions,
        // renamed classes) so no merchant ends up with a half-migrated DB.
        $optionNames = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE 'mollie_wc_gateway_%_settings'"
        );
        foreach ($optionNames as $optionName) {
            $settings = get_option($optionName, false);
            if (!is_array($settings)) {
                // Row exists but stores a non-array (corrupted or never
                // written by us). Skip — overwriting would mask the problem
                // and migrating an unknown shape is unsafe.
                continue;
            }
            $settings = $this->migrateTitle($settings);
            $settings = $this->migrateLogo($settings);
            update_option($optionName, $settings);
        }
    }

    /**
     * Drop `use_api_title` and decide whether to keep the stored `title`.
     *
     * Truth table (old → new):
     *   use_api_title=yes, any title       → title cleared       (merchant explicitly opted into API title)
     *   use_api_title=no,  non-empty title → title preserved     (merchant has a real custom override)
     *   use_api_title=no,  empty title     → title cleared       (toggle was off but nothing was filled in)
     *   key absent,        any title       → title cleared       (legacy default was "use API title", so any
     *                                                              leftover title was never actually displayed)
     *
     * In the new model the runtime treats an empty `title` as "use API title",
     * so clearing is the correct outcome whenever the merchant wasn't actively
     * relying on a stored override.
     */
    private function migrateTitle(array $settings): array
    {
        $useApiTitle = $settings['use_api_title'] ?? 'yes';
        if ($useApiTitle !== 'no' || empty($settings['title'])) {
            unset($settings['title']);
        }
        unset($settings['use_api_title']);
        return $settings;
    }

    /**
     * Drop `enable_custom_logo` and decide whether to keep the stored
     * `iconFileUrl` / `iconFilePath` pair.
     *
     * Truth table (old → new):
     *   enable_custom_logo=yes + non-empty iconFileUrl → preserve url+path  (a real custom logo was active)
     *   enable_custom_logo=yes + empty   iconFileUrl   → clear url+path    (toggle on but nothing uploaded;
     *                                                                       runtime would have shown API logo
     *                                                                       anyway)
     *   enable_custom_logo=no,  any url                → clear url+path    (toggle was off; any stored path
     *                                                                       was orphaned from a previous upload)
     *   key absent,             any url                → clear url+path    (legacy default was disabled)
     *
     * Clearing both `iconFileUrl` and `iconFilePath` puts the row into the new
     * "empty upload field = use API logo" state, matching what merchants
     * actually saw at checkout under the old flags.
     */
    private function migrateLogo(array $settings): array
    {
        $enableCustomLogo = $settings['enable_custom_logo'] ?? 'no';
        $hasUrl = !empty($settings['iconFileUrl']) && is_string($settings['iconFileUrl']);
        if ($enableCustomLogo !== 'yes' || !$hasUrl) {
            unset($settings['iconFileUrl'], $settings['iconFilePath']);
        }
        unset($settings['enable_custom_logo']);
        return $settings;
    }
}
