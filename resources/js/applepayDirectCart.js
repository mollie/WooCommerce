function createAppleErrors(errors) {
    let errorList = []
    for (let error of errors) {
        let appleError = error.contactField ? new ApplePayError(error.code, error.contactField, error.message) : new ApplePayError(error.code)
        errorList.push(appleError)
    }

    return errorList
}

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
            const request = {
                countryCode: countryCode,
                currencyCode: currencyCode,
                supportedNetworks: ['amex', 'maestro', 'masterCard', 'visa', 'vPay'],
                merchantCapabilities: ['supports3DS'],
                shippingType: "shipping",
                requiredBillingContactFields: [
                    "postalAddress",
                    "email"
                ],
                requiredShippingContactFields: [
                    "postalAddress",
                    "email"
                ],
                total: {
                    label: totalLabel,
                    amount: subtotal,
                    type: "pending"
                },
            }
            const session = new ApplePaySession(3, request)
            session.begin()
            session.onshippingmethodselected = function (event) {
                jQuery.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mollie_apple_pay_update_shipping_method',
                        shippingMethod: event.shippingMethod,
                        callerPage: 'cart',
                        contact: updatedContactInfo,
                        nonce: nonce,
                    },
                    complete: (jqXHR, textStatus) => {
                    },
                    success: (applePayShippingMethodUpdate, textStatus, jqXHR) => {
                        let response = applePayShippingMethodUpdate.data
                        selectedShippingMethod = event.shippingMethod
                        if (applePayShippingMethodUpdate.success === false) {
                            response.errors =  createAppleErrors(response.errors)
                        }
                        this.completeShippingMethodSelection(response)
                    },
                    error: (jqXHR, textStatus, errorThrown) => {
                        console.log(textStatus, errorThrown)
                    },
                })
            }
            session.onshippingcontactselected = function (event) {
                jQuery.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mollie_apple_pay_update_shipping_contact',
                        contact: event.shippingContact,
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
                        console.log(textStatus, errorThrown)
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
                            console.log(merchantSession.data)
                            session.abort()
                        }
                    },
                    error: (jqXHR, textStatus, errorThrown) => {
                        console.log(textStatus, errorThrown)
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
                            makeRedirection()
                        } else {
                            result.errors = createAppleErrors(result.errors)
                            session.completePayment(result)
                        }
                    },
                    error: (jqXHR, textStatus, errorThrown) => {
                        console.log(jqXHR)
                        console.log(textStatus, errorThrown)
                    },
                })
            }
            const makeRedirection = function () {
                window.location.href = redirectionUrl
            }
        }

        let maybeShowButton = () => {
            if (window.ApplePaySession || window.ApplePaySession.canMakePayments()) {
                let applePayMethodElement = document.querySelector(
                    '#mollie-applepayDirect-button',
                )
                if (!applePayMethodElement) {
                    return
                }
                let button = document.createElement("button")
                button.setAttribute('id', 'mollie_applepay_button')
                button.setAttribute('class', 'apple-pay-button apple-pay-button-black')
                applePayMethodElement.appendChild(button)
            }
        }
        maybeShowButton()

        jQuery(document.body).on('updated_cart_totals', function (event) {
            maybeShowButton()
            document.querySelector('#mollie_applepay_button').addEventListener('click', (evt) => {
                applePaySession()
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



