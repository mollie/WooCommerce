import { CreditCardField } from '../paymentFields/CreditCardField';

const CreditCardComponent = ( {
	item,
	useEffect,
	activePaymentMethod,
	isComponentReady,
	componentError,
} ) => {
    const isComponentsEnabled = item.shouldLoadComponents;
	useEffect( () => {
		if ( activePaymentMethod === 'mollie_wc_gateway_creditcard' ) {
			// Dispatch event to indicate credit card method is selected
			const creditCardSelected = new Event(
				'mollie_creditcard_component_selected',
				{
					bubbles: true,
				}
			);
			document.documentElement.dispatchEvent( creditCardSelected );
		}
	}, [ activePaymentMethod ] );

	// Display component status
	const getComponentStatus = () => {
		if ( componentError && isComponentsEnabled) {
			return (
				<div className="mollie-error">Error: { componentError }</div>
			);
		}

		if ( ! isComponentReady && isComponentsEnabled) {
			return (
				<div className="mollie-loading">Loading payment form...</div>
			);
		}

		return null;
	};
	if (!isComponentsEnabled) {
		return <div></div>;
	}

	return (
		<div className="mollie-creditcard-component">
			{ getComponentStatus() }

			{ /* Legacy content fallback */ }
			{ item.content && <CreditCardField content={ item.content } /> }
		</div>
	);
};

export default CreditCardComponent;
