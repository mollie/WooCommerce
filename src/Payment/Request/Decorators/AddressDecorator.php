<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use WC_Order;

class AddressDecorator implements RequestDecoratorInterface
{
    public function decorate(array $requestData, WC_Order $order, $context = null): array
    {
        $isPayPalExpressOrder = $order->get_meta('_mollie_payment_method_button') === 'PayPalButton';
        $billingAddress = null;
        if (!$isPayPalExpressOrder) {
            $billingAddress = $this->createBillingAddress($order);
            $shippingAddress = $this->createShippingAddress($order);
        }
        $requestData['billingAddress'] = $billingAddress;
        // Only add shippingAddress if all required fields are set
        if (
            !empty($shippingAddress->streetAndNumber)
            && !empty($shippingAddress->postalCode)
            && !empty($shippingAddress->city)
            && !empty($shippingAddress->country)
        ) {
            $requestData['shippingAddress'] = $shippingAddress;
        }

        return $requestData;
    }

    private function createBillingAddress(WC_Order $order)
    {
        // Setup billing and shipping objects
        $billingAddress = new stdClass();

        // Get user details
        $billingAddress->givenName = (ctype_space(
            $order->get_billing_first_name()
        )) ? null : $order->get_billing_first_name();
        $billingAddress->familyName = (ctype_space(
            $order->get_billing_last_name()
        )) ? null : $order->get_billing_last_name();
        $billingAddress->email = (ctype_space($order->get_billing_email()))
            ? null : $order->get_billing_email();
        // Create billingAddress object
        $billingAddress->streetAndNumber = (ctype_space(
            $order->get_billing_address_1()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_address_1(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $billingAddress->streetAdditional = (ctype_space(
            $order->get_billing_address_2()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_address_2(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $billingAddress->postalCode = (ctype_space(
            $order->get_billing_postcode()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_postcode(),
                self::MAXIMAL_LENGHT_POSTALCODE
            );
        $billingAddress->city = (ctype_space($order->get_billing_city()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_city(),
                self::MAXIMAL_LENGHT_CITY
            );
        $billingAddress->region = (ctype_space($order->get_billing_state()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_state(),
                self::MAXIMAL_LENGHT_REGION
            );
        $billingAddress->country = (ctype_space($order->get_billing_country()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_country(),
                self::MAXIMAL_LENGHT_REGION
            );
        $billingAddress->organizationName = $this->billingCompanyField($order);
        $phone = $this->getPhoneNumber($order);
        $billingAddress->phone = (ctype_space($phone))
            ? null
            : $this->getFormatedPhoneNumber($phone);
        return $billingAddress;
    }

    private function createShippingAddress(WC_Order $order)
    {
        $shippingAddress = new stdClass();
        // Get user details
        $shippingAddress->givenName = (ctype_space(
            $order->get_shipping_first_name()
        )) ? null : $order->get_shipping_first_name();
        $shippingAddress->familyName = (ctype_space(
            $order->get_shipping_last_name()
        )) ? null : $order->get_shipping_last_name();
        $shippingAddress->email = (ctype_space($order->get_billing_email()))
            ? null
            : $order->get_billing_email(); // WooCommerce doesn't have a shipping email


        // Create shippingAddress object
        $shippingAddress->streetAndNumber = (ctype_space(
            $order->get_shipping_address_1()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_address_1(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $shippingAddress->streetAdditional = (ctype_space(
            $order->get_shipping_address_2()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_address_2(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $shippingAddress->postalCode = (ctype_space(
            $order->get_shipping_postcode()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_postcode(),
                self::MAXIMAL_LENGHT_POSTALCODE
            );
        $shippingAddress->city = (ctype_space($order->get_shipping_city()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_city(),
                self::MAXIMAL_LENGHT_CITY
            );
        $shippingAddress->region = (ctype_space($order->get_shipping_state()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_state(),
                self::MAXIMAL_LENGHT_REGION
            );
        $shippingAddress->country = (ctype_space(
            $order->get_shipping_country()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_country(),
                self::MAXIMAL_LENGHT_REGION
            );
        return $shippingAddress;
    }

    protected function getPhoneNumber($order)
    {

        $phone = !empty($order->get_billing_phone()) ? $order->get_billing_phone() : $order->get_shipping_phone();
        if (empty($phone)) {
            //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $phone =  wc_clean(wp_unslash($_POST['billing_phone'] ?? ''));
        }
        return $phone;
    }

    protected function getFormatedPhoneNumber(string $phone)
    {
        //remove whitespaces and all non numerical characters except +
        $phone = preg_replace('/[^0-9+]+/', '', $phone);
        if (!is_string($phone)) {
            return null;
        }
        //check if phone starts with 06 and replace with +316
        $phone = transformPhoneToNLFormat($phone);

        //check that $phone is in E164 format or can be changed by api
        if (is_string($phone) && preg_match('/^\+[1-9]\d{10,13}$|^[1-9]\d{9,13}$/', $phone)) {
            return $phone;
        }
        return null;
    }

    /**
     * @param $order
     * @return string|null
     */
    public function billingCompanyField($order): ?string
    {
        if (!trim($order->get_billing_company())) {
            return $this->checkBillieCompanyField($order);
        }
        return $this->maximalFieldLengths(
            $order->get_billing_company(),
            self::MAXIMAL_LENGHT_ADDRESS
        );
    }

    private function checkBillieCompanyField($order)
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$gateway || !$gateway->id) {
            return null;
        }
        $isBillieMethodId = $gateway->id === 'mollie_wc_gateway_billie';
        if ($isBillieMethodId) {
            //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $fieldPosted = wc_clean(wp_unslash($_POST["billing_company"] ?? ''));
            if ($fieldPosted === '' || !is_string($fieldPosted)) {
                return null;
            }
            return $this->maximalFieldLengths(
                $fieldPosted,
                self::MAXIMAL_LENGHT_ADDRESS
            );
        }
        return null;
    }

    /**
     * Method that shortens the field to a certain length
     *
     * @param string $field
     * @param int    $maximalLength
     *
     * @return null|string
     */
    protected function maximalFieldLengths($field, $maximalLength)
    {
        if (!is_string($field)) {
            return null;
        }
        if (is_int($maximalLength) && strlen($field) > $maximalLength) {
            $field = substr($field, 0, $maximalLength);
            $field = !$field ? null : $field;
        }

        return $field;
    }
}
