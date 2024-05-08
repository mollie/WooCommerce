<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Custom extends AbstractPaymentMethod implements PaymentMethodI
{
    protected const DEFAULT_ID = 'custom';

    protected const DEFAULT_TITLE = 'Custom Payment Gateway';
    protected const DEFAULT_SETTINGS_DESCRIPTION = 'This gateway will not show on the checkout page. It is intended for use with a third party plugin.';
    protected const DEFAULT_DESCRIPTION = '';
    protected const DEFAULT_PAYMENT_FIELDS = false;
    protected const DEFAULT_INSTRUCTIONS = true;
    protected const DEFAULT_SUPPORTS = [
        'products',
        'refunds',
    ];
    protected const DEFAULT_FILTERS_ON_BUILD = false;
    protected const DEFAULT_CONFIRMATION_DELAYED = false;
    protected const DEFAULT_SEPA = false;

    public function getConfig(): array
    {
        return [
            'id' => $this->getId(),
            'defaultTitle' => $this->getTitle(),
            'settingsDescription' => $this->getSettingsDescription(),
            'defaultDescription' => $this->getDescription(),
            'paymentFields' => $this->getPaymentFields(),
            'instructions' => $this->getInstructions(),
            'supports' => $this->getSupports(),
            'filtersOnBuild' => $this->getFiltersOnBuild(),
            'confirmationDelayed' => $this->getConfirmationDelayed(),
            'SEPA' => $this->getSEPA(),
        ];
    }

    protected function getFormFields($generalFormFields): array
    {
        return [
            'enabled' => $generalFormFields['enabled'],
        ];
    }

    protected function getId(): string
    {
        return apply_filters('mollie_wc_custom_gateway_id', self::DEFAULT_ID);
    }

    protected function getTitle(): string
    {
        return apply_filters('mollie_wc_custom_gateway_title', self::DEFAULT_TITLE);
    }

    protected function getSettingsDescription(): string
    {
        return apply_filters('mollie_wc_custom_gateway_settings_description', self::DEFAULT_SETTINGS_DESCRIPTION);
    }

    protected function getDescription(): string
    {
        return apply_filters('mollie_wc_custom_gateway_description', self::DEFAULT_DESCRIPTION);
    }

    protected function getPaymentFields(): bool
    {
        return apply_filters('mollie_wc_custom_gateway_payment_fields', self::DEFAULT_PAYMENT_FIELDS);
    }

    protected function getInstructions(): bool
    {
        return apply_filters('mollie_wc_custom_gateway_instructions', self::DEFAULT_INSTRUCTIONS);
    }

    protected function getSupports(): array
    {
        return apply_filters('mollie_wc_custom_gateway_supports', self::DEFAULT_SUPPORTS);
    }

    protected function getFiltersOnBuild(): bool
    {
        return apply_filters('mollie_wc_custom_gateway_filters_on_build', self::DEFAULT_FILTERS_ON_BUILD);
    }

    protected function getConfirmationDelayed(): bool
    {
        return apply_filters('mollie_wc_custom_gateway_confirmation_delayed', self::DEFAULT_CONFIRMATION_DELAYED);
    }

    protected function getSEPA(): bool
    {
        return apply_filters('mollie_wc_custom_gateway_sepa', self::DEFAULT_SEPA);
    }
}
