( function ( ApplePaySession ) {
	document.addEventListener(
		'DOMContentLoaded',
		hideApplyPaymentMethodIfCantPay
	);
	jQuery( 'body' ).on( 'updated_checkout', hideApplyPaymentMethodIfCantPay );

	function hideApplyPaymentMethodIfCantPay() {
		const applePayMethodElement = document.querySelector(
			'.payment_method_mollie_wc_gateway_applepay'
		);

		const woocommerceCheckoutForm = document.querySelector(
			'form.woocommerce-checkout, #order_review'
		);

		if ( ! woocommerceCheckoutForm ) {
			return;
		}

		if ( ! ApplePaySession || ! ApplePaySession.canMakePayments() ) {
			applePayMethodElement &&
				applePayMethodElement.parentNode.removeChild(
					applePayMethodElement
				);
			return;
		}

		woocommerceCheckoutForm.insertAdjacentHTML(
			'beforeend',
			'<input type="hidden" name="mollie_apple_pay_method_allowed" value="1" />'
		);
	}
} )( window.ApplePaySession );
