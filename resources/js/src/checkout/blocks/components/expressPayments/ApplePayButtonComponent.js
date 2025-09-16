import { request } from '../../../../features/apple-pay/applePayRequest';
import { createAppleErrors } from '../../../../features/apple-pay/applePayError';

export const ApplePayButtonComponent = ( { buttonAttributes = {} } ) => {
	const mollieApplePayBlockDataCart =
		window.mollieApplePayBlockDataCart ||
		window.mollieBlockData.mollieApplePayBlockDataCart;

	const nonce = document.getElementById(
		'woocommerce-process-checkout-nonce'
	).value;

	let updatedContactInfo = [];
	let redirectionUrl = '';

	const {
		product: { needShipping = true, subtotal },
		shop: { countryCode, currencyCode = 'EUR', totalLabel = '' },
		ajaxUrl,
	} = mollieApplePayBlockDataCart;

	const getButtonStyle = ( attrs ) => ( {
		height: `${ attrs.height || 48 }px`,
		borderRadius: `${ attrs.borderRadius || 4 }px`,
	} );
	const style = getButtonStyle( buttonAttributes );

	const ajaxPost = ( url, data, success, error ) =>
		jQuery.ajax( { url, method: 'POST', data, success, error } );

	const findSelectedShippingMethod = ( shippingRates ) => {
		const shippingRate = shippingRates.find(
			( shippingMethod ) => shippingMethod.selected === true
		);
		const appleFormattedRate = {
			amount: '',
			detail: '',
			label: shippingRate?.name,
			identifier: shippingRate?.rate_id,
			selected: shippingRate?.selected,
		};
		return shippingRate ? appleFormattedRate : '';
	};

	const buildOrderPayload = ( applePayment, selectedShippingMethod ) => {
		const { billingContact, shippingContact, token } = applePayment.payment;

		return {
			action: 'mollie_apple_pay_create_order_cart',
			shippingContact: shippingContact,
			billingContact: billingContact,
			token,
			shippingMethod: selectedShippingMethod,
			'mollie-payments-for-woocommerce_issuer_applepay': 'applepay',
			'woocommerce-process-checkout-nonce': nonce,

			// billing
			billing_first_name: billingContact.givenName || '',
			billing_last_name: billingContact.familyName || '',
			billing_company: '',
			billing_country: billingContact.countryCode || '',
			billing_address_1: billingContact.addressLines[ 0 ] || '',
			billing_address_2: billingContact.addressLines[ 1 ] || '',
			billing_postcode: billingContact.postalCode || '',
			billing_city: billingContact.locality || '',
			billing_state: billingContact.administrativeArea || '',
			billing_phone: billingContact.phoneNumber || '000000000000',
			billing_email: shippingContact.emailAddress || '',

			// shipping
			shipping_first_name: shippingContact.givenName || '',
			shipping_last_name: shippingContact.familyName || '',
			shipping_company: '',
			shipping_country: shippingContact.countryCode || '',
			shipping_address_1: shippingContact.addressLines[ 0 ] || '',
			shipping_address_2: shippingContact.addressLines[ 1 ] || '',
			shipping_postcode: shippingContact.postalCode || '',
			shipping_city: shippingContact.locality || '',
			shipping_state: shippingContact.administrativeArea || '',
			shipping_phone: shippingContact.phoneNumber || '000000000000',
			shipping_email: shippingContact.emailAddress || '',

			order_comments: '',
			payment_method: 'mollie_wc_gateway_applepay',
			_wp_http_referer: '/?wc-ajax=update_order_review',
		};
	};

	const applePaySession = () => {
		const session = new ApplePaySession(
			3,
			request( countryCode, currencyCode, totalLabel, subtotal )
		);
		session.begin();

		const store = wp.data.select( 'wc/store/cart' );
		const shippingRates = store.getShippingRates()?.[ 0 ]?.shipping_rates;

		let selectedShippingMethod = '';
		if ( shippingRates && shippingRates.length > 0 ) {
			selectedShippingMethod = findSelectedShippingMethod(
				shippingRates,
				selectedShippingMethod
			);
		}

		function handleShippingMethodSelected( event ) {
			ajaxPost(
				ajaxUrl,
				{
					action: 'mollie_apple_pay_update_shipping_method',
					shippingMethod: event.shippingMethod,
					callerPage: 'cart',
					simplifiedContact: updatedContactInfo,
					'woocommerce-process-checkout-nonce': nonce,
				},
				( applePayShippingMethodUpdate ) => {
					const response = applePayShippingMethodUpdate.data;
					selectedShippingMethod = event.shippingMethod;
					if ( applePayShippingMethodUpdate.success === false ) {
						response.errors = createAppleErrors( response.errors );
					}
					this.completeShippingMethodSelection( response );
				},
				( jqXHR, textStatus, errorThrown ) => {
					console.warn( textStatus, errorThrown );
					session.abort();
				}
			);
		}

		function handleShippingContactSelected( event ) {
			ajaxPost(
				ajaxUrl,
				{
					action: 'mollie_apple_pay_update_shipping_contact',
					simplifiedContact: event.shippingContact,
					callerPage: 'cart',
					needShipping,
					'woocommerce-process-checkout-nonce': nonce,
					shippingMethod: selectedShippingMethod,
				},
				( applePayShippingContactUpdate ) => {
					const response = applePayShippingContactUpdate.data;
					updatedContactInfo = event.shippingContact;
					if ( applePayShippingContactUpdate.success === false ) {
						response.errors = createAppleErrors( response.errors );
					}
					if ( response.newShippingMethods ) {
						selectedShippingMethod =
							response.newShippingMethods[ 0 ];
					}
					this.completeShippingContactSelection( response );
				},
				( jqXHR, textStatus, errorThrown ) => {
					console.warn( textStatus, errorThrown );
					session.abort();
				}
			);
		}

		function handleValidateMerchant( applePayValidateMerchantEvent ) {
			ajaxPost(
				ajaxUrl,
				{
					action: 'mollie_apple_pay_validation',
					validationUrl: applePayValidateMerchantEvent.validationURL,
					'woocommerce-process-checkout-nonce': nonce,
				},
				( merchantSession ) => {
					if ( merchantSession.success === true ) {
						session.completeMerchantValidation(
							JSON.parse( merchantSession.data )
						);
					} else {
						console.warn( merchantSession.data );
						session.abort();
					}
				},
				( jqXHR, textStatus, errorThrown ) => {
					console.warn( textStatus, errorThrown );
					session.abort();
				}
			);
		}

		function handlePaymentAuthorized( ApplePayPayment ) {
			const payload = buildOrderPayload(
				ApplePayPayment,
				selectedShippingMethod
			);
			ajaxPost(
				ajaxUrl,
				payload,
				( authorizationResult ) => {
					const result = authorizationResult.data;
					if ( authorizationResult.success === true ) {
						redirectionUrl = result.returnUrl;
						session.completePayment( result.responseToApple );
						window.location.href = redirectionUrl;
					} else {
						result.errors = createAppleErrors( result.errors );
						session.completePayment( result );
					}
				},
				( jqXHR, textStatus, errorThrown ) => {
					console.warn( textStatus, errorThrown );
					session.abort();
				}
			);
		}

		session.onshippingmethodselected = handleShippingMethodSelected;
		session.onshippingcontactselected = handleShippingContactSelected;
		session.onvalidatemerchant = handleValidateMerchant;
		session.onpaymentauthorized = handlePaymentAuthorized;
	};

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
