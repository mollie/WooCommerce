/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../../utils';
import { gateways as allGateways } from '../../../../resources';
import { buildGatewayLabel, processMolliePaymentStatus, updateCurrencyIfNeeded } from './checkout-test-helpers';

export const testPaymentStatusOnCheckout = ( testId: string, order ) => {
	const { payment, orderStatus } = order;
	const { gateway } = payment;
	const gatewayLabel = buildGatewayLabel( gateway );

	test( `${ testId } | Transaction - Checkout - ${ gatewayLabel } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
		wooCommerceApi,
		utils,
		checkout,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
	} ) => {
		await updateCurrencyIfNeeded( wooCommerceApi, gateway.currency );

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
