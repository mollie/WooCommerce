<?php


class Mollie_WC_Helper_MaybeDisableGateway
{
    /**
     * Disable Meal Voucher Gateway if no categories associated with any product
     * in the cart
     *
     * @param array $gateways
     * @return array
     */
    public function maybeDisableMealVoucherGateway(array $gateways)
    {


        $isWcApiRequest = (bool)filter_input(INPUT_GET, 'wc-api', FILTER_SANITIZE_STRING);
        $wooCommerceSession = mollieWooCommerceSession();


        /*
         * There is only one case where we want to filter the gateway and it's when the checkout
         * page render the available payments methods.
         *
         * For any other case we want to be sure apple pay gateway is included.
         */
        if ($isWcApiRequest ||
            !$wooCommerceSession instanceof WC_Session ||
            !doing_action('woocommerce_payment_gateways') ||
            !wp_doing_ajax() ||
            is_admin()
        ) {
            return $gateways;
        }

        $mealvoucherGatewayClassName = 'Mollie_WC_Gateway_Mealvoucher';
        $mealVoucherGatewayIndex = array_search($mealvoucherGatewayClassName, $gateways, true);

        $productsWithCategory = $this->numberProductsWithCategory();

        if($mealVoucherGatewayIndex !== false && $productsWithCategory == 0){
            unset($gateways[$mealVoucherGatewayIndex]);
        }

        return $gateways;
    }
    /**
     * Compares the products in the cart with the categories associated with
     * every product in the cart. So it returns 0 if no products have category
     * 1 if not all products in the cart have category, and 2 if all products
     * in the cart have a category associated.
     *
     * @return int
     */
    public function numberProductsWithCategory()
    {
        $cart = WC()->cart;
        $products = $cart->get_cart_contents();
        $mealvoucherSettings = get_option('mollie_wc_gateway_mealvoucher_settings');
        $defaultCategory = $mealvoucherSettings['mealvoucher_category_default'];
        $numberOfProducts = 0;
        $productsWithCategory = 0;
        foreach ($products as $product) {

            $postmeta = get_post_meta($product['product_id']);

            $localCategory = array_key_exists('_mollie_voucher_category', $postmeta)?$postmeta['_mollie_voucher_category'][0]: '';

            if($this->productHasVoucherCategory($defaultCategory, $localCategory)){
                $productsWithCategory++;
            }
            $numberOfProducts++;
        }
        if($productsWithCategory === 0){
            return 0;
        }
        if($productsWithCategory === $numberOfProducts){
            return 2;
        }
        return 1;
    }


    /**
     * Check if a product has a default/local category associated
     * that is not No Category
     *
     * @param string $defaultCategory
     * @param string $localCategory
     *
     * @return bool false if no category
     */
    public function productHasVoucherCategory($defaultCategory, $localCategory)
    {
        if ($defaultCategory
            && ($localCategory !== Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY)
        ) {
            return true;
        }
        if (!$defaultCategory && $localCategory
            && ($localCategory !== Mollie_WC_Gateway_Mealvoucher::NO_CATEGORY)
        ) {
            return true;
        }
        return false;
    }

}

