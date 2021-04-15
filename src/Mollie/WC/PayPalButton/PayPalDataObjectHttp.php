<?php

class Mollie_WC_PayPalButton_PayPalDataObjectHttp
{

    /**
     * @var mixed
     */
    public $nonce;
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
     * Set the object with the data relevant to PayPal
     * Required data depends on callerPage
     *
     * @param array $data
     * @param       $callerPage
     */
    public function orderData(array $data, $callerPage)
    {
        $data[Mollie_WC_PayPalButton_PropertiesDictionary::CALLER_PAGE] = $callerPage;
        $this->updateRequiredData(
            $data,
            Mollie_WC_PayPalButton_PropertiesDictionary::CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS,
            Mollie_WC_PayPalButton_PropertiesDictionary::CREATE_ORDER_CART_REQUIRED_FIELDS
        );
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
                    "PayPal Data Error: Missing index {$requiredField}"
                );

                $this->errors[]= ['errorCode' => 'unknown'];
                continue;
            }
            if (!$data[$requiredField]) {
                mollieWooCommerceDebug(
                    "PayPal Data Error: Missing value for {$requiredField}"
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
        $allowedKeys = [
            Mollie_WC_PayPalButton_PropertiesDictionary::NONCE,
            Mollie_WC_PayPalButton_PropertiesDictionary::PRODUCT_QUANTITY,
            Mollie_WC_PayPalButton_PropertiesDictionary::PRODUCT_ID,
            Mollie_WC_PayPalButton_PropertiesDictionary::NEED_SHIPPING
        ];
        foreach ($data as $key => $value) {
            if(in_array($key, $allowedKeys)){
                $filterType = $this->filterType($key);
                $this->$key = filter_var($value, $filterType);
            }
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
            Mollie_WC_PayPalButton_PropertiesDictionary::PRODUCT_QUANTITY,
            Mollie_WC_PayPalButton_PropertiesDictionary::PRODUCT_ID
        ];
        $filterBoolean = [Mollie_WC_PayPalButton_PropertiesDictionary::NEED_SHIPPING];
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
     * @param array $data
     * @param       $requiredProductFields
     * @param       $requiredCartFields
     *
     * @return bool
     */
    protected function updateRequiredData(array $data, $requiredProductFields, $requiredCartFields)
    {
        $this->resetErrors();
        $requiredFields = $requiredProductFields;
        if (isset($data[Mollie_WC_PayPalButton_PropertiesDictionary::CALLER_PAGE])
            && $data[Mollie_WC_PayPalButton_PropertiesDictionary::CALLER_PAGE] == 'cart'
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
}
