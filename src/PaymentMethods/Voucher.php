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
    public const SPORT_CULTURE = 'sport_culture';

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
            'defaultTitle' => 'Voucher',
            'settingsDescription' => '',
            'defaultDescription' => '',
            'paymentFields' => false,
            'instructions' => false,
            'supports' => [
                'products',
            ],
            'filtersOnBuild' => false,
            'confirmationDelayed' => false,
            'docs' => 'https://www.mollie.com/gb/payments/meal-eco-gift-vouchers',
        ];
    }

    public function initializeTranslations(): void
    {
        if ($this->translationsInitialized) {
            return;
        }
        $this->config['defaultTitle'] = __('Voucher', 'mollie-payments-for-woocommerce');
        $this->translationsInitialized = true;
    }

    public function getFormFields($generalFormFields): array
    {
        $paymentMethodFormFieds = [
            'mealvoucher_category_default' => [
                'title' => __('Select the default products categories', 'mollie-payments-for-woocommerce'),
                'type' => 'multiselect',
                'options' => [
                    self::MEAL => __('Meal', 'mollie-payments-for-woocommerce'),
                    self::ECO => __('Eco', 'mollie-payments-for-woocommerce'),
                    self::GIFT => __('Gift', 'mollie-payments-for-woocommerce'),
                    self::SPORT_CULTURE => __('Sport & Culture', 'mollie-payments-for-woocommerce'),
                ],
                'default' => '',
                'class' => 'wc-enhanced-select',
                /* translators: Placeholder 1: Default order status, placeholder 2: Link to 'Hold Stock' setting */
                'description' => __('In order to process it, all products in the order must have a category. This selector will assign the default categories for the shop products. If orders API is active only the first category will be used!', 'mollie-payments-for-woocommerce'),
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

    /**
     * Retrieve the default categories saved in the db option
     *
     * @return array
     */
    public static function voucherDefaultCategories(): array
    {

        $voucherSettings = get_option(
            'mollie_wc_gateway_voucher_settings'
        );
        //get very old setting
        if (! $voucherSettings) {
            $voucherSettings = get_option(
                'mollie_wc_gateway_mealvoucher_settings'
            );
        }

        //convert an old single value option
        if (isset($voucherSettings['mealvoucher_category_default']) && !is_array($voucherSettings['mealvoucher_category_default'])) {
            if ($voucherSettings['mealvoucher_category_default'] !== self::NO_CATEGORY) {
                $voucherSettings['mealvoucher_category_default'] = [ $voucherSettings['mealvoucher_category_default'] ];
            } else {
                $voucherSettings['mealvoucher_category_default'] = [];
            }
        }

        if (!isset($voucherSettings['mealvoucher_category_default']) || !is_array($voucherSettings['mealvoucher_category_default'])) {
            return [];
        }

        return $voucherSettings['mealvoucher_category_default'];
    }
}
