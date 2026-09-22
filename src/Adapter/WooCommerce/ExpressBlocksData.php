<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WooCommerce;

use Mollie\WooCommerce\Adapter\WordPress\ExpressRoutes;
use Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary;
use Mollie\WooCommerce\Core\Express\WalletVisibility;
use Mollie\WooCommerce\Core\Payment\MollieJsLocale;
use Mollie\WooCommerce\Core\Types\ExpressSettings;
use Mollie\WooCommerce\Core\Types\ShopFacts;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;

/**
 * What the block checkout's Express Component is told, as mollieExpressData (REQ-G1).
 *
 * An allowlist: where the two store routes live, the nonce they admit, the locale for Mollie.js,
 * the wallets to hide, and the texts the component shows before it asks the store anything. No API
 * key, profile id, webhook secret, order key, amount or line item — the store prices everything
 * itself when asked. The texts come from PHP because the project does not use
 * wp_set_script_translations.
 */
class ExpressBlocksData
{
    /**
     * @return array{restUrl: string, nonce: string, locale: string, buttons: object, messages: array<string, string>}
     */
    public function build(ExpressSettings $settings, ShopFacts $shop): array
    {
        return [
            'restUrl' => rest_url(RestApi::ROUTE_NAMESPACE . '/'),
            'nonce' => wp_create_nonce(ExpressRoutes::NONCE_ACTION),
            'locale' => MollieJsLocale::from(
                get_locale(),
                AcceptedLocaleValuesDictionary::ALLOWED_LOCALES_KEYS_MAP,
                AcceptedLocaleValuesDictionary::DEFAULT_LOCALE_VALUE
            ),
            'buttons' => $this->buttons($settings, $shop),
            'messages' => [
                'shippingIncomplete' => ExpressRoutes::messageFor('shipping_incomplete'),
                'unavailable' => ExpressRoutes::messageFor('mollie_unavailable'),
                'placeholder' => __('Express checkout', 'mollie-payments-for-woocommerce'),
            ],
        ];
    }

    /**
     * Mollie.js shows a wallet it is not told about, so only the wallets not offered are named, as
     * hidden. The buttons do not depend on the cart: a cart that must wait for the checkout form is
     * held back as a whole, by the component's blocked state.
     */
    private function buttons(ExpressSettings $settings, ShopFacts $shop): object
    {
        $buttons = [];
        foreach (WalletVisibility::buttons($settings, $shop) as $wallet => $offered) {
            if (!$offered) {
                $buttons[$wallet] = ['visibility' => 'hidden'];
            }
        }

        // An object even when empty, so the browser gets {} rather than [].
        return (object) $buttons;
    }
}
