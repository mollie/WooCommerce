<?php


class Mollie_WC_Helper_MaybeDisableGateway
{
    /**
     * Disable Meal Voucher Gateway if no categories associated with any product
     * in the cart
     *
     * @param array $gateways
     *
     * @return array
     */
    public function maybeDisableMealVoucherGateway(array $gateways)
    {
        $isWcApiRequest = (bool)filter_input(
            INPUT_GET,
            'wc-api',
            FILTER_SANITIZE_STRING
        );

        /*
         * There is only one case where we want to filter the gateway and it's when the checkout
         * page render the available payments methods.
         *
         * For any other case we want to be sure mealvoucher gateway is included.
         */
        if ($isWcApiRequest || !doing_action('woocommerce_payment_gateways')
            || !wp_doing_ajax()
            || is_admin()
        ) {
            return $gateways;
        }

        $mealvoucherGatewayClassName = 'Mollie_WC_Gateway_Mealvoucher';
        $mealVoucherGatewayIndex = array_search(
            $mealvoucherGatewayClassName,
            $gateways,
            true
        );

        $productsWithCategory = $this->numberProductsWithCategory();

        if ($mealVoucherGatewayIndex !== false && $productsWithCategory == 0) {
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
     * @return int
     */
    public function numberProductsWithCategory()
    {
        $cart = WC()->cart;
        $products = $cart->get_cart_contents();
        $mealvoucherSettings = get_option(
            'mollie_wc_gateway_mealvoucher_settings'
        );
        //Check if mealvoucherSettings is an array as to prevent notice from being thrown for PHP 7.4 and up.
        if (is_array($mealvoucherSettings)) {
            $defaultCategory = $mealvoucherSettings['mealvoucher_category_default'];
        } else {
            $defaultCategory = false;
        }
        $numberOfProducts = 0;
        $productsWithCategory = 0;
        $variationCategory = false;
        foreach ($products as $product) {
            $postmeta = get_post_meta($product['product_id']);
            $localCategory = array_key_exists(
                Mollie_WC_Gateway_Mealvoucher::MOLLIE_VOUCHER_CATEGORY_OPTION,
                $postmeta
            ) ? $postmeta[Mollie_WC_Gateway_Mealvoucher::MOLLIE_VOUCHER_CATEGORY_OPTION][0] : false;
            if ( isset($product['variation_id']) ) {
                $postmeta = get_post_meta($product['variation_id']);
                $postmeta = is_array($postmeta)?$postmeta:[];
                $variationCategory = array_key_exists(
                    'voucher',
                    $postmeta
                ) ? $postmeta['voucher'][0] : false;
            }

            if ($this->productHasVoucherCategory(
                $defaultCategory,
                $localCategory,
                $variationCategory
            )
            ) {
                $productsWithCategory++;
            }
            $numberOfProducts++;
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
        $defaultCatIsSet = $defaultCategory && ($defaultCategory !== Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY);
        $localCatIsNoCat = $localCategory && $localCategory === Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY;
        $localCatIsSet = $localCategory && $localCategory !== Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY;
        $variationCatIsNoCat = $variationCategory && $variationCategory === Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY;
        $variationCatIsSet = $variationCategory && $variationCategory !== Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY;
        //In importance order variations ->local product (var, simple, subs) -> general
        if($variationCatIsNoCat){
            return false;
        }
        if($variationCatIsSet){
            return true;
        }
        if($localCatIsNoCat){
            return false;
        }
        if($localCatIsSet || $defaultCatIsSet){
            return true;
        }

        return false;
    }

}

