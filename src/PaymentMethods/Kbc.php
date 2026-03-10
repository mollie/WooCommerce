<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class Kbc extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    protected const DEFAULT_ISSUERS_DROPDOWN = 'yes';
    protected function getConfig(): array
    {
        return ['id' => 'kbc', 'defaultTitle' => 'KBC/CBC Payment Button', 'settingsDescription' => '', 'defaultDescription' => 'Select your bank', 'paymentFields' => \true, 'instructions' => \false, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \false, 'confirmationDelayed' => \true, 'SEPA' => \true, 'docs' => 'https://www.mollie.com/gb/payments/kbc-cbc'];
    }
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('KBC/CBC Payment Button', 'mollie-payments-for-woocommerce');
        $this->config['defaultDescription'] = __('Select your bank', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    public function getFormFields($generalFormFields): array
    {
        $searchKey = 'advanced';
        $keys = array_keys($generalFormFields);
        $index = array_search($searchKey, $keys);
        $before = array_slice($generalFormFields, 0, $index + 1, \true);
        $after = array_slice($generalFormFields, $index + 1, null, \true);
        $paymentMethodFormFieds = ['issuers_dropdown_shown' => ['title' => __('Show KBC/CBC banks dropdown', 'mollie-payments-for-woocommerce'), 'type' => 'checkbox', 'description' => sprintf(__('If you disable this, a dropdown with various KBC/CBC banks will not be shown in the WooCommerce checkout, so users will select a KBC/CBC bank on the Mollie payment page after checkout.', 'mollie-payments-for-woocommerce'), $this->getConfig()['defaultTitle']), 'default' => self::DEFAULT_ISSUERS_DROPDOWN], 'issuers_empty_option' => ['title' => __('Issuers empty option', 'mollie-payments-for-woocommerce'), 'type' => 'text', 'description' => sprintf(__("This text will be displayed as the first option in the KBC/CBC issuers drop down, if nothing is entered, 'Select your bank' will be shown. Only if the above 'Show KBC/CBC banks dropdown' is enabled.", 'mollie-payments-for-woocommerce'), $this->getConfig()['defaultTitle']), 'default' => __('Select your bank', 'mollie-payments-for-woocommerce')]];
        $before = array_merge($before, $paymentMethodFormFieds);
        $formFields = array_merge($before, $after);
        return $formFields;
    }
}
