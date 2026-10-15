<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

/**
 * The Express Component in the block checkout's payment step. Not a Mollie method: the Express
 * module adds it to the gateways, decides where it is offered and refuses a checkout placed with it.
 * Its own script registers it in Blocks; the wallet buttons inside it pay.
 */
class Expresscheckout extends AbstractPaymentMethod implements PaymentMethodI
{
    public function getConfig(): array
    {
        return [
            'id' => Constants::EXPRESSCHECKOUT,
            'defaultTitle' => 'Express checkout',
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'docs' => '',
        ];
    }

    // Replace translatable strings after the 'after_setup_theme' hook
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Express checkout', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = true;
    }

    public function getFormFields($generalFormFields): array
    {
        // Never shown: the wallets' settings turn it on. Without a default, WooCommerce reads a
        // gateway with no saved settings as disabled.
        return [
            'enabled' => array_merge($generalFormFields['enabled'], [
                'default' => 'yes',
            ]),
        ];
    }
}
