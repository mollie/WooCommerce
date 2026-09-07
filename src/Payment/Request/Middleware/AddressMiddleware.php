<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Mollie\WooCommerce\Shared\FieldConstants;
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
        // Only add billingAddress if all required fields are set or on order API
        if (
            $context === 'order' || (
            !empty($billingAddress->streetAndNumber)
            && !empty($billingAddress->postalCode)
            && !empty($billingAddress->city)
            && !empty($billingAddress->country))
        ) {
            $requestData['billingAddress'] = $billingAddress;
        }
        //set billingAddress email or phone when no billing address is set for payment API and information available
        if ($billingAddress instanceof stdClass && $this->shouldAddMinimalBillingAddress($requestData, $context, $billingAddress)) {
            $requestData['billingAddress'] = $this->createMinimalBillingAddress($billingAddress);
        }
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
            : $this->getFormatedPhoneNumber($phone, $billingAddress->country);
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
        $shippingPhone = $this->isPhoneValid($order->get_shipping_phone()) ? $order->get_shipping_phone() : '';
        $shippingAddress->phone = (ctype_space($order->get_shipping_phone()))
            ? null
            : $this->getFormatedPhoneNumber($shippingPhone, $shippingAddress->country);
        return $shippingAddress;
    }

    /**
     * Check if minimal billing address should be added.
     *
     * @param array<string, mixed> $requestData The request data.
     * @param mixed $context The context.
     * @param stdClass $billingAddress The billing address object.
     * @return bool True if minimal billing address should be added.
     */
    private function shouldAddMinimalBillingAddress(array $requestData, $context, stdClass $billingAddress): bool
    {
        return empty($requestData['billingAddress'])
            && $context === 'payment'
            && (!empty($billingAddress->email) || !empty($billingAddress->phone));
    }

    /**
     * Create minimal billing address with only email and/or phone.
     *
     * @param stdClass $billingAddress The billing address object.
     * @return stdClass The minimal billing address object.
     */
    private function createMinimalBillingAddress(stdClass $billingAddress): stdClass
    {
        $minimalAddress = new stdClass();

        if (!empty($billingAddress->email)) {
            $minimalAddress->email = $billingAddress->email;
        }

        if (!empty($billingAddress->phone)) {
            $minimalAddress->phone = $billingAddress->phone;
        }

        return $minimalAddress;
    }

    /**
     * Get the phone number from the order or the posted field.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string The phone number.
     */
    protected function getPhoneNumber($order): string
    {
        $phoneSources = [
            $order->get_billing_phone(),
            $order->get_shipping_phone(),
            $this->getPostedPhoneNumber($order),
        ];

        foreach ($phoneSources as $phone) {
            if (!empty($phone) && $this->isPhoneValid($phone)) {
                return $phone;
            }
        }

        return '';
    }

    /**
     * Get the phone number from POST data.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string The posted phone number.
     */
    private function getPostedPhoneNumber(WC_Order $order): string
    {
        $postedField = $this->getPhonePostedFieldName($order);

        //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $phoneFromSpecificField = wc_clean(wp_unslash($_POST[$postedField] ?? ''));

        if (!empty($phoneFromSpecificField)) {
            return $phoneFromSpecificField;
        }

        if ($postedField !== 'billing_phone') {
            //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            return wc_clean(wp_unslash($_POST['billing_phone'] ?? ''));
        }

        return '';
    }

    /**
     * Format the phone number in E.164 using libphonenumber.
     *
     * A number already in international form (starting with '+') is trusted as
     * explicit intent and only normalized. A national number is parsed using the
     * address country as the region hint and must be valid for that region.
     * Anything that cannot be turned into a valid E.164 number returns null so no
     * invalid number is ever sent to the Mollie API — a null phone is acceptable
     * for payment methods that do not require one.
     *
     * @param string $phone The phone number.
     * @param string|null $countryCode The ISO 3166-1 alpha-2 region hint.
     * @return string|null The E.164 formatted phone number, or null.
     */
    protected function getFormatedPhoneNumber(string $phone, $countryCode): ?string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        $region = is_string($countryCode) && $countryCode !== '' ? strtoupper($countryCode) : null;
        $isInternational = strpos($phone, '+') === 0;

        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            $parsed = $phoneUtil->parse($phone, $region);
        } catch (NumberParseException $exception) {
            return null;
        }

        // National numbers must be valid for the region hint; an international
        // number is trusted as long as it parses.
        if (!$isInternational && !$phoneUtil->isValidNumber($parsed)) {
            return null;
        }

        return $phoneUtil->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * Loose pre-filter used only to pick a usable phone source (billing, shipping
     * or POST). Authoritative validation happens in getFormatedPhoneNumber() via
     * libphonenumber, so this must not drop formattable national numbers.
     *
     * @param mixed $billing_phone
     * @return bool
     */
    private function isPhoneValid($billing_phone): bool
    {
        if (!is_string($billing_phone) || $billing_phone === '') {
            return false;
        }
        $digits = preg_replace('/\D+/', '', $billing_phone);
        return is_string($digits) && strlen($digits) >= 8;
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
            return $this->getPaymentMethodCompanyField($order);
        }
        return $this->maximalFieldLengths(
            $order->get_billing_company(),
            self::MAXIMAL_LENGTH_ADDRESS
        );
    }

    /**
     * Check the company field.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string|null The company field.
     */
    private function getPaymentMethodCompanyField($order): ?string
    {
        $method = $order->get_payment_method();
        $cleanMethod = str_replace('mollie_wc_gateway_', '', $method);
        $constantName = strtoupper($cleanMethod) . '_COMPANY';
        $companyField = false;
        if (defined(FieldConstants::class . '::' . $constantName)) {
            $companyField = constant(FieldConstants::class . '::' . $constantName);
        }
        if ($companyField) {
            //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $fieldPosted = wc_clean(wp_unslash($_POST[$companyField] ?? ''));
            $company = $fieldPosted ?: $order->get_billing_company() ?: $order->get_shipping_company();
            return $company ? $this->maximalFieldLengths($company, self::MAXIMAL_LENGTH_ADDRESS) : '';
        }
        return '';
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

    /**
     * Each payment method has a different phone number field name or uses the default.
     *
     * @param WC_Order $order
     * @return string The phone posted field name for the given order.
     */
    private function getPhonePostedFieldName(WC_Order $order): string
    {
        $method = $order->get_payment_method();
        $cleanMethod = str_replace('mollie_wc_gateway_', '', $method);
        $constantName = strtoupper($cleanMethod) . '_PHONE';

        if (defined(FieldConstants::class . '::' . $constantName)) {
            return constant(FieldConstants::class . '::' . $constantName);
        }

        return 'billing_phone';
    }
}
