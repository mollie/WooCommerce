<?php


use Mollie\Api\Exceptions\ApiException;

class Order
{
    /**
     * @var string
     */
    public $resource;

    /**
     * Id of the order.
     *
     * @example ord_8wmqcHMN4U
     * @var string
     */
    public $id;

    /**
     * The profile ID this order belongs to.
     *
     * @example pfl_xH2kP6Nc6X
     * @var string
     */
    public $profileId;

    /**
     * Either "live" or "test". Indicates this being a test or a live (verified) order.
     *
     * @var string
     */
    public $mode;

    /**
     * Amount object containing the value and currency
     *
     * @var \stdClass
     */
    public $amount;

    /**
     * The total amount captured, thus far.
     *
     * @var \stdClass
     */
    public $amountCaptured;

    /**
     * The total amount refunded, thus far.
     *
     * @var \stdClass
     */
    public $amountRefunded;

    /**
     * The status of the order.
     *
     * @var string
     */
    public $status;

    /**
     * The person and the address the order is billed to.
     *
     * @var \stdClass
     */
    public $billingAddress;

    /**
     * The date of birth of your customer, if available.
     * @example 1976-08-21
     * @var string|null
     */
    public $consumerDateOfBirth;

    /**
     * The order number that was used when creating the order.
     *
     * @var string
     */
    public $orderNumber;

    /**
     * The person and the address the order is billed to.
     *
     * @var \stdClass
     */
    public $shippingAddress;

    /**
     * The payment method last used when paying for the order.
     *
     * @see Method
     * @var string
     */
    public $method;

    /**
     * The locale used for this order.
     *
     * @var string
     */
    public $locale;

    /**
     * During creation of the order you can set custom metadata that is stored with
     * the order, and given back whenever you retrieve that order.
     *
     * @var \stdClass|mixed|null
     */
    public $metadata;

    /**
     * Can this order be canceled?
     *
     * @var bool
     */
    public $isCancelable;

    /**
     * Webhook URL set on this payment
     *
     * @var string|null
     */
    public $webhookUrl;

    /**
     * Redirect URL set on this payment
     *
     * @var string
     */
    public $redirectUrl;

    /**
     * UTC datetime the order was created in ISO-8601 format.
     *
     * @example "2013-12-25T10:30:54+00:00"
     * @var string|null
     */
    public $createdAt;

    /**
     * UTC datetime the order the order will expire in ISO-8601 format.
     *
     * @example "2013-12-25T10:30:54+00:00"
     * @var string|null
     */
    public $expiresAt;

    /**
     * UTC datetime if the order is expired, the time of expiration will be present in ISO-8601 format.
     *
     * @example "2013-12-25T10:30:54+00:00"
     * @var string|null
     */
    public $expiredAt;

    /**
     * UTC datetime if the order has been paid, the time of payment will be present in ISO-8601 format.
     *
     * @example "2013-12-25T10:30:54+00:00"
     * @var string|null
     */
    public $paidAt;

    /**
     * UTC datetime if the order has been authorized, the time of authorization will be present in ISO-8601 format.
     *
     * @example "2013-12-25T10:30:54+00:00"
     * @var string|null
     */
    public $authorizedAt;

    /**
     * UTC datetime if the order has been canceled, the time of cancellation will be present in ISO 8601 format.
     *
     * @example "2013-12-25T10:30:54+00:00"
     * @var string|null
     */
    public $canceledAt;

    /**
     * UTC datetime if the order is completed, the time of completion will be present in ISO 8601 format.
     *
     * @example "2013-12-25T10:30:54+00:00"
     * @var string|null
     */
    public $completedAt;

    /**
     * The order lines contain the actual things the customer bought.
     *
     * @var array|object[]
     */
    public $lines;

    /**
     * An object with several URL objects relevant to the customer. Every URL object will contain an href and a type field.
     *
     * @var \stdClass
     */
    public $_links;

    /**
     * @var \stdClass|null
     */
    public $_embedded;

    /**
     * Is this order created?
     *
     * @return bool
     */
    public function isCreated()
    {
    }

    /**
     * Is this order paid for?
     *
     * @return bool
     */
    public function isPaid()
    {
    }

    /**
     * Is this order authorized?
     *
     * @return bool
     */
    public function isAuthorized()
    {
    }

    /**
     * Is this order canceled?
     *
     * @return bool
     */
    public function isCanceled()
    {
    }

    /**
     * (Deprecated) Is this order refunded?
     * @deprecated 2018-11-27
     *
     * @return bool
     */
    public function isRefunded()
    {
    }

    /**
     * Is this order shipping?
     *
     * @return bool
     */
    public function isShipping()
    {
    }

    /**
     * Is this order completed?
     *
     * @return bool
     */
    public function isCompleted()
    {
    }

    /**
     * Is this order expired?
     *
     * @return bool
     */
    public function isExpired()
    {}

    /**
     * Is this order completed?
     *
     * @return bool
     */
    public function isPending()
    {
    }

    /**
     * Cancels this order.
     * If the order was partially shipped, the status will be "completed" instead of
     * "canceled".
     * Will throw a ApiException if the order id is invalid or the resource cannot
     * be found.
     *
     * @return Order
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function cancel()
    {}

    /**
     * Cancel a line for this order.
     * The data array must contain a lines array.
     * You can pass an empty lines array if you want to cancel all eligible lines.
     * Returns null if successful.
     *
     * @param  array $data
     * @return null
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function cancelLines(array $data)
    {
    }

    /**
     * Cancels all eligible lines for this order.
     * Returns null if successful.
     *
     * @param  array|null $data
     * @return null
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function cancelAllLines($data = [])
    {

    }

    /**
     * Get the line value objects
     *
     * @return OrderLineCollection
     */
    public function lines()
    {
    }

    /**
     * Create a shipment for some order lines. You can provide an empty array for the
     * "lines" option to include all unshipped lines for this order.
     *
     * @param array $options
     *
     * @return Shipment
     * @throws ApiException
     */
    public function createShipment(array $options = [])
    {}

    /**
     * Create a shipment for all unshipped order lines.
     *
     * @param array $options
     *
     * @return Shipment
     */
    public function shipAll(array $options = [])
    {
    }

    /**
     * Retrieve a specific shipment for this order.
     *
     * @param string $shipmentId
     * @param array $parameters
     *
     * @return Shipment
     * @throws ApiException
     */
    public function getShipment($shipmentId, array $parameters = [])
    {
    }

    /**
     * Get all shipments for this order.
     *
     * @param array $parameters
     *
     * @return ShipmentCollection
     * @throws ApiException
     */
    public function shipments(array $parameters = [])
    {
    }

    /**
     * Get the checkout URL where the customer can complete the payment.
     *
     * @return string|null
     */
    public function getCheckoutUrl()
    {
    }

    /**
     * Refund specific order lines.
     *
     * @param  array  $data
     * @return Refund
     * @throws ApiException
     */
    public function refund(array $data)
    {}

    /**
     * Refund all eligible order lines.
     *
     * @param  array  $data
     * @return Refund
     */
    public function refundAll(array $data = [])
    {

    }

    /**
     * Retrieves all refunds associated with this order
     *
     * @return RefundCollection
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function refunds()
    {

    }

    /**
     * Saves the order's updated billingAddress and/or shippingAddress.
     *
     * @return \Mollie\Api\Resources\Order
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function update()
    {}

    /**
     * Create a new payment for this Order.
     *
     * @param array $data
     * @param array $filters
     * @return \Mollie\Api\Resources\Payment
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function createPayment($data, $filters = [])
    {
    }

    /**
     * Retrieve the payments for this order.
     * Requires the order to be retrieved using the embed payments parameter.
     *
     * @return null|\Mollie\Api\Resources\PaymentCollection
     */
    public function payments()
    {
    }

    /**
     * When accessed by oAuth we want to pass the testmode by default
     *
     * @return array
     */
    private function getPresetOptions()
    {
    }

    /**
     * Apply the preset options.
     *
     * @param array $options
     * @return array
     */
    private function withPresetOptions(array $options)
    {
    }
}
