<?php

class Mollie_WC_Helper_ApplePayDataObjectHttp
{
    const VALIDATION_REQUIRED_FIELDS = ['nonce', 'validationUrl'];
    const UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS
        = [
            'nonce',
            'productId',
            'productQuantity',
            'callerPage',
            'simplifiedContact',
            'needShipping'
        ];
    const UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS
        = [
            'nonce',
            'productId',
            'productQuantity',
            'shippingMethod',
            'callerPage',
            'simplifiedContact'
        ];
    const UPDATE_CONTACT_CART_REQUIRED_FIELDS
        = [
            'nonce',
            'callerPage',
            'simplifiedContact',
            'needShipping'
        ];
    const UPDATE_METHOD_CART_REQUIRED_FIELDS
        = [
            'nonce',
            'shippingMethod',
            'callerPage',
            'simplifiedContact'
        ];
    const CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS
        = [
            'nonce',
            'productId',
            'productQuantity',
            'shippingMethod',
            'billingContact'
        ];
    const CREATE_ORDER_CART_REQUIRED_FIELDS
        = [
            'nonce',
            'shippingMethod',
            'billingContact',
            'shippingContact'
        ];
    const SHIPPING_CONTACT_INVALID = 'shipping Contact Invalid';
    const BILLING_CONTACT_INVALID = 'billing Contact Invalid';

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
     * @param array $post
     */
    public function validationData(array $post)
    {
        $this->resetErrors();
        if (!$this->hasRequiredFieldsValues(
            $post,
            self::VALIDATION_REQUIRED_FIELDS
        )
        ) {
            return;
        }
        $this->assignDataObjectValues($post);
    }

    /**
     * Set the object with the data relevant to ApplePay on update shipping contact
     * Required data depends on callerPage
     * @param array $post
     */
    public function updateContactData(array $post)
    {
        $this->resetErrors();
        $requiredFields = self::UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS;
        if (array_key_exists('callerPage', $post)
            && $post['callerPage'] == 'cart'
        ) {
            $requiredFields = self::UPDATE_CONTACT_CART_REQUIRED_FIELDS;
        }
        if (!$this->hasRequiredFieldsValues(
            $post,
            $requiredFields
        )
        ) {
            return;
        }
        $this->assignDataObjectValues($post);
        $this->simplifiedContact = $this->simplifiedAddress(
            filter_var_array(
                $post['simplifiedContact'],
                FILTER_SANITIZE_STRING
            )
        );
    }

    /**
     * Set the object with the data relevant to ApplePay on update shipping method
     * Required data depends on callerPage
     *
     * @param array $post
     */
    public function updateMethodData(array $post)
    {
        $this->resetErrors();
        $requiredFields = self::UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS;

        if (array_key_exists('callerPage', $post)
            && $post['callerPage'] == 'cart'
        ) {
            $requiredFields = self::UPDATE_METHOD_CART_REQUIRED_FIELDS;
        }
        if (!$this->hasRequiredFieldsValues(
            $post,
            $requiredFields
        )
        ) {
            return;
        }
        $this->assignDataObjectValues($post);

        $this->simplifiedContact = $this->simplifiedAddress(
            filter_var_array(
                $post['simplifiedContact'],
                FILTER_SANITIZE_STRING
            )
        );
        $this->shippingMethod = filter_var_array(
            $post['shippingMethod'],
            FILTER_SANITIZE_STRING
        );
    }

    /**
     * Set the object with the data relevant to ApplePay on authorized order
     * Required data depends on callerPage
     *
     * @param array $post
     * @param       $callerPage
     */
    public function orderData(array $post, $callerPage)
    {
        $this->resetErrors();
        $requiredFields = self::CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS;
        if ($callerPage == 'cart') {
            $requiredFields = self::CREATE_ORDER_CART_REQUIRED_FIELDS;
        }
        if (!$this->hasRequiredFieldsValues(
            $post,
            $requiredFields
        )
        ) {
            return;
        }

        $this->assignDataObjectValues($post);


        if (!array_key_exists('emailAddress', $post['shippingContact'])
            || !$post['shippingContact']['emailAddress']
        ) {
            array_push(
                $this->errors,
                [
                    'errorCode' => self::SHIPPING_CONTACT_INVALID,
                    'contactField' => 'emailAddress'
                ]
            );
            return;
        }

        $this->shippingAddress = $this->completeAddress(
            filter_var_array(
                $post['shippingContact'],
                FILTER_SANITIZE_STRING
            ),
            self::SHIPPING_CONTACT_INVALID
        );
        $this->billingAddress = $this->completeAddress(
            filter_var_array(
                $post['billingContact'],
                FILTER_SANITIZE_STRING
            ),
            self::BILLING_CONTACT_INVALID
        );
        $this->shippingMethod = filter_var_array(
            $post['shippingMethod'],
            FILTER_SANITIZE_STRING
        );
    }

    /**
     * Checks if the array contains all required fields and if those
     * are not empty.
     * If not it adds an unkown error to the object's error list, as this errors
     * are not supported by ApplePay
     * @param array $post
     * @param array $required
     *
     * @return bool
     */
    protected function hasRequiredFieldsValues(array $post, array $required)
    {
        foreach ($required as $requiredField) {
            if (!array_key_exists($requiredField, $post)) {
                mollieWooCommerceDebug(
                    "ApplePay Data Error: Missing index {$requiredField}"
                );

                array_push($this->errors, ['errorCode' => 'unknown']);
                continue;
            }
            if (!$post[$requiredField]) {
                mollieWooCommerceDebug(
                    "ApplePay Data Error: Missing value for {$requiredField}"
                );
                array_push($this->errors, ['errorCode' => 'unknown']);
                continue;
            }
        }
        return !$this->hasErrors();
    }

    /**
     * Sets the value to the appropriate field in the object
     * @param array $post
     */
    protected function assignDataObjectValues(array $post)
    {
        foreach ($post as $key => $value) {
            $filterType = $this->filterType($value);
            $this->$key = $this->filterValue($value, $filterType);
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
        $filterInt = ['productQuantity', 'productId'];
        $filterBoolean = ['needShipping'];
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
     * Filter the input value with the given filter
     * @param $value
     * @param $filter
     *
     * @return mixed
     */
    protected function filterValue($value, $filter)
    {
        return filter_var($value, $filter);
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
            self::SHIPPING_CONTACT_INVALID
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

                array_push($this->errors, ['errorCode' => 'unknown']);
                continue;
            }
            if (!$post[$requiredField]) {
                mollieWooCommerceDebug(
                    "ApplePay Data Error: Missing value for {$requiredField}"
                );

                array_push(
                    $this->errors,
                    [
                        'errorCode' => $errorCode,
                        'contactField' => $errorValue
                    ]
                );
                continue;
            }
        }
        return !$this->hasErrors();
    }

    /**
     * Returns the address details for after authorization steps
     *
     * @param array $post
     *
     * @param string $errorCode differentiates between billing and shipping information
     *
     * @return string[]
     */
    protected function completeAddress($post, $errorCode)
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
            $post,
            $required,
            $errorCode
        )
        ) {
            return [];
        }


        return [
            'first_name' => $this->filterArrayValue('givenName', $post),
            'last_name' => $this->filterArrayValue('familyName', $post),
            'email' => $this->filterArrayValue('emailAddress', $post),
            'phone' => $this->filterArrayValue('phoneNumber', $post),
            'address_1' => isset($post['addressLines'][0])
                ? $post['addressLines'][0] : '',
            'address_2' => isset($post['addressLines'][1])
                ? $post['addressLines'][1] : '',
            'city' => $this->filterArrayValue('locality', $post),
            'state' => $this->filterArrayValue('administrativeArea', $post),
            'postcode' => $this->filterArrayValue('postalCode', $post),
            'country' => strtoupper(
                $this->filterArrayValue('countryCode', $post)
            )
        ];
    }

    /**
     * Filters the string fields by $post[$key]
     *
     * @param $key
     * @param $post
     *
     * @return mixed
     */
    protected function filterArrayValue($key, $post)
    {
        return filter_var($post[$key], FILTER_SANITIZE_STRING);
    }
}
