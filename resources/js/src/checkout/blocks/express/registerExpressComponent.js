/**
 * Registers only when the store printed mollieExpressData, which it does on a checkout Express owns.
 *
 * A regular payment method, so Blocks lists it in the payment step, after the address and the
 * shipping. Its gateway is available only on the block checkout and refuses a checkout placed with it.
 */
/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
/**
 * Internal dependencies
 */
import MollieExpressComponent, {
	canMakePayment,
} from './MollieExpressComponent';
import MollieExpressEditorComponent from './MollieExpressEditorComponent';

// The Expresscheckout gateway: Blocks lists a payment method only when the Store API offers it as a gateway.
export const EXPRESS_METHOD_NAME = 'mollie_wc_gateway_expresscheckout';

export function registerExpressComponent() {
	const data = window.mollieExpressData;
	if ( ! data ) {
		return;
	}

	registerPaymentMethod( {
		name: EXPRESS_METHOD_NAME,
		label: <span>{ data.messages.placeholder }</span>,
		content: <MollieExpressComponent data={ data } />,
		edit: (
			<MollieExpressEditorComponent label={ data.messages.placeholder } />
		),
		ariaLabel: data.messages.placeholder,
		canMakePayment,
		supports: {
			features: [ 'products' ],
			showSavedCards: false,
			showSaveOption: false,
		},
	} );
}
