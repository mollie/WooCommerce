<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

class Voucher extends AbstractPaymentMethod implements PaymentMethodI
{
    /**
     * @var string
     */
    public const MEAL = 'meal';
    /**
     * @var string
     */
    public const ECO = 'eco';
    /**
     * @var string
     */
    public const GIFT = 'gift';
    /**
     * @var string
     */
    public const NO_CATEGORY = 'no_category';
    /**
     * @var string
     */
    public const MOLLIE_VOUCHER_CATEGORY_OPTION = '_mollie_voucher_category';

    protected function getConfig(): array
    {
        return [
            'id' => 'voucher',
            'defaultTitle' => __('Voucher', 'mollie-payments-for-woocommerce'),
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'SEPA' => false,
            'orderMandatory' => true,
        ];
    }

    public function getFormFields($generalFormFields): array
    {
        $paymentMethodFormFieds = [
            'mealvoucher_category_default' => [
                'title' => __('Select the default products category', 'mollie-payments-for-woocommerce'),
                'type' => 'select',
                'options' => [
                    self::NO_CATEGORY => __('No category', 'mollie-payments-for-woocommerce'),
                    self::MEAL => __('Meal', 'mollie-payments-for-woocommerce'),
                    self::ECO => __('Eco', 'mollie-payments-for-woocommerce'),
                    self::GIFT => __('Gift', 'mollie-payments-for-woocommerce'),
                ],
                'default' => self::NO_CATEGORY,
                /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
                'description' => __('In order to process it, all products in the order must have a category. This selector will assign the default category for the shop products', 'mollie-payments-for-woocommerce'),
                'desc_tip' => true,
            ],
        ];
        return array_merge($generalFormFields, $paymentMethodFormFieds);
    }

    /**
     * Retrieve the default category saved in db option
     *
     * @return string
     */
    public function voucherDefaultCategory(): string
    {
        $mealvoucherSettings = get_option(
            'mollie_wc_gateway_voucher_settings'
        );
        if (!$mealvoucherSettings) {
            $mealvoucherSettings = get_option(
                'mollie_wc_gateway_mealvoucher_settings'
            );
        }

        return $mealvoucherSettings ? $mealvoucherSettings['mealvoucher_category_default'] : Voucher::NO_CATEGORY;
    }
}
