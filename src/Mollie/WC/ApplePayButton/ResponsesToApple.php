<?php

class Mollie_WC_ApplePayButton_ResponsesToApple
{

    /**
     * Returns the authorization response with according success/fail status
     * Adds the error list if provided to be handled by the script
     * On success it adds the redirection url
     *
     * @param        $status 0 => success, 1 => error
     * @param string $orderId
     * @param array  $errorList
     *
     * @return array
     */
    public function authorizationResultResponse(
        $status,
        $orderId = '',
        $errorList = []
    ) {
        $response = [];
        if ($status === 'STATUS_SUCCESS') {
            $response['returnUrl'] = $this->redirectUrlOnSuccessfulPayment(
                $orderId
            );
            $response['responseToApple'] = ['status' => 0];
        } else {
            $response = [
                'status' => 1,
                'errors' => $this->applePayError($errorList)
            ];
        }

        return $response;
    }

    /**
     * Returns an error response to be handled by the script
     *
     * @param array $errorList [['errorCode'=>required, 'contactField'=>'']]
     *
     * @return void
     */
    public function responseWithDataErrors($errorList)
    {
        $response = [];
        $response['errors'] = $this->applePayError($errorList);
        $response['newTotal'] = $this->appleNewTotalResponse(
            0,
            'pending'
        );
        wp_send_json_error($response);
    }

    /**
     * Creates a response formatted for ApplePay
     *
     * @param array $paymentDetails
     *
     * @return array
     */
    public function appleFormattedResponse(array $paymentDetails)
    {
        $response = [];
        if ($paymentDetails['shippingMethods']) {
            $response['newShippingMethods']
                = $paymentDetails['shippingMethods'];
        }

        $response['newLineItems'] = $this->appleNewLineItemsResponse(
            $paymentDetails
        );

        $response['newTotal'] = $this->appleNewTotalResponse(
            $paymentDetails['total']
        );
        return $response;
    }

    /**
     * Returns a success response to be handled by the script
     *
     * @param array $response
     */
    public function responseSuccess(array $response)
    {
        wp_send_json_success($response);
    }

    /**
     * Creates an array of errors formatted
     *
     * @param array $errorList
     * @param array $errors
     *
     * @return array
     */
    protected function applePayError($errorList, $errors = [])
    {
        foreach ($errorList as $error) {
            array_push(
                $errors,
                [
                    "code" => $error['errorCode'],
                    "contactField" => array_key_exists('contactField', $error)
                        ? $error['contactField'] : null,
                    "message" => array_key_exists('contactField', $error)
                        ? "Missing {$error['contactField']}" : "",
                ]
            );
        }

        return $errors;
    }

    /**
     * Creates NewTotals line
     *
     * @param        $total
     *
     * @param string $type
     *
     * @return array
     */
    protected function appleNewTotalResponse($total, $type = 'final')
    {
        return $this->appleItemFormat(
            get_bloginfo('name'),
            $total,
            $type
        );
    }

    /**
     * Creates item line
     *
     * @param $subtotalLabel
     * @param $subtotal
     * @param $type
     *
     * @return array
     */
    protected function appleItemFormat($subtotalLabel, $subtotal, $type)
    {
        return [
            "label" => $subtotalLabel,
            "amount" => $subtotal,
            "type" => $type
        ];
    }

    /**
     * Creates NewLineItems line
     *
     * @param array $paymentDetails
     *
     * @return array[]
     */
    protected function appleNewLineItemsResponse(array $paymentDetails)
    {
        $type = 'final';
        $response = [];
        $response[] =  $this->appleItemFormat(
            'Subtotal',
            $paymentDetails['subtotal'],
            $type
        );

        if ($paymentDetails['shipping']['amount']) {
            $response[]
                = $this->appleItemFormat(
                $paymentDetails['shipping']['label'] ?: '',
                $paymentDetails['shipping']['amount'],
                $type
            );
        }
        $response[]
            = $this->appleItemFormat(
            'Estimated Tax',
            $paymentDetails['taxes'],
            $type

        );
        return $response;
    }

    /**
     * Returns the redirect url to use on successful payment
     *
     * @param $orderId
     *
     * @return string
     */
    protected function redirectUrlOnSuccessfulPayment($orderId)
    {
        $gateway = new Mollie_WC_Gateway_Applepay();
        $order = wc_get_order($orderId);
        $redirect_url = $gateway->getReturnRedirectUrlForOrder($order);
        // Add utm_nooverride query string
        $redirect_url = add_query_arg(['utm_nooverride' => 1], $redirect_url);
        mollieWooCommerceDebug(
            __METHOD__
            . ": Redirect url on return order {$gateway->id}, order {$orderId}: {$redirect_url}"
        );

        return $redirect_url;
    }
}
