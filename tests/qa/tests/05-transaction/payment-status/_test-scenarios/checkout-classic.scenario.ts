/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../../utils';
import { processMolliePaymentStatus } from './checkout-test-helpers';

export const testPaymentStatusOnClassicCheckout = ( testId: string, order ) => {
	const { payment, orderStatus } = order;
	const { gateway } = payment;
	let testedGateway = gateway.name;
	if ( gateway.slug === 'creditcard' ) {
		testedGateway += gateway.settings.mollie_components_enabled === 'yes'
			? ' - Mollie components enabled'
			: ' - Mollie components disabled';
	}

	test( `${ testId } | Transaction - Classic checkout - ${ testedGateway } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
		wooCommerceApi,
		utils,
		classicCheckout,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
	} ) => {
		const currency = gateway.currency;
		if ( currency !== undefined && currency !== 'EUR' ) {
			await wooCommerceApi.updateGeneralSettings( {
				woocommerce_currency: currency,
			} );
		}

		const orderTotals = await countTotals( order );
		payment.amount = orderTotals.order;

		await utils.fillVisitorsCart( order.products );

		await classicCheckout.makeOrder( order );

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
