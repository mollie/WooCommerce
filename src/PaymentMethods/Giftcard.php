<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Giftcard implements PaymentMethodI
{
    use CommonPaymentMethodTrait;

    /**
     * @var string[]
     */
    private $config = [];
    /**
     * @var array[]
     */
    private $settings = [];
    /**
     * Ideal constructor.
     */
    public function __construct(PaymentMethodSettingsHandlerI $paymentMethodSettingsHandler)
    {
        $this->config = $this->getConfig();
        $this->settings = $paymentMethodSettingsHandler->getSettings($this);
    }

    private function getConfig(): array
    {
        return [
            'id' => 'giftcard',
            'defaultTitle' => __('Gift cards', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => __('Select your gift card', 'mollie-payments-for-woocommerce'),
            'paymentFields' => true,
            'instructions' => false,
            'supports' => [
                'products'
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => false
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        $paymentMethodFormFieds =  [
            'issuers_dropdown_shown' => [
                'title' => __(
                    'Show gift cards dropdown',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'checkbox',
                'description' => sprintf(
                    __(
                        'If you disable this, a dropdown with various gift cards will not be shown in the WooCommerce checkout, so users will select a gift card on the Mollie payment page after checkout.',
                        'mollie-payments-for-woocommerce'
                    ),
                    $this->getProperty('defaultTitle')
                ),
                'default' => 'yes',
            ],
            'issuers_empty_option' => [
                'title' => __(
                    'Issuers empty option',
                    'mollie-payments-for-woocommerce'
                ),
                'type' => 'text',
                'description' => sprintf(
                    __(
                        "This text will be displayed as the first option in the gift card dropdown, but only if the above 'Show gift cards dropdown' is enabled.",
                        'mollie-payments-for-woocommerce'
                    ),
                    $this->getProperty('defaultTitle')
                ),
                'default' => '',
            ],
        ];
        return array_merge($generalFormFields, $paymentMethodFormFieds);
    }
}
