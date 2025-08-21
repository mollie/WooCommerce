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
     * todo: refactor to a service
     *
     * Retrieves the voucher categories associated with the given product.
     * The method checks various sources for category data in a specific order:
     * Default voucher categories, product-specific meta data, and category term meta data.
     *
     * @param \WC_Product $product The WooCommerce product for which to retrieve voucher categories.
     *
     * @return array An array of category identifiers (or names) associated with the product.
     *               Returns an empty array if no categories are found.
     */
    public static function getCategoriesForProduct(\WC_Product $product): array
    {
        $categories = self::voucherDefaultCategories();
        if ($categories) {
            return self::cleanCategories($categories);
        }

        $localCategories = $product->get_meta(
            $product->is_type('variation') ? 'voucher' : Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION
        );
        //support old setting in a string
        if ($localCategories && !is_array($localCategories)) {
            if ($localCategories === Voucher::NO_CATEGORY) {
                $localCategories = [];
            }
            $localCategories = [$localCategories];
        }
        if ($localCategories) {
            return self::cleanCategories($localCategories);
        }

        $catTermIds = $product->get_category_ids();
        if (!$catTermIds && $product->is_type('variation')) {
            $parentProduct = wc_get_product($product->get_parent_id());
            if ($parentProduct) {
                $catTermIds = $parentProduct->get_category_ids();
            }
        }
        if (!$catTermIds && $product->is_type('variation')) {
            $parentProduct = wc_get_product($product->get_parent_id());
            if ($parentProduct) {
                $catTermIds = $parentProduct->get_category_ids();
            }
        }
        if ($catTermIds) {
            $categoryCategories = [];
            foreach ($catTermIds as $catTermId) {
                $metaCategory = get_term_meta($catTermId, '_mollie_voucher_category', true);
                if ($metaCategory && $metaCategory !== Voucher::NO_CATEGORY) {
                    $categoryCategories[] = $metaCategory;
                }
            }
            if ($categoryCategories) {
                return self::cleanCategories(array_unique($categoryCategories));
            }
        }

        return [];
    }

    /**
     * Filters a list of categories to include only the predefined valid categories.
     * This ensures the resulting array contains only categories recognized by the system.
     *
     * @param array $categories The array of categories to be cleaned.
     *
     * @return array An array containing only valid category identifiers.
     *               Returns an empty array if no valid categories are found.
     */
    public static function cleanCategories(array $categories): array
    {
        return array_filter($categories, static function ($category) {
            return in_array($category, [self::MEAL, self::ECO, self::GIFT, self::SPORT_CULTURE], true);
        });
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
            if ($voucherSettings['mealvoucher_category_default'] && $voucherSettings['mealvoucher_category_default'] !== self::NO_CATEGORY) {
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
