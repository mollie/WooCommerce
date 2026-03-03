<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Buttons\ApplePayButton;

use Mollie\Psr\Log\LoggerInterface as Logger;
use Mollie\Psr\Log\LogLevel;
class ApplePayDataObjectHttp
{
    /**
     * @var mixed
     */
    protected $nonce;
    /**
     * @var mixed
     */
    protected $validationUrl;
    /**
     * @var mixed
     */
    protected $simplifiedContact;
    /**
     * @var mixed|null
     */
    protected $needShipping;
    /**
     * @var mixed
     */
    protected $productId;
    /**
     * @var mixed
     */
    protected $productQuantity;
    /**
     * @var array|mixed
     */
    protected $shippingMethod = [];
    /**
     * @var string[]
     */
    protected $billingAddress = [];
    /**
     * @var string[]
     */
    protected $shippingAddress = [];
    /**
     * @var array
     */
    protected $errors = [];
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * ApplePayDataObjectHttp constructor.
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
    /**
     * Resets the errors array
     */
    protected function resetErrors()
    {
        $this->errors = [];
    }
    /**
     * Returns if the object has any errors
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }
    /**
     * Returns errors
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }
    /**
     * Set the object with the data relevant to ApplePay validation
     */
    public function validationData()
    {
        if (!$this->isNonceValid()) {
            return;
        }
        $data = $this->getFilteredRequestData();
        $this->resetErrors();
        if (!$this->hasRequiredFieldsValuesOrError($data, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::VALIDATION_REQUIRED_FIELDS)) {
            return;
        }
        $this->assignDataObjectValues($data);
    }
    /**
     * Set the object with the data relevant to ApplePay on update shipping contact
     * Required data depends on callerPage
     */
    public function updateContactData()
    {
        if (!$this->isNonceValid()) {
            return;
        }
        $data = $this->getFilteredRequestData();
        $result = $this->updateRequiredData($data, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::UPDATE_CONTACT_CART_REQUIRED_FIELDS);
        if (!$result) {
            return;
        }
        $this->updateSimplifiedContact($data[\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SIMPLIFIED_CONTACT]);
    }
    /**
     * Set the object with the data relevant to ApplePay on update shipping method
     * Required data depends on callerPage
     */
    public function updateMethodData()
    {
        if (!$this->isNonceValid()) {
            return;
        }
        $data = $this->getFilteredRequestData();
        $result = $this->updateRequiredData($data, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::UPDATE_METHOD_CART_REQUIRED_FIELDS);
        if (!$result) {
            return;
        }
        $this->updateSimplifiedContact($data[\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SIMPLIFIED_CONTACT]);
        $this->updateShippingMethod($data);
    }
    /**
     * Set the object with the data relevant to ApplePay on authorized order
     * Required data depends on callerPage
     *
     * @param       $callerPage
     */
    public function orderData($callerPage)
    {
        if (!$this->isNonceValid()) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $data = filter_var_array($_POST, \FILTER_SANITIZE_SPECIAL_CHARS);
        $data[\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::CALLER_PAGE] = $callerPage;
        $result = $this->updateRequiredData($data, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::CREATE_ORDER_CART_REQUIRED_FIELDS);
        if (!$result) {
            return;
        }
        if (!array_key_exists('emailAddress', $data[\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SHIPPING_CONTACT]) || !$data[\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SHIPPING_CONTACT]['emailAddress']) {
            $this->errors[] = ['errorCode' => \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SHIPPING_CONTACT_INVALID, 'contactField' => 'emailAddress'];
            return;
        }
        $filteredShippingContact = $data[\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SHIPPING_CONTACT];
        $this->shippingAddress = $this->completeAddress($filteredShippingContact, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SHIPPING_CONTACT_INVALID);
        $filteredbillingContact = $data[\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::BILLING_CONTACT];
        $this->billingAddress = $this->completeAddress($filteredbillingContact, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::BILLING_CONTACT_INVALID);
        $this->updateShippingMethod($data);
    }
    /**
     * Checks if the array contains all required fields and if those
     * are not empty.
     * If not it adds an unknown error to the object's error list, as this errors
     * are not supported by ApplePay
     *
     *
     * @return bool
     */
    protected function hasRequiredFieldsValuesOrError(array $data, array $required)
    {
        foreach ($required as $requiredField) {
            if (!array_key_exists($requiredField, $data)) {
                $this->logger->debug(sprintf('ApplePay Data Error: Missing index %s', $requiredField));
                $this->errors[] = ['errorCode' => 'unknown'];
                continue;
            }
            if ($data[$requiredField] === null || $data[$requiredField] === '') {
                $this->logger->debug(sprintf('ApplePay Data Error: Missing value for %s', $requiredField));
                $this->errors[] = ['errorCode' => 'unknown'];
                continue;
            }
        }
        return !$this->hasErrors();
    }
    /**
     * Sets the value to the appropriate field in the object
     */
    protected function assignDataObjectValues(array $data)
    {
        foreach ($data as $key => $value) {
            if ($key === 'woocommerce-process-checkout-nonce') {
                $key = 'nonce';
            }
            $this->{$key} = $value;
        }
    }
    /**
     * Returns the address details used in pre-authorization steps
     * @param array $contactInfo
     *
     * @return string[]
     *
     */
    protected function simplifiedAddress($contactInfo)
    {
        $required = ['locality' => 'locality', 'postalCode' => 'postalCode', 'countryCode' => 'countryCode'];
        if (!$this->addressHasRequiredFieldsValues($contactInfo, $required, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SHIPPING_CONTACT_INVALID)) {
            return [];
        }
        return ['city' => $contactInfo['locality'], 'postcode' => $contactInfo['postalCode'], 'country' => strtoupper($contactInfo['countryCode'])];
    }
    /**
     * Checks if the address array contains all required fields and if those
     * are not empty.
     * If not it adds a contactField error to the object's error list
     *
     * @param array  $post      The address to check
     * @param array  $required  The required fields for the given address
     * @param string $errorCode Either shipping or billing kind
     *
     * @return bool
     */
    protected function addressHasRequiredFieldsValues(array $post, array $required, $errorCode)
    {
        foreach ($required as $requiredField => $errorValue) {
            if (!array_key_exists($requiredField, $post)) {
                $this->logger->debug(sprintf('ApplePay Data Error: Missing index %s', $requiredField));
                $this->errors[] = ['errorCode' => 'unknown'];
                continue;
            }
            if (!$post[$requiredField]) {
                $this->logger->debug(sprintf('ApplePay Data Error: Missing value for %s', $requiredField));
                $this->errors[] = ['errorCode' => $errorCode, 'contactField' => $errorValue];
                continue;
            }
        }
        return !$this->hasErrors();
    }
    /**
     * Returns the address details for after authorization steps
     *
     * @param array  $data
     *
     * @param string $errorCode differentiates between billing and shipping information
     *
     * @return string[]
     */
    protected function completeAddress($data, $errorCode)
    {
        $required = ['givenName' => 'name', 'familyName' => 'name', 'addressLines' => 'addressLines', 'locality' => 'locality', 'postalCode' => 'postalCode', 'countryCode' => 'countryCode'];
        if (!$this->addressHasRequiredFieldsValues($data, $required, $errorCode)) {
            return [];
        }
        return ['first_name' => $data['givenName'], 'last_name' => $data['familyName'], 'email' => $data['emailAddress'] ?? '', 'phone' => $data['phoneNumber'] ?? '', 'address_1' => $data['addressLines'][0] ?? '', 'address_2' => $data['addressLines'][1] ?? '', 'city' => $data['locality'], 'state' => $data['administrativeArea'], 'postcode' => $data['postalCode'], 'country' => strtoupper($data['countryCode'])];
    }
    /**
     * @param       $requiredProductFields
     * @param       $requiredCartFields
     */
    protected function updateRequiredData(array $data, $requiredProductFields, $requiredCartFields)
    {
        $this->resetErrors();
        $requiredFields = $requiredProductFields;
        if (isset($data[\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::CALLER_PAGE]) && $data[\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::CALLER_PAGE] === 'cart') {
            $requiredFields = $requiredCartFields;
        }
        $hasRequiredFieldsValues = $this->hasRequiredFieldsValuesOrError($data, $requiredFields);
        if (!$hasRequiredFieldsValues) {
            return \false;
        }
        $this->assignDataObjectValues($data);
        return \true;
    }
    /**
     * @param $data
     */
    protected function updateSimplifiedContact($data)
    {
        $simplifiedContactInfo = array_map('sanitize_text_field', $data);
        $this->simplifiedContact = $this->simplifiedAddress($simplifiedContactInfo);
    }
    protected function updateShippingMethod(array $data)
    {
        if (array_key_exists(\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SHIPPING_METHOD, $data)) {
            $this->shippingMethod = filter_var_array($data[\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SHIPPING_METHOD], \FILTER_SANITIZE_SPECIAL_CHARS);
        }
    }
    public function billingAddress(): array
    {
        return $this->billingAddress;
    }
    public function shippingAddress(): array
    {
        return $this->shippingAddress;
    }
    public function shippingMethod(): array
    {
        return $this->shippingMethod ?? [];
    }
    public function needShipping(): bool
    {
        return $this->needShipping;
    }
    public function productId(): string
    {
        return $this->productId;
    }
    public function productQuantity(): string
    {
        return $this->productQuantity;
    }
    public function nonce()
    {
        return $this->nonce;
    }
    public function validationUrl()
    {
        return $this->validationUrl;
    }
    public function simplifiedContact()
    {
        return $this->simplifiedContact;
    }
    public function isNonceValid()
    {
        $nonce = filter_input(\INPUT_POST, 'woocommerce-process-checkout-nonce', \FILTER_SANITIZE_SPECIAL_CHARS);
        return wp_verify_nonce($nonce, 'woocommerce-process_checkout') || wp_verify_nonce($nonce, 'mollie_apple_pay_blocks');
    }
    /**
     * @return array|false|null
     */
    public function getFilteredRequestData()
    {
        return filter_input_array(\INPUT_POST, [\Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::CALLER_PAGE => \FILTER_SANITIZE_SPECIAL_CHARS, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::VALIDATION_URL => \FILTER_SANITIZE_SPECIAL_CHARS, 'woocommerce-process-checkout-nonce' => \FILTER_SANITIZE_SPECIAL_CHARS, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::NEED_SHIPPING => \FILTER_VALIDATE_BOOLEAN, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SIMPLIFIED_CONTACT => ['filter' => \FILTER_SANITIZE_SPECIAL_CHARS, 'flags' => \FILTER_REQUIRE_ARRAY], \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SHIPPING_CONTACT => ['filter' => \FILTER_SANITIZE_SPECIAL_CHARS, 'flags' => \FILTER_REQUIRE_ARRAY], \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::BILLING_CONTACT => ['filter' => \FILTER_SANITIZE_SPECIAL_CHARS, 'flags' => \FILTER_REQUIRE_ARRAY], \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::SHIPPING_METHOD => ['filter' => \FILTER_SANITIZE_SPECIAL_CHARS, 'flags' => \FILTER_REQUIRE_ARRAY], \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::PRODUCT_ID => \FILTER_SANITIZE_NUMBER_INT, \Mollie\WooCommerce\Buttons\ApplePayButton\PropertiesDictionary::PRODUCT_QUANTITY => \FILTER_SANITIZE_NUMBER_INT]);
    }
}
