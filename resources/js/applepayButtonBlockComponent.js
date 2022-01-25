import {maybeShowButton} from './maybeShowApplePayButton.js';
import {request} from "./applePayRequest";
import {createAppleErrors} from "./applePayError";

(
    function ({mollieApplePayBlockDataCart})
    {
        if (_.isEmpty(mollieApplePayBlockDataCart)) {
            return
        }
        const {product: {needShipping = true, subtotal}, shop: {countryCode, currencyCode = 'EUR', totalLabel = ''}, buttonMarkup, ajaxUrl} = mollieApplePayBlockDataCart

        const { registerPlugin } = wp.plugins;
        const { ExperimentalOrderMeta } = wc.blocksCheckout;
        const ApplePayButtonComponent = ( { cart, extensions } ) => {
            return <div dangerouslySetInnerHTML={ {__html: buttonMarkup} }/>
        }
        const MollieApplePayButtonCart = () => {
            return  <ExperimentalOrderMeta>
                <ApplePayButtonComponent />
            </ExperimentalOrderMeta>
        };

        registerPlugin( 'mollie-applepay-block-button', {
            render: () => {
                return <MollieApplePayButtonCart />;
            },
            scope: 'woocommerce-checkout'
        } );

        setTimeout(function(){
            if(!maybeShowButton()){
                return
            }
            const nonce = document.getElementById("woocommerce-process-checkout-nonce").value

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
                    const {billingContact, shippingContact } = ApplePayPayment.payment

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
                            'woocommerce-process-checkout-nonce': nonce,
                            'billing_first_name': billingContact.givenName || '',
                            'billing_last_name': billingContact.familyName || '',
                            'billing_company': '',
                            'billing_country': billingContact.countryCode || '',
                            'billing_address_1': billingContact.addressLines[0] || '',
                            'billing_address_2': billingContact.addressLines[1] || '',
                            'billing_postcode': billingContact.postalCode || '',
                            'billing_city': billingContact.locality || '',
                            'billing_state': billingContact.administrativeArea || '',
                            'billing_phone': billingContact.phoneNumber || '000000000000',
                            'billing_email': shippingContact.emailAddress || '',
                            'shipping_first_name': shippingContact.givenName || '',
                            'shipping_last_name': shippingContact.familyName || '',
                            'shipping_company': '',
                            'shipping_country': shippingContact.countryCode || '',
                            'shipping_address_1': shippingContact.addressLines[0] || '',
                            'shipping_address_2': shippingContact.addressLines[1] || '',
                            'shipping_postcode': shippingContact.postalCode || '',
                            'shipping_city': shippingContact.locality || '',
                            'shipping_state': shippingContact.administrativeArea || '',
                            'shipping_phone': shippingContact.phoneNumber || '000000000000',
                            'shipping_email': shippingContact.emailAddress || '',
                            'order_comments' : '',
                            'payment_method' : 'mollie_wc_gateway_applepay',
                            '_wp_http_referer' : '/?wc-ajax=update_order_review'
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
            document.querySelector('#mollie_applepay_button').addEventListener('click', (evt) => {
                applePaySession()
            })
        },500);

    }
)
(
    window
)
