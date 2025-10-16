/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../../utils';
import { processMolliePaymentStatus } from './process-mollie-payment-status.scenario';

export const testPaymentStatusOnPayForOrder = ( testId: string, order ) => {
	const { payment, orderStatus } = order;
	const { gateway } = payment;
	let testedGateway = gateway.name;
	if ( gateway.slug === 'creditcard' ) {
		testedGateway += gateway.settings.mollie_components_enabled === 'yes'
			? ' - Mollie components enabled'
			: ' - Mollie components disabled';
	}

	test( `${ testId } | Transaction - Pay for order - ${ testedGateway } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
		wooCommerceApi,
		wooCommerceUtils,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
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

		const apiOrder = await wooCommerceUtils.createApiOrder( order );

		await payForOrder.makeOrder( apiOrder.id, apiOrder.order_key, order );

		const orderId = await mollieHostedCheckout.pay( payment );

		await processMolliePaymentStatus(
			{ mollieHostedCheckout, orderReceived, payForOrder },
			Number( orderId ),
			order
		);

		await wooCommerceOrderEdit.assertOrderDetails(
			Number( orderId ),
			order
		);
	} );
};
