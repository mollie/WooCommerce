<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Billie extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'billie',
            'defaultTitle' => __('Billie', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => __(
                'To accept payments via Billie, all default WooCommerce checkout fields should be enabled and required.',
                'mollie-payments-for-woocommerce'
            ),
            'defaultDescription' => '',
            'paymentFields' => true,
            'instructions' => false,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => true,
            'confirmationDelayed' => false,
            'SEPA' => false,
            'orderMandatory' => true,
            'errorMessage' => __(
                'Company field is empty. The company field is required.',
                'mollie-payments-for-woocommerce'
            ),
            'companyPlaceholder' => __('Please enter your company name here.', 'mollie-payments-for-woocommerce'),
            'docs' => 'https://www.mollie.com/gb/payments/billie',
        ];
    }

    public function filtersOnBuild()
    {
        add_filter(
            'woocommerce_after_checkout_validation',
            [$this, 'BillieFieldsMandatory'],
            11,
            2
        );
        add_action(
            'woocommerce_checkout_posted_data',
            [$this, 'switchFields'],
            11
        );
    }

    public function getFormFields($generalFormFields): array
    {
        unset($generalFormFields[1]);
        unset($generalFormFields['allowed_countries']);

        return $generalFormFields;
    }

    public function BillieFieldsMandatory($fields, $errors)
    {
        $gatewayName = "mollie_wc_gateway_billie";
        $field = 'billing_company_billie';
        $companyLabel = __('Company', 'mollie-payments-for-woocommerce');
        return $this->addPaymentMethodMandatoryFields($fields, $gatewayName, $field, $companyLabel, $errors);
    }

    public function switchFields($data)
    {
        if (isset($data['payment_method']) && $data['payment_method'] === 'mollie_wc_gateway_billie') {
            $fieldPosted = filter_input(INPUT_POST, 'billing_company_billie', FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
            if ($fieldPosted) {
                $data['billing_company'] = !empty($fieldPosted) ? $fieldPosted : $data['billing_company'];
            }
        }
        return $data;
    }

    /**
     * Some payment methods require mandatory fields, this function will add them to the checkout fields array
     * @param $fields
     * @param string $gatewayName
     * @param string $field
     * @param $errors
     * @return mixed
     */
    public function addPaymentMethodMandatoryFields($fields, string $gatewayName, string $field, string $fieldLabel, $errors)
    {
        if ($fields['payment_method'] !== $gatewayName) {
            return $fields;
        }
        if (!isset($fields[$field])) {
            $fieldPosted = filter_input(INPUT_POST, $field, FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
            if ($fieldPosted) {
                $fields[$field] = $fieldPosted;
            } else {
                $errors->add(
                    'validation',
                    sprintf(
                    /* translators: Placeholder 1: field name. */
                        __('%s is a required field.', 'woocommerce'),
                        "<strong>$fieldLabel</strong>"
                    )
                );
            }
        }

        return $fields;
    }
}
