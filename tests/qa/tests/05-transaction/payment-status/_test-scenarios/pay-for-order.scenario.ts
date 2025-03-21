/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../../utils';

export const testPaymentStatusOnPayForOrder = ( testId: string, order ) => {
	const { payment, orderStatus } = order;
	const { gateway } = payment;
	if (
		gateway.slug === 'creditcard' &&
		gateway.settings.mollie_components_enabled !== 'no'
	) {
		gateway.name += ' - Disabled Mollie components';
	}

	test( `${ testId } | Pay for order - ${ gateway.name } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
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

		const orderTotals = await countTotals( order );
		payment.amount = orderTotals.order;
		const orderId = await transaction.onPayForOrder( order );
		await wooCommerceOrderEdit.assertOrderDetails(
			Number( orderId ),
			order
		);
	} );
};
