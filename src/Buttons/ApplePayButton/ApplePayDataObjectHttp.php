<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\ApplePayButton;

use Psr\Log\LoggerInterface as Logger;

/**
 * @phpcs:disable Inpsyde.CodeQuality.PropertyPerClassLimit.TooManyProperties
 */
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
     * @var array<mixed>
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
     * @var mixed
     */
    public $callerPage;
    /**
     * @var array<mixed>
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
    protected function resetErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Returns if the object has any errors
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Returns errors
     * @return array<mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Set the object with the data relevant to ApplePay validation
     */
    public function validationData(): void
    {
        if (!$this->isNonceValid()) {
            return;
        }
        $data = $this->getFilteredRequestData();
        if (!is_array($data)) {
            return;
        }

        $this->resetErrors();
        if (
            !$this->hasRequiredFieldsValuesOrError(
                $data,
                PropertiesDictionary::VALIDATION_REQUIRED_FIELDS
            )
        ) {
            return;
        }
        $this->assignDataObjectValues($data);
    }

    /**
     * Set the object with the data relevant to ApplePay on update shipping contact
     * Required data depends on callerPage
     */
    public function updateContactData(): void
    {
        if (!$this->isNonceValid()) {
            return;
        }
        $data = $this->getFilteredRequestData();
        if (!is_array($data)) {
            return;
        }

        $result = $this->updateRequiredData(
            $data,
            PropertiesDictionary::UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS,
            PropertiesDictionary::UPDATE_CONTACT_CART_REQUIRED_FIELDS
        );
        if (!$result) {
            return;
        }
        $simplifiedContact = $data[PropertiesDictionary::SIMPLIFIED_CONTACT];
        if (is_array($simplifiedContact)) {
            $this->updateSimplifiedContact($simplifiedContact);
        }
    }

    /**
     * Set the object with the data relevant to ApplePay on update shipping method
     * Required data depends on callerPage
     */
    public function updateMethodData(): void
    {
        if (!$this->isNonceValid()) {
            return;
        }

        $data = $this->getFilteredRequestData();
        if (!is_array($data)) {
            return;
        }

        $result = $this->updateRequiredData(
            $data,
            PropertiesDictionary::UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS,
            PropertiesDictionary::UPDATE_METHOD_CART_REQUIRED_FIELDS
        );
        if (!$result) {
            return;
        }
        $simplifiedContact = $data[PropertiesDictionary::SIMPLIFIED_CONTACT];
        if (is_array($simplifiedContact)) {
            $this->updateSimplifiedContact($simplifiedContact);
        }
        $this->updateShippingMethod($data);
    }

    /**
     * Set the object with the data relevant to ApplePay on authorized order
     * Required data depends on callerPage
     *
     * @param string $callerPage
     */
    public function orderData(string $callerPage): void
    {
        if (!$this->isNonceValid()) {
            return;
        }
        $data = $this->getFilteredRequestData();
        if (!is_array($data)) {
            return;
        }
        $data[PropertiesDictionary::CALLER_PAGE] = $callerPage;
        $result = $this->updateRequiredData(
            $data,
            PropertiesDictionary::CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS,
            PropertiesDictionary::CREATE_ORDER_CART_REQUIRED_FIELDS
        );
        if (!$result) {
            return;
        }
        $shippingContact = $data[PropertiesDictionary::SHIPPING_CONTACT];
        if (
            !is_array($shippingContact)
            || !array_key_exists('emailAddress', $shippingContact)
            || !$shippingContact['emailAddress']
        ) {
            $this->errors[] = [
                'errorCode' => PropertiesDictionary::SHIPPING_CONTACT_INVALID,
                'contactField' => 'emailAddress',
            ];

            return;
        }

        $this->shippingAddress = $this->completeAddress(
            $shippingContact,
            PropertiesDictionary::SHIPPING_CONTACT_INVALID
        );
        $filteredbillingContact = $data[PropertiesDictionary::BILLING_CONTACT];
        if (is_array($filteredbillingContact)) {
            $this->billingAddress = $this->completeAddress(
                $filteredbillingContact,
                PropertiesDictionary::BILLING_CONTACT_INVALID
            );
        }
        $this->updateShippingMethod($data);
    }

    /**
     * Checks if the array contains all required fields and if those
     * are not empty.
     * If not it adds an unknown error to the object's error list, as this errors
     * are not supported by ApplePay
     *
     * @param array<mixed> $data
     * @param array<mixed> $required
     * @return bool
     */
    protected function hasRequiredFieldsValuesOrError(array $data, array $required): bool
    {
        foreach ($required as $requiredField) {
            if (!array_key_exists($requiredField, $data)) {
                $this->logger->debug(
                    sprintf('ApplePay Data Error: Missing index %s', $requiredField)
                );

                $this->errors[] = ['errorCode' => 'unknown'];
                continue;
            }
            if ($data[$requiredField] === null || $data[$requiredField] === '') {
                $this->logger->debug(
                    sprintf('ApplePay Data Error: Missing value for %s', $requiredField)
                );
                $this->errors[] = ['errorCode' => 'unknown'];
                continue;
            }
        }
        return !$this->hasErrors();
    }

    /**
     * Sets the value to the appropriate field in the object
     *
     * @param array<mixed> $data
     */
    protected function assignDataObjectValues(array $data): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'woocommerce-process-checkout-nonce') {
                $key = 'nonce';
            }
            $this->$key = $value;
        }
    }

    /**
     * Returns the address details used in pre-authorization steps
     *
     * @param array<mixed> $contactInfo
     * @return array<string, string>
     */
    protected function simplifiedAddress(array $contactInfo): array
    {
        $required = [
            'locality' => 'locality',
            'postalCode' => 'postalCode',
            'countryCode' => 'countryCode',
        ];
        if (
            !$this->addressHasRequiredFieldsValues(
                $contactInfo,
                $required,
                PropertiesDictionary::SHIPPING_CONTACT_INVALID
            )
        ) {
            return [];
        }
        return [
            'city' => (string) $contactInfo['locality'],
            'postcode' => (string) $contactInfo['postalCode'],
            'country' => strtoupper((string) $contactInfo['countryCode']),
        ];
    }

    /**
     * Checks if the address array contains all required fields and if those
     * are not empty.
     * If not it adds a contactField error to the object's error list
     *
     * @param array<mixed> $post The address to check
     * @param array<mixed> $required The required fields for the given address
     * @param string $errorCode Either shipping or billing kind
     *
     * @return bool
     */
    protected function addressHasRequiredFieldsValues(
        array $post,
        array $required,
        string $errorCode
    ): bool {

        foreach ($required as $requiredField => $errorValue) {
            if (!array_key_exists($requiredField, $post)) {
                $this->logger->debug(
                    sprintf('ApplePay Data Error: Missing index %s', $requiredField)
                );

                $this->errors[] = ['errorCode' => 'unknown'];
                continue;
            }
            if (!$post[$requiredField]) {
                $this->logger->debug(
                    sprintf('ApplePay Data Error: Missing value for %s', $requiredField)
                );
                $this->errors[] = [
                    'errorCode' => $errorCode,
                    'contactField' => $errorValue,
                ];
                continue;
            }
        }
        return !$this->hasErrors();
    }

    /**
     * Returns the address details for after authorization steps
     *
     * @param array<mixed> $data
     * @param string $errorCode differentiates between billing and shipping information
     * @return array<string, string>
     */
    protected function completeAddress(array $data, string $errorCode): array
    {
        $required = [
            'givenName' => 'name',
            'familyName' => 'name',
            'addressLines' => 'addressLines',
            'locality' => 'locality',
            'postalCode' => 'postalCode',
            'countryCode' => 'countryCode',
        ];
        if (
            !$this->addressHasRequiredFieldsValues(
                $data,
                $required,
                $errorCode
            )
        ) {
            return [];
        }

        $addressLines = is_array($data['addressLines'] ?? null) ? $data['addressLines'] : [];
        return [
            'first_name' => (string) $data['givenName'],
            'last_name' => (string) $data['familyName'],
            'email' => (string) ($data['emailAddress'] ?? ''),
            'phone' => (string) ($data['phoneNumber'] ?? ''),
            'address_1' => (string) ($addressLines[0] ?? ''),
            'address_2' => (string) ($addressLines[1] ?? ''),
            'city' => (string) $data['locality'],
            'state' => (string) ($data['administrativeArea'] ?? ''),
            'postcode' => (string) $data['postalCode'],
            'country' => strtoupper((string) $data['countryCode']),
        ];
    }

    /**
     * @param array<mixed> $data
     * @param array<mixed> $requiredProductFields
     * @param array<mixed> $requiredCartFields
     * @return bool
     */
    protected function updateRequiredData(array $data, array $requiredProductFields, array $requiredCartFields): bool
    {
        $this->resetErrors();
        $requiredFields = $requiredProductFields;
        if (
            isset($data[PropertiesDictionary::CALLER_PAGE])
            && $data[PropertiesDictionary::CALLER_PAGE] === 'cart'
        ) {
            $requiredFields = $requiredCartFields;
        }
        $hasRequiredFieldsValues = $this->hasRequiredFieldsValuesOrError(
            $data,
            $requiredFields
        );
        if (!$hasRequiredFieldsValues) {
            return false;
        }
        $this->assignDataObjectValues($data);
        return true;
    }

    /**
     * @param array<mixed> $data
     */
    protected function updateSimplifiedContact(array $data): void
    {
        $simplifiedContactInfo = array_map('sanitize_text_field', $data);
        $this->simplifiedContact = $this->simplifiedAddress(
            $simplifiedContactInfo
        );
    }

    /**
     * @param array<mixed> $data
     */
    protected function updateShippingMethod(array $data): void
    {
        if (
            array_key_exists(
                PropertiesDictionary::SHIPPING_METHOD,
                $data
            )
        ) {
            $shippingMethodData = $data[PropertiesDictionary::SHIPPING_METHOD];
            if (!is_array($shippingMethodData)) {
                return;
            }
            $filtered = filter_var_array(
                $shippingMethodData,
                FILTER_SANITIZE_SPECIAL_CHARS
            );
            if (is_array($filtered)) {
                $this->shippingMethod = $filtered;
            }
        }
    }

    /**
     * @return array<mixed>
     */
    public function billingAddress(): array
    {
        return $this->billingAddress;
    }

    /**
     * @return array<mixed>
     */
    public function shippingAddress(): array
    {
        return $this->shippingAddress;
    }

    /**
     * @return array<mixed>
     */
    public function shippingMethod(): array
    {
        return $this->shippingMethod;
    }

    public function needShipping(): bool
    {
        return (bool) $this->needShipping;
    }

    /**
     * @return mixed
     */
    public function productId()
    {
        return $this->productId;
    }

    /**
     * @return mixed
     */
    public function productQuantity()
    {
        return $this->productQuantity;
    }

    /**
     * @return mixed
     */
    public function nonce()
    {
        return $this->nonce;
    }

    /**
     * @return mixed
     */
    public function validationUrl()
    {
        return $this->validationUrl;
    }

    /**
     * @return mixed
     */
    public function simplifiedContact()
    {
        return $this->simplifiedContact;
    }

    /**
     * @return bool
     */
    public function isNonceValid(): bool
    {
        $nonce = filter_input(INPUT_POST, 'woocommerce-process-checkout-nonce', FILTER_SANITIZE_SPECIAL_CHARS);

        return (bool) wp_verify_nonce(
            $nonce,
            'woocommerce-process_checkout'
        ) || (bool) wp_verify_nonce($nonce, 'mollie_apple_pay_blocks');
    }

    /**
     * @return array<mixed>|false|null
     */
    public function getFilteredRequestData()
    {
        return filter_input_array(INPUT_POST, [
            PropertiesDictionary::CALLER_PAGE => FILTER_SANITIZE_SPECIAL_CHARS,
            PropertiesDictionary::VALIDATION_URL => FILTER_SANITIZE_SPECIAL_CHARS,
            'woocommerce-process-checkout-nonce' => FILTER_SANITIZE_SPECIAL_CHARS,
            PropertiesDictionary::NEED_SHIPPING => FILTER_VALIDATE_BOOLEAN,
            PropertiesDictionary::SIMPLIFIED_CONTACT => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY,
            ],
            PropertiesDictionary::SHIPPING_CONTACT => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY,
            ],
            PropertiesDictionary::BILLING_CONTACT => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY,
            ],
            PropertiesDictionary::SHIPPING_METHOD => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_REQUIRE_ARRAY,
            ],
            PropertiesDictionary::PRODUCT_ID => FILTER_SANITIZE_NUMBER_INT,
            PropertiesDictionary::PRODUCT_QUANTITY => FILTER_SANITIZE_NUMBER_INT,
        ]);
    }
}
