function createAppleErrors(errors) {
    let errorList = []
    for (let error of errors) {
        let appleError = error.contactField ? new ApplePayError(error.code, error.contactField, error.message) : new ApplePayError(error.code)
        errorList.push(appleError)
    }

    return errorList
}

(
    function ({_, mollieApplePayDirectData, jQuery}) {
        if (_.isEmpty(mollieApplePayDirectData)) {
            return
        }
        let {product: {id, needShipping = true, isVariation = false, price}, shop: {countryCode, currencyCode = 'EUR', totalLabel = ''}, ajaxUrl} = mollieApplePayDirectData

        if (!id || !price || !countryCode || !ajaxUrl) {
            return
        }
        let applePayMethodElement = document.querySelector(
            '#mollie-applepayDirect-button',
        )
        if (window.ApplePaySession || window.ApplePaySession.canMakePayments()) {
            if (!applePayMethodElement) {
                return
            }
            let button = document.createElement("button");
            button.setAttribute('id', 'mollie_applepay_button')
            button.setAttribute('class', 'apple-pay-button apple-pay-button-black')
            applePayMethodElement.appendChild(button)
        }

        let variationIdValue = false
        const nonce = document.getElementById("_wpnonce").value
        let productQuantity = 1
        let updatedContactInfo = []
        let selectedShippingMethod = []
        let redirectionUrl = ''
        jQuery(document).on("change", "input.qty", function () {
            productQuantity = this.value
        })
        if (isVariation) {
            let appleButton = document.querySelector('#mollie_applepay_button');
            jQuery(".single_variation_wrap").on("show_variation", function (event, variation) {
                // Fired when the user selects all the required dropdowns / attributes
                // and a final variation is selected / shown
                if (variation.variation_id) {
                    id = variation.variation_id
                    variationIdValue = true;
                    console.log('id dentro', id)
                }
                appleButton.disabled = false;
                appleButton.classList.remove("buttonDisabled");
            });
            appleButton.disabled = true;
            appleButton.classList.add("buttonDisabled");
        }
        const amountWithoutTax = productQuantity * price
        document.querySelector('#mollie_applepay_button').addEventListener('click', (evt) => {
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
                    amount: amountWithoutTax,
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
                        productId: id,
                        callerPage: 'productDetail',
                        productQuantity: productQuantity,
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
                        productId: id,
                        callerPage: 'productDetail',
                        productQuantity: productQuantity,
                        simplifiedContact: event.shippingContact,
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
                        action: 'mollie_apple_pay_create_order',
                        productId: id,
                        productQuantity: productQuantity,
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
        })
    }

)
(
    window
)



