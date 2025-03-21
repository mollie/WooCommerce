/**
 * External dependencies
 */
import { countTotals } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test } from '../../../../utils';
import { gateways as allGateways } from '../../../../resources';

export const testPaymentStatusOnCheckout = ( testId: string, order ) => {
	const { payment, orderStatus } = order;
	const { gateway } = payment;
	if (
		gateway.slug === 'creditcard' &&
		gateway.settings.mollie_components_enabled !== 'no'
	) {
		gateway.name += ' - Disabled Mollie components';
	}

	test( `${ testId } | Checkout - ${ gateway.name } - Payment status ${ payment.status } creates order with status ${ orderStatus }`, async ( {
		wooCommerceApi,
		transaction,
		wooCommerceOrderEdit,
	} ) => {
		// TODO: remove when productivity issue is fixed:
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
		const orderId = await transaction.onCheckout( order );
		await wooCommerceOrderEdit.assertOrderDetails(
			Number( orderId ),
			order
		);
	} );
};
