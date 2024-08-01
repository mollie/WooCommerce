<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

class Riverty extends AbstractPaymentMethod implements PaymentMethodI
{
    protected function getConfig(): array
    {
        return [
            'id' => 'riverty',
            'defaultTitle' => __('Riverty', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => __(
                'To accept payments via Riverty, all default WooCommerce checkout fields should be enabled and required.',
                'mollie-payments-for-woocommerce'
            ),
            'defaultDescription' => '',
            'paymentFields' => true,
            'additionalFields' => ['birthdate', 'phone'],
            'instructions' => false,
            'supports' => [
                'products',
                'refunds',
            ],
            'filtersOnBuild' => true,
            'confirmationDelayed' => false,
            'SEPA' => false,
            'orderMandatory' => true,
            'phonePlaceholder' => __('Please enter your phone here. +316xxxxxxxx', 'mollie-payments-for-woocommerce'),
            'birthdatePlaceholder' => __('Please enter your birthdate here.', 'mollie-payments-for-woocommerce'),
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        return $generalFormFields;
    }

    public function filtersOnBuild()
    {
        add_action(
            'woocommerce_checkout_posted_data',
            [$this, 'switchFields'],
            11
        );
        add_action('woocommerce_rest_checkout_process_payment_with_context', [$this, 'addPhoneWhenRest'], 11);
        add_action('woocommerce_rest_checkout_process_payment_with_context', [$this, 'addBirthdateWhenRest'], 11);
        add_action('woocommerce_before_pay_action', [$this, 'fieldsMandatoryPayForOrder'], 11);
    }

    /**
     * @param $order
     */
    public function fieldsMandatoryPayForOrder($order)
    {
        $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS) ?? false;

        if ($paymentMethod !== 'mollie_wc_gateway_riverty') {
            return;
        }

        $phoneValue = filter_input(INPUT_POST, 'billing_phone_riverty', FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
        $phoneValid = $phoneValue || null;

        if ($phoneValid) {
            $order->set_billing_phone($phoneValue);
        }
    }

    public function switchFields($data)
    {
        if (isset($data['payment_method']) && $data['payment_method'] === 'mollie_wc_gateway_riverty') {
            $fieldName = 'billing_phone_' . $this->getConfig()['id'];
            $fieldPosted = filter_input(INPUT_POST, $fieldName, FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
            if (!empty($fieldPosted)) {
                $data['billing_phone'] = $fieldPosted;
            }
        }
        return $data;
    }

    public function addPhoneWhenRest($arrayContext)
    {
        $context = $arrayContext;
        $phoneMandatoryGateways = ['mollie_wc_gateway_riverty'];
        $paymentMethod = $context->payment_data['payment_method'] ?? null;
        if ($paymentMethod && in_array($paymentMethod, $phoneMandatoryGateways)) {
            $billingPhone = $context->order->get_billing_phone();
            if (!empty($billingPhone)) {
                return;
            }

            $billingPhone = $context->payment_data['billing_phone'] ?? null;
            if ($billingPhone) {
                $context->order->set_billing_phone($billingPhone);
                $context->order->save();
            }
        }
    }

    /**
     * @throws RouteException
     */
    public function addBirthdateWhenRest($context)
    {
        $paymentMethod = $context->payment_data['payment_method'] ?? null;
        if ($paymentMethod) {
            $billingBirthdate = $context->payment_data['billing_birthdate'] ?? null;
            if ($billingBirthdate && $this->isBirthValid($billingBirthdate)) {
                $context->order->update_meta_data('billing_birthdate', $billingBirthdate);
                $context->order->save();
            }
        }
    }

    private function isBirthValid($billing_birthdate): bool
    {
        return isMollieBirthValid($billing_birthdate);
    }
}
