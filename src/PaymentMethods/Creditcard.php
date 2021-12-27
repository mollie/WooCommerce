<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Creditcard extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'creditcard',
            'defaultTitle' => __('Credit card', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => __('', 'mollie-payments-for-woocommerce'),
            'paymentFields' => true,
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
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

    protected function includeMollieComponentsFields($generalFormFields)
    {
        $fields = [
            'mollie_components_enabled' => [
                'type' => 'checkbox',
                'title' => __('Enable Mollie Components', 'mollie-payments-for-woocommerce'),
                'description' => sprintf(
                    __(
                        'Use the Mollie Components for this Gateway. Read more about <a href="https://www.mollie.com/en/news/post/better-checkout-flows-with-mollie-components">%s</a> and how it improves your conversion.',
                        'mollie-payments-for-woocommerce'
                    ),
                    __('Mollie Components', 'mollie-payments-for-woocommerce')
                ),
                'default' => 'no',
            ],
        ];

        return array_merge($generalFormFields, $fields);
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
