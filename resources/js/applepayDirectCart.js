import {createAppleErrors} from './applePayError.js';
import {request} from "./applePayRequest";
import {maybeShowButton} from './maybeShowApplePayButton.js';

(
    function ({_, mollieApplePayDirectDataCart, jQuery}) {
        if (_.isEmpty(mollieApplePayDirectDataCart)) {
            return
        }
        const {product: {needShipping = true, subtotal}, shop: {countryCode, currencyCode = 'EUR', totalLabel = ''}, ajaxUrl} = mollieApplePayDirectDataCart

        if (!subtotal || !countryCode || !ajaxUrl) {
            return
        }

        const nonce = document.getElementById("_wpnonce").value

        let updatedContactInfo = []
        let selectedShippingMethod = []
        let redirectionUrl = ''
        let applePaySession = () => {
            const session = new ApplePaySession(3, request(countryCode, currencyCode, totalLabel, subtotal))
            session.begin()
            session.onshippingmethodselected = function (event) {
                jQuery.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mollie_apple_pay_update_shipping_method',
                        shippingMethod: event.shippingMethod,
                        callerPage: 'cart',
                        simplifiedContact: updatedContactInfo,
                        nonce: nonce,
                    },
                    complete: (jqXHR, textStatus) => {
                    },
                    success: (applePayShippingMethodUpdate, textStatus, jqXHR) => {
                        let response = applePayShippingMethodUpdate.data
                        selectedShippingMethod = event.shippingMethod
                        if (applePayShippingMethodUpdate.success === false) {
                            response.errors = createAppleErrors(response.errors)
                        }
                        this.completeShippingMethodSelection(response)
                    },
                    error: (jqXHR, textStatus, errorThrown) => {
                        console.warn(textStatus, errorThrown)
                        session.abort()
                    },
                })
            }
            session.onshippingcontactselected = function (event) {
                jQuery.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mollie_apple_pay_update_shipping_contact',
                        simplifiedContact: event.shippingContact,
                        callerPage: 'cart',
                        needShipping: needShipping,
                        nonce: nonce,
                    },
                    complete: (jqXHR, textStatus) => {
                    },
                    success: (applePayShippingContactUpdate, textStatus, jqXHR) => {
                        let response = applePayShippingContactUpdate.data
                        updatedContactInfo = event.shippingContact
                        if (applePayShippingContactUpdate.success === false) {
                            response.errors = createAppleErrors(response.errors)
                        }
                        if (response.newShippingMethods) {
                            selectedShippingMethod = response.newShippingMethods[0]
                        }
                        this.completeShippingContactSelection(response)
                    },
                    error: (jqXHR, textStatus, errorThrown) => {
                        console.warn(textStatus, errorThrown)
                        session.abort()
                    },
                })
            }
            session.onvalidatemerchant = (applePayValidateMerchantEvent) => {
                jQuery.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mollie_apple_pay_validation',
                        validationUrl: applePayValidateMerchantEvent.validationURL,
                        nonce: nonce,
                    },
                    complete: (jqXHR, textStatus) => {
                    },
                    success: (merchantSession, textStatus, jqXHR) => {
                        if (merchantSession.success === true) {
                            session.completeMerchantValidation(JSON.parse(merchantSession.data))
                        } else {
                            console.warn(merchantSession.data)
                            session.abort()
                        }
                    },
                    error: (jqXHR, textStatus, errorThrown) => {
                        console.warn(textStatus, errorThrown)
                        session.abort()
                    },
                })
            }
            session.onpaymentauthorized = (ApplePayPayment) => {
                jQuery.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mollie_apple_pay_create_order_cart',
                        shippingContact: ApplePayPayment.payment.shippingContact,
                        billingContact: ApplePayPayment.payment.billingContact,
                        token: ApplePayPayment.payment.token,
                        shippingMethod: selectedShippingMethod,
                        'mollie-payments-for-woocommerce_issuer_applepay': 'applepay',
                        nonce: nonce,
                    },
                    complete: (jqXHR, textStatus) => {

                    },
                    success: (authorizationResult, textStatus, jqXHR) => {
                        let result = authorizationResult.data
                        if (authorizationResult.success === true) {
                            redirectionUrl = result['returnUrl'];
                            session.completePayment(result['responseToApple'])
                            window.location.href = redirectionUrl
                        } else {
                            result.errors = createAppleErrors(result.errors)
                            session.completePayment(result)
                        }
                    },
                    error: (jqXHR, textStatus, errorThrown) => {
                        console.warn(textStatus, errorThrown)
                        session.abort()
                    },
                })
            }
        }

        maybeShowButton()

        jQuery(document.body).on('updated_cart_totals', function (event) {
            maybeShowButton()
            document.querySelector('#mollie_applepay_button').addEventListener('click', (evt) => {
                //applePaySession()
            })

        })

        document.querySelector('#mollie_applepay_button').addEventListener('click', (evt) => {
            applePaySession()
        })
    }

)
(
    window
)



