export const maybeShowButton = () => {
	const { ApplePaySession } = window;
	const applePayMethodElement = document.querySelector(
		'#mollie-applepayDirect-button'
	);
	const canShowButton =
		applePayMethodElement &&
		ApplePaySession &&
		ApplePaySession.canMakePayments();
	if ( ! canShowButton ) {
		return false;
	}
	const button = document.createElement( 'button' );
	button.setAttribute( 'id', 'mollie_applepay_button' );
	button.classList.add( 'apple-pay-button' );
	button.classList.add( 'apple-pay-button-black' );
	applePayMethodElement.appendChild( button );
	return true;
};
