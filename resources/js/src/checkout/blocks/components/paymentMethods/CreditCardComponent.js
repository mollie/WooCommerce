import { CreditCardField } from '../paymentFields/CreditCardField';

/**
 * Credit Card Payment Component
 * Handles Mollie credit card payment method with tokenization
 * @param {Object}   props                     - The component props
 * @param {Object}   props.item                - Payment method item configuration
 * @param {Function} props.useEffect           - React useEffect hook
 * @param {string}   props.activePaymentMethod - Currently active payment method identifier
 */
const CreditCardComponent = ( { item, useEffect, activePaymentMethod } ) => {
	useEffect( () => {
		const creditCardSelected = new Event(
			'mollie_creditcard_component_selected',
			{ bubbles: true }
		);

		const handleComponentsReady = () => {
			document.documentElement.dispatchEvent( creditCardSelected );
		};

		// Listen for Mollie components ready event
		document.addEventListener(
			'mollie_components_ready_to_submit',
			handleComponentsReady
		);

		return () => {
			document.removeEventListener(
				'mollie_components_ready_to_submit',
				handleComponentsReady
			);
		};
	}, [] );

	// Dispatch credit card selection event when component mounts
	useEffect( () => {
		if ( activePaymentMethod === 'mollie_wc_gateway_creditcard' ) {
			const creditCardSelected = new Event(
				'mollie_creditcard_component_selected',
				{ bubbles: true }
			);
			document.documentElement.dispatchEvent( creditCardSelected );
		}
	}, [ activePaymentMethod ] );

	return <CreditCardField content={ item.content } />;
};

export default CreditCardComponent;
