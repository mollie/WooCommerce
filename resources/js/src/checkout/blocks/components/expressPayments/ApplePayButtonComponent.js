/* global jQuery, ApplePaySession */
import { select } from '@wordpress/data';
import { request } from '../../../../features/apple-pay/applePayRequest';
import { createAppleErrors } from '../../../../features/apple-pay/applePayError';

export const ApplePayButtonComponent = ( { buttonAttributes = {} } ) => {
    console.log('mollieApplePayBlockDataCart:');
    let mollieApplePayBlockDataCart =
        window.mollieApplePayBlockDataCart ||
        window.mollieBlockData.mollieApplePayBlockDataCart;
    if (mollieApplePayBlockDataCart.length === 0) {
        mollieApplePayBlockDataCart = window.mollieBlockData.gatewayData.appleButtonData;
    }
    console.log(mollieApplePayBlockDataCart);
    let nonce = false;
	let wooNonceElement = document.getElementById(
		'woocommerce-process-checkout-nonce'
	);
    if(wooNonceElement) {
        nonce = wooNonceElement.value;
    }
    if(!nonce) {
        nonce = window.mollieBlockData.gatewayData.appleButtonData.nonce;
    }
console.log('nonce:', nonce);

	let updatedContactInfo = [];
	let redirectionUrl = '';

	const {
		shop: { countryCode,  totalLabel = '' },
        ajaxUrl,
	} = mollieApplePayBlockDataCart;

    const cartStore = select('wc/store/cart')
    const needShipping = cartStore.getNeedsShipping();
    const rawSubtotal = cartStore.getCartTotals().total_price;
    const currencyCode = window.wcSettings.currency.code;
    const currencyMinorUnit = cartStore.getCartTotals().currency_minor_unit;
    const subtotal = rawSubtotal / Math.pow( 10, currencyMinorUnit );

console.log('countryCode:', countryCode, 'needShipping:', needShipping,'subtotal:', subtotal, 'currencyCode:', currencyCode);
	const getButtonStyle = ( attrs ) => ( {
		height: `${ attrs.height || 48 }px`,
		borderRadius: `${ attrs.borderRadius || 4 }px`,
	} );
	const style = getButtonStyle( buttonAttributes );

    let applePaySession = () => {
        const session = new ApplePaySession(3, request(countryCode, currencyCode, totalLabel, subtotal))
        session.begin()
        console.log('Apple Pay session started')
        session.onshippingmethodselected = function (event) {
            console.log('Shipping method selected:', event.shippingMethod)
            jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mollie_apple_pay_update_shipping_method',
                    shippingMethod: event.shippingMethod,
                    callerPage: 'cart',
                    simplifiedContact: updatedContactInfo,
                    'woocommerce-process-checkout-nonce': nonce,
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
            console.log('Shipping contact selected:', event.shippingContact)
            jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mollie_apple_pay_update_shipping_contact',
                    simplifiedContact: event.shippingContact,
                    callerPage: 'cart',
                    needShipping: needShipping,
                    'woocommerce-process-checkout-nonce': nonce,
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
            console.log('Apple Pay merchant validation:', applePayValidateMerchantEvent)
            jQuery.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'mollie_apple_pay_validation',
                    validationUrl: applePayValidateMerchantEvent.validationURL,
                    'woocommerce-process-checkout-nonce': nonce,
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

	return (
		<button
			id="mollie_applepay_button"
			className="apple-pay-button apple-pay-button-black"
			onClick={ ( event ) => {
				event.preventDefault();
				applePaySession();
			} }
			style={ style }
		></button>
	);
};

export default ApplePayButtonComponent;
