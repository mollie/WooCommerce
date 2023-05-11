<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway\Voucher;

use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Payment\PaymentService;
use Mollie\WooCommerce\PaymentMethods\Voucher;

class MaybeDisableGateway
{
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
        // To exclude we are in Checkout or Order Pay page. These are the other options where gateways are required.
        $notInCheckoutOrPayPage = $isWcApiRequest
            || !doing_action('woocommerce_payment_gateways')
            || (!wp_doing_ajax() && !is_wc_endpoint_url('order-pay'));
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
            if (!($gateway instanceof MolliePaymentGateway)) {
                continue;
            }
            if ($gateway->id === 'mollie_wc_gateway_voucher') {
                $mealVoucherGatewayIndex = $key;
            }
        }

        $productsWithCategory = $this->numberProductsWithCategory();
        $paymentAPISetting = get_option('mollie-payments-for-woocommerce_api_switch') === PaymentService::PAYMENT_METHOD_TYPE_PAYMENT;

        if ($mealVoucherGatewayIndex !== false && ($productsWithCategory === 0 || $paymentAPISetting)) {
            unset($gateways[$mealVoucherGatewayIndex]);
        }

        return $gateways;
    }

    /**
     * If there are no products with category
     * then we should not see the voucher gateway
     *
     * @return bool
     */
    public function shouldRemoveVoucher(): bool
    {
        $productsWithCategory = $this->numberProductsWithCategory();
        return $productsWithCategory === 0;
    }

    /**
     * Compares the products in the cart with the categories associated with
     * every product in the cart. So it returns 0 if no products have category
     * and 2 if all products
     * in the cart have a category associated.
     *
     * @return int
     */
    public function numberProductsWithCategory()
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
        if (is_array($mealvoucherSettings) && isset($mealvoucherSettings['mealvoucher_category_default'])) {
            $defaultCategory = $mealvoucherSettings['mealvoucher_category_default'];
        } else {
            $defaultCategory = false;
        }
        $productsWithCategory = 0;
        $variationCategory = false;
        foreach ($products as $product) {
            $postmeta = get_post_meta($product['product_id']);
            $localCategory = array_key_exists(
                Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION,
                $postmeta
            ) ? $postmeta[Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION][0] : false;
            if (isset($product['variation_id'])) {
                $postmeta = get_post_meta($product['variation_id']);
                $postmeta = is_array($postmeta) ? $postmeta : [];
                $variationCategory = array_key_exists(
                    'voucher',
                    $postmeta
                ) ? $postmeta['voucher'][0] : false;
            }

            if (
                $this->productHasVoucherCategory(
                    $defaultCategory,
                    $localCategory,
                    $variationCategory
                )
            ) {
                $productsWithCategory++;
            }
        }
        if ($productsWithCategory === 0) {
            return 0;
        }
        return 2;
    }

    /**
     * Check if a product has a default/local category associated
     * that is not No Category
     *
     * @param string $defaultCategory
     * @param string $localCategory
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
