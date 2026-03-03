<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

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
class AddressMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
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
        if ($context === 'order' || !empty($billingAddress->streetAndNumber) && !empty($billingAddress->postalCode) && !empty($billingAddress->city) && !empty($billingAddress->country)) {
            $requestData['billingAddress'] = $billingAddress;
        }
        //set billingAddress email when no billing address is set for payment API
        if (empty($requestData['billingAddress']) && $context === 'payment' && !empty($billingAddress->email)) {
            $requestData['billingAddress'] = new stdClass();
            $requestData['billingAddress']->email = $billingAddress->email;
        }
        // Only add shippingAddress if all required fields are set
        if (!empty($shippingAddress->streetAndNumber) && !empty($shippingAddress->postalCode) && !empty($shippingAddress->city) && !empty($shippingAddress->country)) {
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
        $billingAddress->givenName = ctype_space($order->get_billing_first_name()) ? null : $order->get_billing_first_name();
        $billingAddress->familyName = ctype_space($order->get_billing_last_name()) ? null : $order->get_billing_last_name();
        $billingAddress->email = ctype_space($order->get_billing_email()) ? null : $order->get_billing_email();
        // Create billingAddress object
        $billingAddress->streetAndNumber = ctype_space($order->get_billing_address_1()) ? null : $this->maximalFieldLengths($order->get_billing_address_1(), self::MAXIMAL_LENGTH_ADDRESS);
        $billingAddress->streetAdditional = ctype_space($order->get_billing_address_2()) ? null : $this->maximalFieldLengths($order->get_billing_address_2(), self::MAXIMAL_LENGTH_ADDRESS);
        $billingAddress->postalCode = ctype_space($order->get_billing_postcode()) ? null : $this->maximalFieldLengths($order->get_billing_postcode(), self::MAXIMAL_LENGTH_POSTALCODE);
        $billingAddress->city = ctype_space($order->get_billing_city()) ? null : $this->maximalFieldLengths($order->get_billing_city(), self::MAXIMAL_LENGTH_CITY);
        $billingAddress->region = ctype_space($order->get_billing_state()) ? null : $this->maximalFieldLengths($order->get_billing_state(), self::MAXIMAL_LENGTH_REGION);
        $billingAddress->country = ctype_space($order->get_billing_country()) ? null : $this->maximalFieldLengths($order->get_billing_country(), self::MAXIMAL_LENGTH_REGION);
        $billingAddress->organizationName = $this->billingCompanyField($order);
        $phone = $this->getPhoneNumber($order);
        $billingAddress->phone = ctype_space($phone) ? null : $this->getFormatedPhoneNumber($phone, $billingAddress->country);
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
        $shippingAddress->givenName = ctype_space($order->get_shipping_first_name()) ? null : $order->get_shipping_first_name();
        $shippingAddress->familyName = ctype_space($order->get_shipping_last_name()) ? null : $order->get_shipping_last_name();
        $shippingAddress->email = ctype_space($order->get_billing_email()) ? null : $order->get_billing_email();
        // WooCommerce doesn't have a shipping email
        // Create shippingAddress object
        $shippingAddress->streetAndNumber = ctype_space($order->get_shipping_address_1()) ? null : $this->maximalFieldLengths($order->get_shipping_address_1(), self::MAXIMAL_LENGTH_ADDRESS);
        $shippingAddress->streetAdditional = ctype_space($order->get_shipping_address_2()) ? null : $this->maximalFieldLengths($order->get_shipping_address_2(), self::MAXIMAL_LENGTH_ADDRESS);
        $shippingAddress->postalCode = ctype_space($order->get_shipping_postcode()) ? null : $this->maximalFieldLengths($order->get_shipping_postcode(), self::MAXIMAL_LENGTH_POSTALCODE);
        $shippingAddress->city = ctype_space($order->get_shipping_city()) ? null : $this->maximalFieldLengths($order->get_shipping_city(), self::MAXIMAL_LENGTH_CITY);
        $shippingAddress->region = ctype_space($order->get_shipping_state()) ? null : $this->maximalFieldLengths($order->get_shipping_state(), self::MAXIMAL_LENGTH_REGION);
        $shippingAddress->country = ctype_space($order->get_shipping_country()) ? null : $this->maximalFieldLengths($order->get_shipping_country(), self::MAXIMAL_LENGTH_REGION);
        $shippingPhone = $this->isPhoneValid($order->get_shipping_phone()) ? $order->get_shipping_phone() : '';
        $shippingAddress->phone = ctype_space($order->get_shipping_phone()) ? null : $this->getFormatedPhoneNumber($shippingPhone, $shippingAddress->country);
        return $shippingAddress;
    }
    /**
     * Get the phone number from the order or the posted field.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string The phone number.
     */
    protected function getPhoneNumber($order): string
    {
        $phoneSources = [$order->get_billing_phone(), $order->get_shipping_phone(), $this->getPostedPhoneNumber($order)];
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
     * Format the phone number in E.164.
     *
     * @param string $phone The phone number.
     * @return string|null The formatted phone number.
     */
    protected function getFormatedPhoneNumber(string $phone, $countryCode): ?string
    {
        //remove whitespaces and all non numerical characters except +
        $phone = preg_replace('/[^0-9+]+/', '', $phone);
        if (!is_string($phone) || strlen($phone) < 9) {
            return null;
        }
        if (strpos($phone, '00') === 0) {
            $phone = '+' . substr($phone, 2);
        }
        if (strpos($phone, '0') === 0) {
            $prefix = $this->countryCodeToPhonePrefix($countryCode);
            if ($prefix) {
                $phone = '+' . $prefix . substr($phone, 1);
            }
        }
        if (strlen($phone) <= 16) {
            return $phone;
        }
        return null;
    }
    private function isPhoneValid($billing_phone)
    {
        return preg_match('/^\+[1-9]\d{10,13}$|^[1-9]\d{9,13}$|^06\d{9,13}$/', $billing_phone);
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
        return $this->maximalFieldLengths($order->get_billing_company(), self::MAXIMAL_LENGTH_ADDRESS);
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
        $companyField = \false;
        if (defined(FieldConstants::class . '::' . $constantName)) {
            $companyField = constant(FieldConstants::class . '::' . $constantName);
        }
        if ($companyField) {
            //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $fieldPosted = wc_clean(wp_unslash($_POST[$companyField] ?? ''));
            $company = ($fieldPosted ?: $order->get_billing_company()) ?: $order->get_shipping_company();
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
     * Converts a country code to its corresponding phone prefix.
     *
     * @param string $countryCode The ISO 3166-1 alpha-2 country code.
     * @return string|null The phone prefix associated with the country code, or null if not found.
     */
    private function countryCodeToPhonePrefix($countryCode): ?string
    {
        $phoneCodes = ['AF' => '93', 'AL' => '355', 'DZ' => '213', 'AS' => '1684', 'AD' => '376', 'AO' => '244', 'AI' => '1264', 'AQ' => '672', 'AG' => '1268', 'AR' => '54', 'AM' => '374', 'AW' => '297', 'AU' => '61', 'AT' => '43', 'AZ' => '994', 'BS' => '1242', 'BH' => '973', 'BD' => '880', 'BB' => '1246', 'BY' => '375', 'BE' => '32', 'BZ' => '501', 'BJ' => '229', 'BM' => '1441', 'BT' => '975', 'BO' => '591', 'BA' => '387', 'BW' => '267', 'BR' => '55', 'IO' => '246', 'VG' => '1284', 'BN' => '673', 'BG' => '359', 'BF' => '226', 'BI' => '257', 'KH' => '855', 'CM' => '237', 'CA' => '1', 'CV' => '238', 'KY' => '1345', 'CF' => '236', 'TD' => '235', 'CL' => '56', 'CN' => '86', 'CX' => '61', 'CC' => '61', 'CO' => '57', 'KM' => '269', 'CK' => '682', 'CR' => '506', 'HR' => '385', 'CU' => '53', 'CW' => '599', 'CY' => '357', 'CZ' => '420', 'CD' => '243', 'DK' => '45', 'DJ' => '253', 'DM' => '1767', 'DO' => '1809', 'TL' => '670', 'EC' => '593', 'EG' => '20', 'SV' => '503', 'GQ' => '240', 'ER' => '291', 'EE' => '372', 'ET' => '251', 'FK' => '500', 'FO' => '298', 'FJ' => '679', 'FI' => '358', 'FR' => '33', 'PF' => '689', 'GA' => '241', 'GM' => '220', 'GE' => '995', 'DE' => '49', 'GH' => '233', 'GI' => '350', 'GR' => '30', 'GL' => '299', 'GD' => '1473', 'GU' => '1671', 'GT' => '502', 'GG' => '441481', 'GN' => '224', 'GW' => '245', 'GY' => '592', 'HT' => '509', 'HN' => '504', 'HK' => '852', 'HU' => '36', 'IS' => '354', 'IN' => '91', 'ID' => '62', 'IR' => '98', 'IQ' => '964', 'IE' => '353', 'IM' => '441624', 'IL' => '972', 'IT' => '39', 'CI' => '225', 'JM' => '1876', 'JP' => '81', 'JE' => '441534', 'JO' => '962', 'KZ' => '7', 'KE' => '254', 'KI' => '686', 'XK' => '383', 'KW' => '965', 'KG' => '996', 'LA' => '856', 'LV' => '371', 'LB' => '961', 'LS' => '266', 'LR' => '231', 'LY' => '218', 'LI' => '423', 'LT' => '370', 'LU' => '352', 'MO' => '853', 'MK' => '389', 'MG' => '261', 'MW' => '265', 'MY' => '60', 'MV' => '960', 'ML' => '223', 'MT' => '356', 'MH' => '692', 'MR' => '222', 'MU' => '230', 'YT' => '262', 'MX' => '52', 'FM' => '691', 'MD' => '373', 'MC' => '377', 'MN' => '976', 'ME' => '382', 'MS' => '1664', 'MA' => '212', 'MZ' => '258', 'MM' => '95', 'NA' => '264', 'NR' => '674', 'NP' => '977', 'NL' => '31', 'AN' => '599', 'NC' => '687', 'NZ' => '64', 'NI' => '505', 'NE' => '227', 'NG' => '234', 'NU' => '683', 'KP' => '850', 'MP' => '1670', 'NO' => '47', 'OM' => '968', 'PK' => '92', 'PW' => '680', 'PS' => '970', 'PA' => '507', 'PG' => '675', 'PY' => '595', 'PE' => '51', 'PH' => '63', 'PN' => '64', 'PL' => '48', 'PT' => '351', 'PR' => '1787', 'QA' => '974', 'CG' => '242', 'RE' => '262', 'RO' => '40', 'RU' => '7', 'RW' => '250', 'BL' => '590', 'SH' => '290', 'KN' => '1869', 'LC' => '1758', 'MF' => '590', 'PM' => '508', 'VC' => '1784', 'WS' => '685', 'SM' => '378', 'ST' => '239', 'SA' => '966', 'SN' => '221', 'RS' => '381', 'SC' => '248', 'SL' => '232', 'SG' => '65', 'SX' => '1721', 'SK' => '421', 'SI' => '386', 'SB' => '677', 'SO' => '252', 'ZA' => '27', 'KR' => '82', 'SS' => '211', 'ES' => '34', 'LK' => '94', 'SD' => '249', 'SR' => '597', 'SJ' => '47', 'SZ' => '268', 'SE' => '46', 'CH' => '41', 'SY' => '963', 'TW' => '886', 'TJ' => '992', 'TZ' => '255', 'TH' => '66', 'TG' => '228', 'TK' => '690', 'TO' => '676', 'TT' => '1868', 'TN' => '216', 'TR' => '90', 'TM' => '993', 'TC' => '1649', 'TV' => '688', 'VI' => '1340', 'UG' => '256', 'UA' => '380', 'AE' => '971', 'GB' => '44', 'US' => '1', 'UY' => '598', 'UZ' => '998', 'VU' => '678', 'VA' => '379', 'VE' => '58', 'VN' => '84', 'WF' => '681', 'EH' => '212', 'YE' => '967', 'ZM' => '260', 'ZW' => '263'];
        return $phoneCodes[$countryCode] ?? null;
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
