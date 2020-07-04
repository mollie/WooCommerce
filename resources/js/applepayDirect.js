import {createAppleErrors} from './applePayError.js';
import {maybeShowButton} from './maybeShowApplePayButton.js';
import {request} from './applePayRequest.js';

(
    function ({_, mollieApplePayDirectData, jQuery}) {
        if (_.isEmpty(mollieApplePayDirectData)) {
            return
        }
        const {product: {id, needShipping = true, isVariation = false, price}, shop: {countryCode, currencyCode = 'EUR', totalLabel = ''}, ajaxUrl} = mollieApplePayDirectData

        if (!id || !price || !countryCode || !ajaxUrl) {
            return
        }
        maybeShowButton()

        const nonce = document.getElementById('_wpnonce').value
        let productId = id
        let productQuantity = 1
        let updatedContactInfo = []
        let selectedShippingMethod = []
        let redirectionUrl = ''
        document.querySelector('input.qty').addEventListener('change', event => {
            productQuantity = event.currentTarget.value
        })

        if (isVariation) {
            let appleButton = document.querySelector('#mollie_applepay_button');
            jQuery('.single_variation_wrap').on('show_variation', function (event, variation) {
                // Fired when the user selects all the required dropdowns / attributes
                // and a final variation is selected / shown
                if (variation.variation_id) {
                    productId = variation.variation_id
                }
                appleButton.disabled = false;
                appleButton.classList.remove("buttonDisabled");
            });
            appleButton.disabled = true;
            appleButton.classList.add("buttonDisabled");
        }
        const amountWithoutTax = productQuantity * price
        document.querySelector('#mollie_applepay_button').addEventListener('click', (evt) => {
            let shippingContact = {'locality' : 'Como',
                'postalCode' : '22100',
                'countryCode' : 'IT'
            }
            let shippingMethod = {'label' : "Flat Rate Peso",
                'detail' : "",
                'amount' : "0.00",
                'identifier' : "flat_rate:1"}
console.log(shippingContact)
            jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mollie_apple_pay_update_shipping_contact',
                    productId: id,
                    shippingMethod: shippingMethod,
                    callerPage: 'productDetail',
                    productQuantity: productQuantity,
                    simplifiedContact: shippingContact,
                    needShipping: needShipping,
                    nonce: nonce,
                },
                complete: (jqXHR, textStatus) => {
                },
                success: (applePayShippingContactUpdate, textStatus, jqXHR) => {
                    let response = applePayShippingContactUpdate.data
                    updatedContactInfo = shippingContact
                    if (applePayShippingContactUpdate.success === false) {
                        response.errors = createAppleErrors(response.errors)
                    }
                    if (response.newShippingMethods) {
                        selectedShippingMethod = response.newShippingMethods[0]
                    }
                    console.log(response)
                    method()
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.warn(textStatus, errorThrown)

                },
            })
            const method = ()=>{
                jQuery.ajax({
                    url: ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'mollie_apple_pay_update_shipping_method',
                        productId: id,
                        shippingMethod: shippingMethod,
                        callerPage: 'productDetail',
                        productQuantity: productQuantity,
                        simplifiedContact: shippingContact,
                        needShipping: needShipping,
                        nonce: nonce,
                    },
                    complete: (jqXHR, textStatus) => {
                    },
                    success: (applePayShippingContactUpdate, textStatus, jqXHR) => {
                        console.log('en el segundo')
                        let response = applePayShippingContactUpdate.data
                        updatedContactInfo = shippingContact
                        if (applePayShippingContactUpdate.success === false) {
                            response.errors = createAppleErrors(response.errors)
                        }
                        if (response.newShippingMethods) {
                            selectedShippingMethod = response.newShippingMethods[0]
                        }
                        console.log(response)

                    },
                    error: (jqXHR, textStatus, errorThrown) => {
                        console.warn(textStatus, errorThrown)

                    },
                })
            }
            /*const session = new ApplePaySession(3, request(countryCode, currencyCode, totalLabel, amountWithoutTax))
            session.begin()
            if(needShipping){
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
                        console.warn(textStatus, errorThrown)
                        session.abort()
                    },
                })
            }
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
                        action: 'mollie_apple_pay_create_order',
                        productId: productId,
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
            }*/
        })
    }

)
(
    window
)



