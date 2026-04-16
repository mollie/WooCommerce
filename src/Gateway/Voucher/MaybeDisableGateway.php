<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Gateway\Voucher;

use Mollie\Inpsyde\PaymentGateway\PaymentGateway;
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
            return \true;
        }
        $cart = WC()->cart;
        if (!$cart) {
            return \false;
        }
        $products = $cart->get_cart_contents();
        foreach ($products as $product) {
            if (!$product['data'] instanceof \WC_Product) {
                continue;
            }
            $categories = Voucher::getCategoriesForProduct($product['data']);
            if ($categories) {
                return \true;
            }
        }
        return \false;
    }
}
