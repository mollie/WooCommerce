<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Przelewy24 implements PaymentMethodI
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
            'id' => 'przelewy24',
            'defaultTitle' => __('Przelewy24', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => __(
                'To accept payments via Przelewy24, a customer email is required for every payment.',
                'mollie-payments-for-woocommerce'
            ),
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => true,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => false
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
