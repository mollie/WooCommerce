<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Entry;

use Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary;
use Mollie\WooCommerce\Components\Rules\MollieJsLocale;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\WalletVisibility;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;

/**
 * What the blocks script receives on the block checkout and the block cart.
 */
class ExpressBlocksData
{
    /**
     * @param string $surface The page the data is printed on; the browser sends it back to start a session.
     * @return array{restUrl: string, nonce: string, surface: string, locale: string, buttons: object, messages: array<string, string>}
     */
    public function build(ExpressSettings $settings, ShopFacts $shop, string $surface): array
    {
        return [
            'restUrl' => rest_url(RestApi::ROUTE_NAMESPACE . '/'),
            'nonce' => wp_create_nonce(ExpressRoutes::NONCE_ACTION),
            'surface' => $surface,
            'locale' => MollieJsLocale::from(
                get_locale(),
                AcceptedLocaleValuesDictionary::ALLOWED_LOCALES_KEYS_MAP,
                AcceptedLocaleValuesDictionary::DEFAULT_LOCALE_VALUE
            ),
            'buttons' => $this->buttons($settings, $shop, $surface),
            'messages' => [
                'unavailable' => ExpressRoutes::messageFor('mollie_unavailable'),
                // WordPress refuses the request before the route runs when the login changed in another tab.
                'reload' => ExpressRoutes::messageFor('mollie_express_forbidden'),
                'placeholder' => __('Express checkout', 'mollie-payments-for-woocommerce'),
            ],
        ];
    }

    /**
     * Mollie.js shows every wallet it is not told about, so only hidden ones are listed.
     */
    private function buttons(ExpressSettings $settings, ShopFacts $shop, string $surface): object
    {
        $buttons = [];
        foreach (WalletVisibility::buttons($settings, $shop, $surface) as $wallet => $offered) {
            if (!$offered) {
                $buttons[$wallet] = ['visibility' => 'hidden'];
            }
        }

        // Object so an empty list encodes as {}, not [].
        return (object) $buttons;
    }
}
