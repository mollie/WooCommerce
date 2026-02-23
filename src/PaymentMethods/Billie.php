<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

/**
 * Class Billie
 *
 * Handles the Billie payment method for WooCommerce.
 */
class Billie extends \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod implements \Mollie\WooCommerce\PaymentMethods\PaymentMethodI
{
    /**
     * Get the configuration for the Billie payment method.
     *
     * @return array
     */
    protected function getConfig(): array
    {
        return ['id' => 'billie', 'defaultTitle' => 'Billie', 'settingsDescription' => 'To accept payments via Billie, all default WooCommerce checkout fields should be enabled and required.', 'defaultDescription' => '', 'paymentFields' => \true, 'instructions' => \false, 'supports' => ['products', 'refunds'], 'filtersOnBuild' => \true, 'confirmationDelayed' => \false, 'paymentCaptureMode' => 'manual', 'errorMessage' => 'Company field is empty. The company field is required.', 'companyPlaceholder' => 'Please enter your company name here.', 'docs' => 'https://www.mollie.com/gb/payments/billie'];
    }
    // Replace translatable strings after the 'after_setup_theme' hook
    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Billie', 'mollie-payments-for-woocommerce');
        $this->config['settingsDescription'] = __('To accept payments via Billie, all default WooCommerce checkout fields should be enabled and required.', 'mollie-payments-for-woocommerce');
        $this->config['errorMessage'] = __('Company field is empty. The company field is required.', 'mollie-payments-for-woocommerce');
        $this->config['companyPlaceholder'] = __('Please enter your company name here.', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = \true;
    }
    /**
     * Add filters and actions for the Billie payment method.
     * This will be added during constructor
     */
    public function filtersOnBuild()
    {
        add_filter('woocommerce_after_checkout_validation', [$this, 'BillieFieldsMandatory'], 11, 2);
        add_action('woocommerce_checkout_posted_data', [$this, 'switchFields'], 11);
    }
    /**
     * Modify the general form fields for the Billie payment method.
     *
     * @param array $generalFormFields
     * @return array
     */
    public function getFormFields(array $generalFormFields): array
    {
        unset($generalFormFields[1]);
        unset($generalFormFields['allowed_countries']);
        return $generalFormFields;
    }
    /**
     * Validate mandatory fields for the Billie payment method.
     *
     * @param array $fields
     * @param $errors
     */
    public function BillieFieldsMandatory(array $fields, $errors)
    {
        $gatewayName = "mollie_wc_gateway_billie";
        $field = 'billing_company_billie';
        $companyLabel = __('Company', 'mollie-payments-for-woocommerce');
        return $this->addPaymentMethodMandatoryFields($fields, $gatewayName, $field, $companyLabel, $errors);
    }
    /**
     * Switch fields for the Billie payment method.
     *
     * @param array $data
     * @return array
     */
    public function switchFields(array $data): array
    {
        if (isset($data['payment_method']) && $data['payment_method'] === 'mollie_wc_gateway_billie') {
            $fieldPosted = filter_input(\INPUT_POST, 'billing_company_billie', \FILTER_SANITIZE_SPECIAL_CHARS) ?? \false;
            if (!empty($fieldPosted) && is_string($fieldPosted)) {
                $data['billing_company'] = $fieldPosted;
            }
        }
        return $data;
    }
    /**
     * Some payment methods require mandatory fields, this function will add them to the checkout fields array
     * @param array $fields
     * @param string $gatewayName
     * @param string $field
     * @param $errors
     * @return mixed
     */
    public function addPaymentMethodMandatoryFields(array $fields, string $gatewayName, string $field, string $fieldLabel, $errors)
    {
        if ($fields['payment_method'] !== $gatewayName) {
            return $fields;
        }
        $fieldPosted = filter_input(\INPUT_POST, $field, \FILTER_SANITIZE_SPECIAL_CHARS) ?? \false;
        if (!empty($fieldPosted) && is_string($fieldPosted)) {
            if (isset($fields['billing_company']) && $fields['billing_company'] === $fieldPosted) {
                return $fields;
            }
            if (isset($fields['shipping_company']) && $fields['shipping_company'] === $fieldPosted) {
                return $fields;
            }
            $fields['billing_company'] = $fieldPosted;
        }
        if (empty($fields['billing_company']) && empty($fields['shipping_company'])) {
            $errors->add('validation', sprintf(
                /* translators: Placeholder 1: field name. */
                __('%s is a required field.', 'woocommerce'),
                "<strong>{$fieldLabel}</strong>"
            ));
        }
        return $fields;
    }
}
