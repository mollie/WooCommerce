/**
 * Registers only when the store printed mollieExpressData, which it does on a checkout Express owns.
 */
/**
 * External dependencies
 */
import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
/**
 * Internal dependencies
 */
import MollieExpressComponent, {
	canMakePayment,
} from './MollieExpressComponent';
import MollieExpressEditorComponent from './MollieExpressEditorComponent';

export const EXPRESS_METHOD_NAME = 'mollie_express_component';

export function registerExpressComponent() {
	const data = window.mollieExpressData;
	if ( ! data ) {
		return;
	}

	registerExpressPaymentMethod( {
		name: EXPRESS_METHOD_NAME,
		title: data.messages.placeholder,
		description: data.messages.placeholder,
		content: <MollieExpressComponent data={ data } />,
		edit: (
			<MollieExpressEditorComponent label={ data.messages.placeholder } />
		),
		ariaLabel: data.messages.placeholder,
		canMakePayment,
		supports: {
			features: [ 'products' ],
		},
	} );
}
