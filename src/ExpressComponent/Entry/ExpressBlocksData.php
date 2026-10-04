<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Entry;

use Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary;
use Mollie\WooCommerce\Components\Rules\MollieJsLocale;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressSettings;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShopFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\WalletVisibility;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
/**
 * Public browser data: never keys, secrets, order keys or amounts. Texts come from PHP (no wp_set_script_translations).
 */
class ExpressBlocksData
{
    /**
     * @return array{restUrl: string, nonce: string, locale: string, buttons: object, messages: array<string, string>}
     */
    public function build(ExpressSettings $settings, ShopFacts $shop): array
    {
        return ['restUrl' => rest_url(RestApi::ROUTE_NAMESPACE . '/'), 'nonce' => wp_create_nonce(\Mollie\WooCommerce\ExpressComponent\Entry\ExpressRoutes::NONCE_ACTION), 'locale' => MollieJsLocale::from(get_locale(), AcceptedLocaleValuesDictionary::ALLOWED_LOCALES_KEYS_MAP, AcceptedLocaleValuesDictionary::DEFAULT_LOCALE_VALUE), 'buttons' => $this->buttons($settings, $shop), 'messages' => ['shippingIncomplete' => \Mollie\WooCommerce\ExpressComponent\Entry\ExpressRoutes::messageFor('shipping_incomplete'), 'unavailable' => \Mollie\WooCommerce\ExpressComponent\Entry\ExpressRoutes::messageFor('mollie_unavailable'), 'placeholder' => __('Express checkout', 'mollie-payments-for-woocommerce'), 'waitingForShipping' => __('Waiting for the shipping cost…', 'mollie-payments-for-woocommerce')]];
    }
    /**
     * Mollie.js shows every wallet it is not told about, so only hidden ones are listed.
     */
    private function buttons(ExpressSettings $settings, ShopFacts $shop): object
    {
        $buttons = [];
        foreach (WalletVisibility::buttons($settings, $shop) as $wallet => $offered) {
            if (!$offered) {
                $buttons[$wallet] = ['visibility' => 'hidden'];
            }
        }
        // Object so an empty list encodes as {}, not [].
        return (object) $buttons;
    }
}
