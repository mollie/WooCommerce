/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../../utils';
import { gateways as allGateways } from '../../../../resources';
import { processMolliePaymentStatus } from './checkout-test-helpers';

export const testPaymentStatusOnCheckout = ( testId: string, order ) => {
	const { payment, orderStatus } = order;
	const { gateway } = payment;
	let testedGateway = gateway.name;
	if ( gateway.slug === 'creditcard' ) {
		testedGateway += gateway.settings.mollie_components_enabled === 'yes'
			? ' - Mollie components enabled'
			: ' - Mollie components disabled';
	}

	test( `${ testId } | Transaction - Checkout - ${ testedGateway } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
		wooCommerceApi,
		utils,
		checkout,
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

		await checkout.makeOrder( order );

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
