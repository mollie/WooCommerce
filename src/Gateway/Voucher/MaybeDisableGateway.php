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
     * Disable Voucher Gateway if no categories associated with any product
     * in the cart
     * Disable if Payments API is selected in advanced settings
     *
     * @param array $gateways
     *
     * @return array
     */
    public function maybeDisableMealVoucherGateway(array $gateways): array
    {
        if (!$this->haveCartProductsCategories()) {
            unset($gateways['mollie_wc_gateway_voucher']);
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
        if (!$cart) {
            return false;
        }
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
            if ($localCategories && !in_array(Voucher::NO_CATEGORY, $localCategories, true)) {
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
}
