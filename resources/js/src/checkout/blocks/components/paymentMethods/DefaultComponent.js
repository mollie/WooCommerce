import { IssuerSelect } from '../paymentFields/IssuerSelect';

/**
 * Default Payment Component
 * Handles payment methods with issuer selection (banks) or simple content display
 * @param {Object}      props                     - The component props
 * @param {Object}      props.item                - Payment method item configuration
 * @param {string}      props.activePaymentMethod - Currently active payment method identifier
 * @param {string|null} props.selectedIssuer      - Currently selected issuer ID (from store)
 * @param {Function}    props.setSelectedIssuer   - Function to update the selected issuer in store
 */
const DefaultComponent = ( {
	item,
	activePaymentMethod,
	selectedIssuer,
	setSelectedIssuer,
} ) => {
	const issuerKey = `mollie-payments-for-woocommerce_issuer_${ activePaymentMethod }`;

	let itemContent = null;
	if ( item.content && item.content !== '' ) {
		itemContent = <p>{ item.content }</p>;
	}

	// Show issuer selection for payment methods that have issuers (banks, etc.)
	if ( item.issuers && item.issuers.length > 0 ) {
		return (
			<div>
				{ itemContent }
				<IssuerSelect
					issuerKey={ issuerKey }
					issuers={ item.issuers }
					selectedIssuer={ selectedIssuer }
					updateIssuer={ setSelectedIssuer }
				/>
			</div>
		);
	}

	// Simple content display for payment methods without special requirements
	return <div>{ itemContent }</div>;
};

export default DefaultComponent;
