<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Buttons\PayPalButton;

use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel;

class PayPalDataObjectHttp
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
     * @var Logger
     */
    protected $logger;

    /**
     * PayPalDataObjectHttp constructor.
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
     * Set the object with the data relevant to PayPal
     * Required data depends on callerPage
     *
     * @param       $callerPage
     */
    public function orderData(array $data, $callerPage)
    {
        $data[PropertiesDictionary::CALLER_PAGE] = $callerPage;
        $this->updateRequiredData(
            $data,
            PropertiesDictionary::CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS,
            PropertiesDictionary::CREATE_ORDER_CART_REQUIRED_FIELDS
        );
    }

    /**
     * Checks if the array contains all required fields and if those
     * are not empty.
     * If not it adds an unkown error to the object's error list, as this errors
     * are not supported by ApplePay
     *
     *
     * @return bool
     */
    protected function hasRequiredFieldsValuesOrError(array $data, array $required)
    {
        foreach ($required as $requiredField) {
            if (!array_key_exists($requiredField, $data)) {
                $this->logger->debug(
                    sprintf('PayPal Data Error: Missing index %s', $requiredField)
                );

                $this->errors[] = ['errorCode' => 'unknown'];
                continue;
            }
            if (!$data[$requiredField]) {
                $this->logger->debug(
                    sprintf('PayPal Data Error: Missing value for %s', $requiredField)
                );
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
        $allowedKeys = [
            PropertiesDictionary::NONCE,
            PropertiesDictionary::PRODUCT_QUANTITY,
            PropertiesDictionary::PRODUCT_ID,
            PropertiesDictionary::NEED_SHIPPING,
        ];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedKeys)) {
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
            PropertiesDictionary::PRODUCT_QUANTITY,
            PropertiesDictionary::PRODUCT_ID,
        ];
        $filterBoolean = [PropertiesDictionary::NEED_SHIPPING];
        switch ($value) {
            case in_array($value, $filterInt):
                return FILTER_SANITIZE_NUMBER_INT;
            case in_array($value, $filterBoolean):
                return FILTER_VALIDATE_BOOLEAN;
            default:
                return FILTER_SANITIZE_STRING;
        }
    }

    /**
     * @param       $requiredProductFields
     * @param       $requiredCartFields
     * @return bool
     */
    protected function updateRequiredData(array $data, $requiredProductFields, $requiredCartFields)
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
}
