/**
 * Registers the Express Component as its own Blocks express payment method, with no host gateway.
 *
 * Only when mollieExpressData is on the page, which the store prints on a block checkout that
 * Express owns and nowhere else — so nothing is registered on the cart block, the product page or a
 * checkout Express does not own. Like the two existing express buttons it drives its own flow and
 * lets Mollie redirect, instead of submitting through the Store API checkout.
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
