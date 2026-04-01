<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Klarna extends AbstractPaymentMethod implements PaymentMethodI
{
    /**
     * @return array<mixed>
     */
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
            'confirmationDelayed' => false,
            'paymentCaptureMode' => 'manual',
            'docs' => 'https://www.mollie.com/gb/payments/klarna',
        ];
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

    /**
     * @param array<mixed> $generalFormFields
     * @return array<mixed>
     */
    public function getFormFields(array $generalFormFields): array
    {
        /**
         * This payment method requires line items to be sent
         *
         * @see https://docs.mollie.com/reference/create-payment
         * @see https://docs.mollie.com/reference/create-order
         */
        if (isset($generalFormFields['hide_order_lines'])) {
            unset($generalFormFields['hide_order_lines']);
        }

        return $generalFormFields;
    }
}
