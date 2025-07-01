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
        $voucherSettings = Voucher::voucherDefaultCategories();
        if ($voucherSettings) {
            return true;
        }

        $cart = WC()->cart;
        if (!$cart) {
            return false;
        }
        $products = $cart->get_cart_contents();
        foreach ($products as $product) {
            if (!$product['data'] instanceof \WC_Product) {
                continue;
            }
            $wcProduct = $product['data'];
            $metaKey = $wcProduct->is_type('variation') ? 'voucher' : Voucher::MOLLIE_VOUCHER_CATEGORY_OPTION;
            $localCategories = $wcProduct->get_meta($metaKey);
            //support old setting
            if ($localCategories && !is_array($localCategories)) {
                if ($localCategories === Voucher::NO_CATEGORY) {
                    $localCategories = [];
                }
                $localCategories = [$localCategories];
            }
            if ($localCategories) {
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
                foreach ($catTermIds as $catTermId) {
                    $metaVoucher = get_term_meta($catTermId, '_mollie_voucher_category', true);
                    if ($metaVoucher && $metaVoucher !== Voucher::NO_CATEGORY) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
