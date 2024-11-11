/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../utils';

export const testPaymentStatusPayForOrder = ( testId: string, order ) => {
	test( `${ testId } | Pay for order - ${ order.payment.gateway.name } - Payment status "${ order.payment.status }" creates order with status "${ order.orderStatus }"`, async ( {
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
