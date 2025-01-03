/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../../utils';

export const testPaymentStatusOnPayForOrder = ( testId: string, order ) => {
	const gatewaySettings = order.payment.gateway.settings;
	let gatewayName = order.payment.gateway.name;
	if( gatewaySettings.mollie_components_enabled === 'yes' ) {
		gatewayName +=  ' - Mollie components';
	}

	test( `${ testId } | Pay for order - ${ gatewayName } - Payment status "${ order.payment.status }" creates order with status "${ order.orderStatus }"`, async ( {
		wooCommerceApi,
		transaction,
		wooCommerceOrderEdit,
	} ) => {
		const currency = order.payment.gateway.currency;
		if ( currency !== undefined && currency !== 'EUR' ) {
			await wooCommerceApi.updateGeneralSettings( {
				woocommerce_currency: currency,
			} );
		}
		order.payment.amount = ( await countTotals( order ) ).order;
		const orderId = await transaction.onPayForOrder( order );
		await wooCommerceOrderEdit.assertOrderDetails(
			Number( orderId ),
			order
		);
	} );
};
