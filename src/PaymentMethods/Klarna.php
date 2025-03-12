<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Klarna extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'klarna',
            'defaultTitle' => 'Pay with Klarna',
            'settingsDescription' => 'To accept payments via Klarna, all default WooCommerce checkout fields should be enabled and required.',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => true,
            'confirmationDelayed' => false,
            'SEPA' => false,
            //'orderMandatory' => true,
            'docs' => 'https://www.mollie.com/gb/payments/klarna',
        ];
    }

    public function filtersOnBuild()
    {
        add_filter('woocommerce_mollie_wc_gateway_klarna_args', function (array $paymentData): array {
            if (!isset($paymentData['orderNumber']) && !isset($paymentData['captureMode'])) {
                $paymentData['captureMode'] = 'manual';
            }
            return $paymentData;
        });
    }

    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Pay with Klarna', 'mollie-payments-for-woocommerce');
        $this->config['settingsDescription'] = __(
            'To accept payments via Klarna, all default WooCommerce checkout fields should be enabled and required.',
            'mollie-payments-for-woocommerce'
        );
        $this->translationsInitialized = true;
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }
}
