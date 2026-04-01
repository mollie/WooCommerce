<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Buttons\ApplePayButton;

use Mollie\WooCommerce\Gateway\MolliePaymentGatewayI;
use Mollie\Psr\Log\LoggerInterface as Logger;
class ResponsesToApple
{
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var mixed
     */
    protected $deprecatedAppleHelper;
    /**
     * ResponsesToApple constructor.
     *
     * @param mixed $deprecatedAppleHelper
     */
    public function __construct(Logger $logger, $deprecatedAppleHelper)
    {
        $this->logger = $logger;
        $this->deprecatedAppleHelper = $deprecatedAppleHelper;
    }
    /**
     * Returns the authorization response with according success/fail status
     * Adds the error list if provided to be handled by the script
     * On success it adds the redirection url
     *
     * @param string $status 0 => success, 1 => error
     * @param string $orderId
     * @param array<mixed>  $errorList
     *
     * @return array<mixed>
     */
    public function authorizationResultResponse(string $status, string $orderId = '', array $errorList = []): array
    {
        $response = [];
        if ($status === 'STATUS_SUCCESS') {
            $response['returnUrl'] = $this->redirectUrlOnSuccessfulPayment($orderId);
            $response['responseToApple'] = ['status' => 0];
        } else {
            $response = ['status' => 1, 'errors' => $this->applePayError($errorList)];
        }
        return $response;
    }
    /**
     * Returns an error response to be handled by the script
     *
     * @param array<mixed> $errorList [['errorCode'=>required, 'contactField'=>'']]
     *
     * @return void
     */
    public function responseWithDataErrors(array $errorList): void
    {
        $response = [];
        $response['errors'] = $this->applePayError($errorList);
        $response['newTotal'] = $this->appleNewTotalResponse(0, 'pending');
        wp_send_json_error($response);
    }
    /**
     * Creates a response formatted for ApplePay
     *
     * @param array<mixed> $paymentDetails
     * @param ApplePayDataObjectHttp $applePayRequestDataObject
     * @return array<mixed>
     */
    public function appleFormattedResponse(array $paymentDetails, \Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDataObjectHttp $applePayRequestDataObject): array
    {
        $response = [];
        if ($paymentDetails['shippingMethods']) {
            $selectedShippingMethod = $applePayRequestDataObject->shippingMethod();
            $response['newShippingMethods'] = $this->reorderShippingMethods($paymentDetails['shippingMethods'], $selectedShippingMethod);
        }
        $response['newLineItems'] = $this->appleNewLineItemsResponse($paymentDetails);
        $response['newTotal'] = $this->appleNewTotalResponse($paymentDetails['total']);
        return $response;
    }
    /**
     * Reorders the shipping methods to have the selected shipping method on top so we see it as selected
     * @param array $methods
     * @param array $selectedShippingMethod
     * @return array
     */
    private function reorderShippingMethods(array $methods, array $selectedShippingMethod): array
    {
        $reordered_methods = [];
        foreach ($methods as $key => $method) {
            if ($method['identifier'] === $selectedShippingMethod['identifier']) {
                $reordered_methods[] = $method;
                unset($methods[$key]);
                break;
            }
        }
        return array_merge($reordered_methods, array_values($methods));
    }
    /**
     * Returns a success response to be handled by the script
     *
     * @param array<mixed> $response
     */
    public function responseSuccess(array $response): void
    {
        wp_send_json_success($response);
    }
    /**
     * Creates an array of errors formatted
     *
     * @param array<mixed> $errorList
     * @param array<mixed> $errors
     *
     * @return array<mixed>
     */
    protected function applePayError(array $errorList, array $errors = []): array
    {
        foreach ($errorList as $error) {
            $errors[] = ["code" => $error['errorCode'], "contactField" => $error['contactField'] ?? null, "message" => array_key_exists('contactField', $error) ? sprintf('Missing %s', $error['contactField']) : ""];
        }
        return $errors;
    }
    /**
     * Creates NewTotals line
     *
     * @param mixed $total
     * @param string $type
     *
     * @return array<mixed>
     */
    protected function appleNewTotalResponse($total, string $type = 'final'): array
    {
        return $this->appleItemFormat(get_bloginfo('name'), $total, $type);
    }
    /**
     * Creates item line
     *
     * @param mixed $subtotalLabel
     * @param mixed $subtotal
     * @param mixed $type
     *
     * @return array<mixed>
     */
    protected function appleItemFormat($subtotalLabel, $subtotal, $type): array
    {
        return ["label" => $subtotalLabel, "amount" => $subtotal, "type" => $type];
    }
    /**
     * Creates NewLineItems line
     *
     * @param array<mixed> $paymentDetails
     * @return array<mixed>
     */
    protected function appleNewLineItemsResponse(array $paymentDetails): array
    {
        $type = 'final';
        $response = [];
        $response[] = $this->appleItemFormat('Subtotal', round(floatval($paymentDetails['subtotal']), 2), $type);
        if ($paymentDetails['shipping']['amount']) {
            $response[] = $this->appleItemFormat($paymentDetails['shipping']['label'] ?: '', round(floatval($paymentDetails['shipping']['amount']), 2), $type);
        }
        $issetFeeAmount = isset($paymentDetails['fee']) && isset($paymentDetails['fee']['amount']);
        if ($issetFeeAmount) {
            $response[] = $this->appleItemFormat($paymentDetails['fee']['label'] ?: '', round(floatval($paymentDetails['fee']['amount']), 2), $type);
        }
        $response[] = $this->appleItemFormat('Estimated Tax', round(floatval($paymentDetails['taxes']), 2), $type);
        return $response;
    }
    /**
     * Returns the redirect url to use on successful payment
     *
     * @param string $orderId
     *
     * @return string
     */
    protected function redirectUrlOnSuccessfulPayment(string $orderId): string
    {
        $order = wc_get_order($orderId);
        $redirect_url = $this->deprecatedAppleHelper->getReturnRedirectUrlForOrder($order);
        // Add utm_nooverride query string
        $redirect_url = add_query_arg(['utm_nooverride' => 1], $redirect_url);
        $this->logger->debug(__METHOD__ . sprintf(': Redirect url on return order %s, order %s: %s', $this->deprecatedAppleHelper->paymentMethod()->getProperty('id'), $orderId, $redirect_url));
        return $redirect_url;
    }
}
