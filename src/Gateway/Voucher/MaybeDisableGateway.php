<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway\Voucher;

use Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerce\PaymentMethods\Voucher;

class MaybeDisableGateway
{
    /**
     * Disable Bank Transfer Gateway
     *
     * @param ?array $gateways
     * @return array
     */
    public function maybeDisableBankTransferGateway(?array $gateways): array
    {
        if (!is_array($gateways)) {
            return [];
        }
        $isWcApiRequest = (bool)filter_input(INPUT_GET, 'wc-api', FILTER_SANITIZE_SPECIAL_CHARS);

        $bankTransferSettings = get_option('mollie_wc_gateway_banktransfer_settings', false);
        //If the setting is active is forced Payment API so we need to filter the gateway when order is in pay-page
        // as it might have been created with Orders API
        $isActiveExpiryDate = $bankTransferSettings
            && isset($bankTransferSettings['activate_expiry_days_setting'])
            && $bankTransferSettings['activate_expiry_days_setting'] === "yes"
            && isset($bankTransferSettings['order_dueDate'])
            && $bankTransferSettings['order_dueDate'] > 0;

        /*
         * There is only one case where we want to filter the gateway and it's when the
         * pay-page render the available payments methods AND the setting is enabled
         *
         * For any other case we want to be sure bank transfer gateway is included.
         */
        if (
            $isWcApiRequest ||
            !$isActiveExpiryDate ||
            is_checkout() && ! is_wc_endpoint_url('order-pay') ||
            !wp_doing_ajax() && ! is_wc_endpoint_url('order-pay') ||
            is_admin()
        ) {
            return $gateways;
        }
        $bankTransferGatewayClassName = 'mollie_wc_gateway_banktransfer';
        unset($gateways[$bankTransferGatewayClassName]);

        return  $gateways;
    }

    /**
     * Disable Voucher Gateway if no categories associated with any product
     * in the cart
     * Disable if Payments API is selected in advanced settings
     *
     * @param ?array $gateways
     *
     * @return array
     */
    public function maybeDisableMealVoucherGateway(?array $gateways): array
    {
        if (!is_array($gateways)) {
            return [];
        }

        $isWcApiRequest = (bool)filter_input(
            INPUT_GET,
            'wc-api',
            FILTER_SANITIZE_SPECIAL_CHARS
        );
        $isCheckoutPage = is_checkout();
        $isOrderPayPage = is_wc_endpoint_url('order-pay');
        // To exclude we are in Checkout or Order Pay page. These are the other options where gateways are required.
        $notInCheckoutOrPayPage = $isWcApiRequest
            || !doing_action('woocommerce_payment_gateways')
            || (!wp_doing_ajax() && !$isOrderPayPage && !$isCheckoutPage);
        $notHasBlocks = !has_block('woocommerce/checkout');
        /*
         * There are 3 cases where we want to filter the gateway and it's when the checkout
         * page render the available payments methods, either classic or block
         * and when we are in the order-pay page.
         *
         * For any other case we want to be sure voucher gateway is included.
         */
        if (($notInCheckoutOrPayPage && $notHasBlocks) || is_admin()) {
            return $gateways;
        }
        $mealVoucherGatewayIndex = false;
        foreach ($gateways as $key => $gateway) {
            if (! mollieWooCommerceIsMollieGateway($gateway)) {
                continue;
            }
            if ($gateway->id === 'mollie_wc_gateway_voucher') {
                $mealVoucherGatewayIndex = $key;
            }
        }

        if (!$this->haveCartProductsCategories()) {
            unset($gateways[$mealVoucherGatewayIndex]);
        }

        return $gateways;
    }

    /**
     * Compares the products in the cart with the categories associated with
     * every product in the cart. So it returns 0 if no products have category
     * and 2 if all products
     * in the cart have a category associated.
     *
     * @return bool
     */
    public function haveCartProductsCategories(): bool
    {
        $cart = WC()->cart;
        $products = $cart->get_cart_contents();
        $mealvoucherSettings = get_option(
            'mollie_wc_gateway_voucher_settings'
        );
        if (!$mealvoucherSettings) {
            $mealvoucherSettings = get_option(
                'mollie_wc_gateway_mealvoucher_settings'
            );
        }
        //Check if mealvoucherSettings is an array as to prevent notice from being thrown for PHP 7.4 and up.
        if (is_array($mealvoucherSettings) && !empty($mealvoucherSettings['mealvoucher_category_default']) && $mealvoucherSettings['mealvoucher_category_default'] !== Voucher::NO_CATEGORY) {
            return true;
        }

        foreach ($products as $product) {
            if (!$product['data'] instanceof \WC_Product) {
                continue;
            }
            $wcProduct = $product['data'];
            $metaKey = $wcProduct->is_type('variation') ? 'voucher' : Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION;
            $localCategories = $wcProduct->get_meta($metaKey, false);
            foreach ($localCategories as $key => $localCategory) {
                assert($localCategory instanceof \WC_Meta_Data);
                $localCategories[$key] = $localCategory->value;
            }
            if (!in_array(Voucher::NO_CATEGORY, $localCategories, true)) {
                return true;
            }

            $catTermIds = $wcProduct->get_category_ids();
            if (!$catTermIds && $wcProduct->is_type('variation')) {
                $parentProduct = wc_get_product($wcProduct->get_parent_id());
                if ($parentProduct) {
                    $catTermIds = $parentProduct->get_category_ids();
                }
            }
            if ($catTermIds) {
                $term_id = end($catTermIds);
                $metaVouchers = [];
                if ($term_id) {
                    $metaVouchers = get_term_meta($term_id, '_mollie_voucher_category', false);
                }
                foreach ($metaVouchers as $key => $metaVoucher) {
                    if (!$metaVoucher || $metaVoucher === Voucher::NO_CATEGORY) {
                        unset($metaVouchers[$key]);
                    }
                }
                if (!empty($metaVouchers)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a product has a default/local category associated
     * that is not No Category
     *
     * @param bool|string $defaultCategory
     * @param bool|string $localCategory
     *
     * @param bool|string $variationCategory
     * @return bool false if no category
     */
    public function productHasVoucherCategory($defaultCategory, $localCategory, $variationCategory = false)
    {
        $defaultCatIsSet = $defaultCategory && ($defaultCategory !== Voucher::NO_CATEGORY);
        $localCatIsNoCat = $localCategory && $localCategory === Voucher::NO_CATEGORY;
        $localCatIsSet = $localCategory && $localCategory !== Voucher::NO_CATEGORY;
        $variationCatIsNoCat = $variationCategory && $variationCategory === Voucher::NO_CATEGORY;
        $variationCatIsSet = $variationCategory && $variationCategory !== Voucher::NO_CATEGORY;
        //In importance order variations ->local product (var, simple, subs) -> general
        if ($variationCatIsNoCat) {
            return false;
        }
        if ($variationCatIsSet) {
            return true;
        }
        if ($localCatIsNoCat) {
            return false;
        }
        if ($localCatIsSet || $defaultCatIsSet) {
            return true;
        }

        return false;
    }
}
