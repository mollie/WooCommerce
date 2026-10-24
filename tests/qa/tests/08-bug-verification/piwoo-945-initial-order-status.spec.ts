/**
 * Verifies the PIWOO-945 "Initial order status" setting. The on-hold itself
 * needs a Mollie "pending" payment, which test mode cannot produce
 * (integration: tests/Integration/spec/webhooks/PendingPaymentHoldTest.php).
 *
 * Mollie 8.2.0 Phase 2 test cases: HOLD-01.
 */

/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { gateways } from '../../resources';

test( 'C4567673 | Pay by Bank, Wero, iDEAL and Bank transfer offer "Initial order status", default On hold', async ( {
	wooCommerceApi,
	mollieApiMethod,
} ) => {
	for ( const slug of [ 'paybybank', 'wero', 'ideal', 'banktransfer' ] ) {
		// Wero is not registered with the Orders API
		if (
			! gateways[ slug ].availableForApiMethods.includes(
				mollieApiMethod
			)
		) {
			continue;
		}
		await test.step( slug, async () => {
			const gateway = await wooCommerceApi.wcRequest(
				'get',
				`payment_gateways/mollie_wc_gateway_${ slug }`
			);
			const setting = gateway.settings.initial_order_status;
			expect( setting?.label ).toEqual( 'Initial order status' );
			expect( Object.keys( setting.options ) ).toEqual( [
				'on-hold',
				'pending',
			] );
			expect( setting.default ).toEqual( 'on-hold' );
		} );
	}
} );
