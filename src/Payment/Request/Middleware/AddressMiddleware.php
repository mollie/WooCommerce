<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use stdClass;
use WC_Order;

/**
 * Class AddressMiddleware
 *
 * This middleware adds address information to the payment request data.
 *
 * @package Mollie\WooCommerce\Payment\Request\Middleware
 */
class AddressMiddleware implements RequestMiddlewareInterface
{
    public const MAXIMAL_LENGTH_ADDRESS = 100;
    public const MAXIMAL_LENGTH_POSTALCODE = 20;
    public const MAXIMAL_LENGTH_CITY = 200;
    public const MAXIMAL_LENGTH_REGION = 200;

    /**
     * Invoke the middleware.
     *
     * @param array<string, mixed> $requestData The request data to be modified.
     * @param WC_Order $order The WooCommerce order object.
     * @param mixed $context Additional context for the middleware.
     * @param callable $next The next middleware to be called.
     * @return array<string, mixed> The modified request data.
     */
    public function __invoke(array $requestData, WC_Order $order, $context, callable $next): array
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
        return $next($requestData, $order, $context);
    }

    /**
     * Create the billing address object.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return stdClass The billing address object.
     */
    private function createBillingAddress(WC_Order $order): stdClass
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
                self::MAXIMAL_LENGTH_ADDRESS
            );
        $billingAddress->streetAdditional = (ctype_space(
            $order->get_billing_address_2()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_address_2(),
                self::MAXIMAL_LENGTH_ADDRESS
            );
        $billingAddress->postalCode = (ctype_space(
            $order->get_billing_postcode()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_postcode(),
                self::MAXIMAL_LENGTH_POSTALCODE
            );
        $billingAddress->city = (ctype_space($order->get_billing_city()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_city(),
                self::MAXIMAL_LENGTH_CITY
            );
        $billingAddress->region = (ctype_space($order->get_billing_state()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_state(),
                self::MAXIMAL_LENGTH_REGION
            );
        $billingAddress->country = (ctype_space($order->get_billing_country()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_country(),
                self::MAXIMAL_LENGTH_REGION
            );
        $billingAddress->organizationName = $this->billingCompanyField($order);
        $phone = $this->getPhoneNumber($order);
        $billingAddress->phone = (ctype_space($phone))
            ? null
            : $this->getFormatedPhoneNumber($phone);
        return $billingAddress;
    }

    /**
     * Create the shipping address object.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return stdClass The shipping address object.
     */
    private function createShippingAddress(WC_Order $order): stdClass
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
                self::MAXIMAL_LENGTH_ADDRESS
            );
        $shippingAddress->streetAdditional = (ctype_space(
            $order->get_shipping_address_2()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_address_2(),
                self::MAXIMAL_LENGTH_ADDRESS
            );
        $shippingAddress->postalCode = (ctype_space(
            $order->get_shipping_postcode()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_postcode(),
                self::MAXIMAL_LENGTH_POSTALCODE
            );
        $shippingAddress->city = (ctype_space($order->get_shipping_city()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_city(),
                self::MAXIMAL_LENGTH_CITY
            );
        $shippingAddress->region = (ctype_space($order->get_shipping_state()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_state(),
                self::MAXIMAL_LENGTH_REGION
            );
        $shippingAddress->country = (ctype_space(
            $order->get_shipping_country()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_country(),
                self::MAXIMAL_LENGTH_REGION
            );
        return $shippingAddress;
    }

    /**
     * Get the phone number from the order.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string|null The phone number.
     */
    protected function getPhoneNumber($order): ?string
    {
        $phone = !empty($order->get_billing_phone()) ? $order->get_billing_phone() : $order->get_shipping_phone();
        if (empty($phone)) {
            //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $phone = wc_clean(wp_unslash($_POST['billing_phone'] ?? ''));
        }
        return $phone;
    }

    /**
     * Format the phone number.
     *
     * @param string $phone The phone number.
     * @return string|null The formatted phone number.
     */
    protected function getFormatedPhoneNumber(string $phone): ?string
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
     * Get the billing company field.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string|null The billing company field.
     */
    public function billingCompanyField($order): ?string
    {
        if (!trim($order->get_billing_company())) {
            return $this->checkBillieCompanyField($order);
        }
        return $this->maximalFieldLengths(
            $order->get_billing_company(),
            self::MAXIMAL_LENGTH_ADDRESS
        );
    }

    /**
     * Check the Billie company field.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string|null The Billie company field.
     */
    private function checkBillieCompanyField($order): ?string
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
                self::MAXIMAL_LENGTH_ADDRESS
            );
        }
        return null;
    }

    /**
     * Method that shortens the field to a certain length.
     *
     * @param string $field The field to be shortened.
     * @param int $maximalLength The maximal length of the field.
     * @return string|null The shortened field.
     */
    protected function maximalFieldLengths($field, $maximalLength): ?string
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
