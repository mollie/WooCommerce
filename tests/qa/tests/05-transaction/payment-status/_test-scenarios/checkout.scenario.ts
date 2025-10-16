/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../../utils';
import { processMolliePaymentStatus } from './process-mollie-payment-status.scenario';

const isMultistepCheckout = process.env.IS_MULTISTEP_CHECKOUT === 'true';

export const testPaymentStatusOnCheckout = ( testId: string, order ) => {
	const { payment, orderStatus } = order;
	const { gateway } = payment;
	let testedGateway = gateway.name;
	if ( gateway.slug === 'creditcard' ) {
		testedGateway += gateway.settings.mollie_components_enabled === 'yes'
			? ' - Mollie components enabled'
			: ' - Mollie components disabled';
	}
	const multistepLabel = isMultistepCheckout ? ' - Multistep' : '';

	test( `${ testId } | Transaction${ multistepLabel } - Checkout - ${ testedGateway } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
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

		await ( isMultistepCheckout 
			? checkout.makeMultistepOrder( order ) 
			: checkout.makeOrder( order )
		);

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
