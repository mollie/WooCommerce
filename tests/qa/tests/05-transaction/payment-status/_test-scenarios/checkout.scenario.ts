/**
 *External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../../utils';
import { gateways as allGateways } from '../../../../resources';
import { processMolliePaymentStatus } from './process-mollie-payment-status.scenario';

export const testPaymentStatusOnCheckout = ( testId: string, order ) => {
	const { payment, orderStatus } = order;
	const { gateway } = payment;
	let testedGateway = gateway.name;
	if (
		gateway.slug === 'creditcard' &&
		gateway.settings.mollie_components_enabled === 'yes'
	) {
		testedGateway += ' - Disabled Mollie components';
	}

	test( `${ testId } | Checkout - ${ testedGateway } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
		wooCommerceApi,
		utils,
		checkout,
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
		wooCommerceOrderEdit,
	} ) => {
		for ( const key in allGateways ) {
			const isEnabled = allGateways[ key ].slug === gateway.slug;
			await wooCommerceApi.updatePaymentGateway(
				`mollie_wc_gateway_${ allGateways[ key ].slug }`,
				{ enabled: isEnabled }
			);
		}

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
