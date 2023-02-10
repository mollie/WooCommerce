<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\BlockService;

use InvalidArgumentException;
use Mollie\WooCommerce\Gateway\Voucher\MaybeDisableGateway;
use Mollie\WooCommerce\Shared\Data;

/**
 * Class CheckoutBlockService
 * @package Mollie\WooCommerce\BlockService
 */
class CheckoutBlockService
{
    protected $dataService;
    /**
     * @var MaybeDisableGateway
     */
    protected $voucherDisabler;

    /**
     * CheckoutBlockService constructor.
     */
    public function __construct(Data $dataService, MaybeDisableGateway $voucherDisabler)
    {
        $this->dataService = $dataService;
        $this->voucherDisabler = $voucherDisabler;
    }

    /**
     * Adds all the Ajax actions to perform the whole workflow
     */
    public function bootstrapAjaxRequest()
    {
        $actionName = 'mollie_checkout_blocks_canmakepayment';
        add_action(
            'wp_ajax_' . $actionName,
            [$this, 'availableGateways']
        );
        add_action(
            'wp_ajax_nopriv_' . $actionName,
            [$this, 'availableGateways']
        );
    }

    /**
     * When the country changes in the checkout block
     * We need to check again the list of available gateways accordingly
     * And return the result with a key based on the evaluated filters for the script to cache
     */
    public function availableGateways()
    {
        $currency = filter_input(INPUT_POST, 'currency', FILTER_SANITIZE_SPECIAL_CHARS);
        $cartTotal = filter_input(INPUT_POST, 'cartTotal', FILTER_SANITIZE_NUMBER_INT);
        $paymentLocale = filter_input(INPUT_POST, 'paymentLocale', FILTER_SANITIZE_SPECIAL_CHARS);
        $billingCountry = filter_input(INPUT_POST, 'billingCountry', FILTER_SANITIZE_SPECIAL_CHARS);
        $cartTotal = $cartTotal / 100;
        $availablePaymentMethods = [];
        try {
            $filters = $this->dataService->getFilters(
                $currency,
                $cartTotal,
                $paymentLocale,
                $billingCountry
            );
        } catch (InvalidArgumentException $exception) {
            $filters = false;
        }
        if ($filters) {
            WC()->customer->set_billing_country($billingCountry);
            $availableGateways = WC()->payment_gateways()->get_available_payment_gateways();
            $availableGateways = $this->removeNonMollieGateway($availableGateways);
            $availableGateways = $this->maybeRemoveVoucher($availableGateways);
            $filterKey = "{$filters['amount']['currency']}-{$filters['locale']}-{$filters['billingCountry']}";
            foreach ($availableGateways as $key => $gateway) {
                $availablePaymentMethods[$filterKey][$key] = $gateway->paymentMethod()->getProperty('id');
            }
        }
        wp_send_json_success($availablePaymentMethods);
    }

    /**
     * Remove the voucher gateway from the available ones
     * if the products in the cart don't fit the requirements
     *
     * @param array $availableGateways
     * @return array
     */
    protected function maybeRemoveVoucher(array $availableGateways): array
    {
        foreach ($availableGateways as $key => $gateway) {
            if ($key !== 'mollie_wc_gateway_voucher') {
                continue;
            }
            $shouldRemoveVoucher = $this->voucherDisabler->shouldRemoveVoucher();
            if ($shouldRemoveVoucher) {
                unset($availableGateways[$key]);
            }
        }
        return $availableGateways;
    }

    /**
     * Remove the non Mollie gateways from the available ones
     * so we don't deal with them in our block logic
     *
     * @param array $availableGateways
     * @return array
     */
    protected function removeNonMollieGateway(array $availableGateways): array
    {
        foreach ($availableGateways as $key => $gateway) {
            if (strpos($key, 'mollie_wc_gateway_') === false) {
                unset($availableGateways[$key]);
            }
        }
        return $availableGateways;
    }
}
