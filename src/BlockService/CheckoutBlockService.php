<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\BlockService;

use InvalidArgumentException;

class CheckoutBlockService
{
    protected $dataService;

    /**
     * CheckoutBlockService constructor.
     */
    public function __construct($dataService)
    {
        $this->dataService = $dataService;
    }

    /**
     * Adds all the Ajax actions to perform the whole workflow
     */
    public function bootstrapAjaxRequest()
    {
        $actionName = 'mollie_checkout_blocks_canmakepayment';
        add_action(
            'wp_ajax_' . $actionName,
            function () {
                return $this->availableGateways();
            }
        );
        add_action(
            'wp_ajax_nopriv_' . $actionName,
            function () {
                return $this->availableGateways();
            }
        );
    }

    public function availableGateways()
    {
        $currency = filter_var($_POST['currency'], FILTER_SANITIZE_STRING);
        $cartTotal = filter_var(
            $_POST['cartTotal'],
            FILTER_SANITIZE_NUMBER_INT
        );
        $paymentLocale = filter_var(
            $_POST['paymentLocale'],
            FILTER_SANITIZE_STRING
        );
        $billingCountry = filter_var(
            $_POST['billingCountry'],
            FILTER_SANITIZE_STRING
        );
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
            $availableGateways = WC()->payment_gateways()->get_available_payment_gateways();
            foreach ($availableGateways as $key => $gateway){
                if(strpos($key, 'mollie_wc_gateway_') === false){
                    unset($availableGateways[$key]);
                }
            }
            $filterKey = "{$filters['amount']['currency']}-{$filters['locale']}-{$filters['billingCountry']}";
            foreach ($availableGateways as $key => $gateway){
                $availablePaymentMethods[$filterKey][$key] = $gateway->paymentMethod->getProperty('id');
            }
        }

        wp_send_json_success($availablePaymentMethods);
    }
}
