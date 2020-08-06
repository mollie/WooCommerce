<?php

class Mollie_WC_ApplePayButton_ApplePayDataObjectHttp
{

    /**
     * @var mixed
     */
    public $nonce;
    /**
     * @var mixed
     */
    public $validationUrl;
    /**
     * @var mixed
     */
    public $simplifiedContact;
    /**
     * @var mixed|null
     */
    public $needShipping;
    /**
     * @var mixed
     */
    public $productId;
    /**
     * @var mixed
     */
    public $productQuantity;
    /**
     * @var array|mixed
     */
    public $shippingMethod;
    /**
     * @var string[]
     */
    public $billingAddress;
    /**
     * @var string[]
     */
    public $shippingAddress;
    /**
     * @var mixed
     */
    public $callerPage;

    /**
     * @var array
     */
    public $errors = [];

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
     * Set the object with the data relevant to ApplePay validation
     *
     * @param array $data
     */
    public function validationData(array $data)
    {
        $this->resetErrors();
        if (!$this->hasRequiredFieldsValuesOrError(
            $data,
            Mollie_WC_ApplePayButton_PropertiesDictionary::VALIDATION_REQUIRED_FIELDS
        )
        ) {
            return;
        }
        $this->assignDataObjectValues($data);
    }

    /**
     * Set the object with the data relevant to ApplePay on update shipping contact
     * Required data depends on callerPage
     *
     * @param array $data
     */
    public function updateContactData(array $data)
    {
        $result = $this->updateRequiredData(
            $data,
            Mollie_WC_ApplePayButton_PropertiesDictionary::UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS,
            Mollie_WC_ApplePayButton_PropertiesDictionary::UPDATE_CONTACT_CART_REQUIRED_FIELDS
        );
        if (!$result) {
            return;
        }
        $this->updateSimplifiedContact($data[Mollie_WC_ApplePayButton_PropertiesDictionary::SIMPLIFIED_CONTACT]);
    }

    /**
     * Set the object with the data relevant to ApplePay on update shipping method
     * Required data depends on callerPage
     *
     * @param array $data
     */
    public function updateMethodData(array $data)
    {
        $result = $this->updateRequiredData(
            $data,
            Mollie_WC_ApplePayButton_PropertiesDictionary::UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS,
            Mollie_WC_ApplePayButton_PropertiesDictionary::UPDATE_METHOD_CART_REQUIRED_FIELDS
        );
        if (!$result) {
            return;
        }
        $this->updateSimplifiedContact($data[Mollie_WC_ApplePayButton_PropertiesDictionary::SIMPLIFIED_CONTACT]);
        $this->updateShippingMethod($data);
    }

    /**
     * Set the object with the data relevant to ApplePay on authorized order
     * Required data depends on callerPage
     *
     * @param array $data
     * @param       $callerPage
     */
    public function orderData(array $data, $callerPage)
    {
        $data[Mollie_WC_ApplePayButton_PropertiesDictionary::CALLER_PAGE] = $callerPage;
        $result = $this->updateRequiredData(
            $data,
            Mollie_WC_ApplePayButton_PropertiesDictionary::CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS,
            Mollie_WC_ApplePayButton_PropertiesDictionary::CREATE_ORDER_CART_REQUIRED_FIELDS
        );
        if (!$result) {
            return;
        }
        if (!array_key_exists('emailAddress', $data[Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_CONTACT])
            || !$data[Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_CONTACT]['emailAddress']
        ) {
            $this->errors[] =  [
                'errorCode' => Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_CONTACT_INVALID,
                'contactField' => 'emailAddress'
            ];

            return;
        }

        $filteredShippingContact = filter_var_array(
            $data[Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_CONTACT],
            FILTER_SANITIZE_STRING
        );
        $this->shippingAddress = $this->completeAddress(
            $filteredShippingContact,
            Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_CONTACT_INVALID
        );
        $filteredbillingContact = filter_var_array(
            $data[Mollie_WC_ApplePayButton_PropertiesDictionary::BILLING_CONTACT],
            FILTER_SANITIZE_STRING
        );
        $this->billingAddress = $this->completeAddress(
            $filteredbillingContact,
            Mollie_WC_ApplePayButton_PropertiesDictionary::BILLING_CONTACT_INVALID
        );
        $this->updateShippingMethod($data);
    }

    /**
     * Checks if the array contains all required fields and if those
     * are not empty.
     * If not it adds an unkown error to the object's error list, as this errors
     * are not supported by ApplePay
     *
     * @param array $data
     * @param array $required
     *
     * @return bool
     */
    protected function hasRequiredFieldsValuesOrError(array $data, array $required)
    {
        foreach ($required as $requiredField) {
            if (!array_key_exists($requiredField, $data)) {
                mollieWooCommerceDebug(
                    "ApplePay Data Error: Missing index {$requiredField}"
                );

                $this->errors[]= ['errorCode' => 'unknown'];
                continue;
            }
            if (!$data[$requiredField]) {
                mollieWooCommerceDebug(
                    "ApplePay Data Error: Missing value for {$requiredField}"
                );
                $this->errors[]= ['errorCode' => 'unknown'];
                continue;
            }
        }
        return !$this->hasErrors();
    }

    /**
     * Sets the value to the appropriate field in the object
     *
     * @param array $data
     */
    protected function assignDataObjectValues(array $data)
    {
        foreach ($data as $key => $value) {
            $filterType = $this->filterType($value);
            $this->$key = filter_var($value, $filterType);
        }
    }

    /**
     * Selector for the different filters to apply to each field
     * @param $value
     *
     * @return int
     */
    protected function filterType($value)
    {
        $filterInt = [
            Mollie_WC_ApplePayButton_PropertiesDictionary::PRODUCT_QUANTITY,
            Mollie_WC_ApplePayButton_PropertiesDictionary::PRODUCT_ID
        ];
        $filterBoolean = [Mollie_WC_ApplePayButton_PropertiesDictionary::NEED_SHIPPING];
        switch ($value) {
            case in_array($value, $filterInt):
                return FILTER_SANITIZE_NUMBER_INT;
                break;
            case in_array($value, $filterBoolean):
                return FILTER_VALIDATE_BOOLEAN;
                break;
            default:
                return FILTER_SANITIZE_STRING;
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
        $required = [
            'locality' => 'locality',
            'postalCode' => 'postalCode',
            'countryCode' => 'countryCode'
        ];
        if (!$this->addressHasRequiredFieldsValues(
            $contactInfo,
            $required,
            Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_CONTACT_INVALID
        )
        ) {
            return [];
        }
        return [
            'city' => $contactInfo['locality'],
            'postcode' => $contactInfo['postalCode'],
            'country' => strtoupper($contactInfo['countryCode'])
        ];
    }

    /**
     * Checks if the address array contains all required fields and if those
     * are not empty.
     * If not it adds a contacField error to the object's error list
     *
     * @param array  $post      The address to check
     * @param array  $required  The required fields for the given address
     * @param string $errorCode Either shipping or billing kind
     *
     * @return bool
     */
    protected function addressHasRequiredFieldsValues(
        array $post,
        array $required,
        $errorCode
    ) {
        foreach ($required as $requiredField => $errorValue) {
            if (!array_key_exists($requiredField, $post)) {
                mollieWooCommerceDebug(
                    "ApplePay Data Error: Missing index {$requiredField}"
                );

                $this->errors[]= ['errorCode' => 'unknown'];
                continue;
            }
            if (!$post[$requiredField]) {
                mollieWooCommerceDebug(
                    "ApplePay Data Error: Missing value for {$requiredField}"
                );
                $this->errors[]
                    = [
                    'errorCode' => $errorCode,
                    'contactField' => $errorValue
                ];
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
        $required = [
            'givenName' => 'name',
            'familyName' => 'name',
            'addressLines' => 'addressLines',
            'locality' => 'locality',
            'postalCode' => 'postalCode',
            'countryCode' => 'countryCode'
        ];
        if (!$this->addressHasRequiredFieldsValues(
            $data,
            $required,
            $errorCode
        )
        ) {
            return [];
        }
        $filter = FILTER_SANITIZE_STRING;

        return [
            'first_name' => filter_var($data['givenName'], $filter),
            'last_name' => filter_var($data['familyName'], $filter),
            'email' => filter_var($data['emailAddress'], $filter),
            'phone' => filter_var($data['phoneNumber'], $filter),
            'address_1' => isset($data['addressLines'][0])
                ? filter_var($data['addressLines'][0], $filter) : '',
            'address_2' => isset($data['addressLines'][1])
                ? filter_var($data['addressLines'][1], $filter) : '',
            'city' => filter_var($data['locality'], $filter),
            'state' => filter_var($data['administrativeArea'], $filter),
            'postcode' => filter_var($data['postalCode'], $filter),
            'country' => strtoupper(
                filter_var($data['countryCode'], $filter)
            )
        ];
    }

    /**
     * @param array $data
     * @param       $requiredProductFields
     * @param       $requiredCartFields
     */
    protected function updateRequiredData(array $data, $requiredProductFields, $requiredCartFields)
    {
        $this->resetErrors();
        $requiredFields = $requiredProductFields;
        if (isset($data[Mollie_WC_ApplePayButton_PropertiesDictionary::CALLER_PAGE])
            && $data[Mollie_WC_ApplePayButton_PropertiesDictionary::CALLER_PAGE] == 'cart'
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
     * @param $data
     */
    protected function updateSimplifiedContact($data)
    {
        $simplifiedContactInfo = filter_var_array(
            $data,
            FILTER_SANITIZE_STRING
        );
        $this->simplifiedContact = $this->simplifiedAddress(
            $simplifiedContactInfo
        );
    }

    /**
     * @param array $data
     */
    protected function updateShippingMethod(array $data)
    {
        if (array_key_exists(
            Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_METHOD, $data)) {
            $this->shippingMethod = filter_var_array(
                $data[Mollie_WC_ApplePayButton_PropertiesDictionary::SHIPPING_METHOD],
                FILTER_SANITIZE_STRING
            );
        }
    }
}
