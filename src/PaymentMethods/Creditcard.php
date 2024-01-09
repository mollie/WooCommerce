<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use Mollie\WooCommerce\Activation\ActivationModule;
use Mollie\WooCommerce\Shared\SharedDataDictionary;

class Creditcard extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'creditcard',
            'defaultTitle' => __('Credit card', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => $this->hasPaymentFields(),
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
                'subscriptions',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => false,
            'Subscription' => true,
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        $componentFields = $this->includeMollieComponentsFields($generalFormFields);
        return $this->includeCreditCardIconSelector($componentFields);
    }

    public function hasPaymentFields(): bool
    {
        $optionName = 'mollie_wc_gateway_creditcard_settings';
        $settings = get_option($optionName, false);
        $componentsEnabled = $settings['mollie_components_enabled'] ?? false;
        return $componentsEnabled ? $componentsEnabled === 'yes' : $this->defaultComponentsEnabled() === 'yes';
    }

    protected function includeMollieComponentsFields($generalFormFields)
    {
        $fields = [
            'mollie_components_enabled' => [
                'type' => 'checkbox',
                'title' => __('Enable Mollie Components', 'mollie-payments-for-woocommerce'),
                /* translators: Placeholder 1: Mollie Components.*/
                'description' => sprintf(
                    __(
                        'Use the Mollie Components for this Gateway. Read more about <a href=\'https://www.mollie.com/en/news/post/better-checkout-flows-with-mollie-components?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner\'>%s</a> and how it improves your conversion.',
                        'mollie-payments-for-woocommerce'
                    ),
                    __('Mollie Components', 'mollie-payments-for-woocommerce')
                ),
                'default' => $this->defaultComponentsEnabled(),
            ],
        ];

        return array_merge($generalFormFields, $fields);
    }

    protected function defaultComponentsEnabled()
    {
        $isNewInstall = get_option(SharedDataDictionary::NEW_INSTALL_PARAM_NAME, false);
        if ($isNewInstall === 'yes') {
            return 'yes';
        }
        return 'no';
    }

    /**
     * Include the credit card icon selector customization in the credit card
     * settings page
     */
    protected function includeCreditCardIconSelector($componentFields)
    {
        $fields = $this->creditcardIconsSelectorFields();
        $fields && ($componentFields = array_merge($componentFields, $fields));
        return $componentFields;
    }

    private function creditcardIconsSelectorFields(): array
    {
        return [
            [
                'title' => __('Customize Icons', 'mollie-payments-for-woocommerce'),
                'type' => 'title',
                'desc' => '',
                'id' => 'customize_icons',
            ],
            'mollie_creditcard_icons_enabler' => [
                'type' => 'checkbox',
                'title' => __('Enable Icons Selector', 'mollie-payments-for-woocommerce'),
                'description' => __(
                    'Show customized creditcard icons on checkout page',
                    'mollie-payments-for-woocommerce'
                ),
                'checkboxgroup' => 'start',
                'default' => 'no',
            ],
            'mollie_creditcard_icons_amex' => [
                'label' => __('Show American Express Icon', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'mollie_creditcard_icons_cartasi' => [
                'label' => __('Show Carta Si Icon', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'mollie_creditcard_icons_cartebancaire' => [
                'label' => __('Show Carte Bancaire Icon', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'mollie_creditcard_icons_maestro' => [
                'label' => __('Show Maestro Icon', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'mollie_creditcard_icons_mastercard' => [
                'label' => __('Show Mastercard Icon', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'mollie_creditcard_icons_visa' => [
                'label' => __('Show Visa Icon', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'mollie_creditcard_icons_vpay' => [
                'label' => __('Show VPay Icon', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'checkboxgroup' => 'end',
                'default' => 'no',
            ],
        ];
    }
}
