/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../../utils';
import {
	buildGatewayLabel,
	processMolliePaymentStatus,
	updateCurrencyIfNeeded,
} from './checkout-test-helpers';

const isMultistepCheckout = process.env.IS_MULTISTEP_CHECKOUT === 'true';

export const testPaymentStatusOnClassicCheckout = ( testId: string, order ) => {
	const { payment, orderStatus } = order;
	const { gateway } = payment;
	const gatewayLabel = buildGatewayLabel( gateway );
	const multistepLabel = isMultistepCheckout ? ' - Multistep' : '';

	test( `${ testId } | Transaction${ multistepLabel } - Classic checkout - ${ gatewayLabel } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
		wooCommerceApi,
		utils,
		classicCheckout,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
	} ) => {
		await updateCurrencyIfNeeded( wooCommerceApi, gateway.currency );

		const orderTotals = await countTotals( order );
		payment.amount = orderTotals.order;

		await utils.fillVisitorsCart( order.products );

		await ( isMultistepCheckout
			? classicCheckout.makeMultistepOrder( order )
			: classicCheckout.makeOrder( order ) );

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
