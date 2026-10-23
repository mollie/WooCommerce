/**
 * Verifies PIWOO-963 (.org report #50065) on the order-pay page.
 * The page script posts update_surcharge_order_pay on load and on each payment
 * method change. The endpoint may only change the Mollie fee of an order that
 * still needs payment, for a registered and enabled gateway, with the nonce
 * rendered for the same user. The fee is also applied on submit without JS.
 *
 * WooCommerce binds guest nonces to the session only for "woocommerce*" actions,
 * so all guests share the surcharge nonce. "Another user" is the logged-in admin.
 *
 * Mollie 8.2.0 Phase 2 test cases: SEC-01 to SEC-08.
 */

/**
 * External dependencies
 */
import { APIRequestContext } from '@playwright/test';
import { WooCommerceApi } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test, expect, updateCurrencyIfNeeded } from '../../utils';
import { orders, products } from '../../resources';

const FEE_LABEL = 'Gateway Fee';
const CARD = 'mollie_wc_gateway_creditcard';
const IDEAL = 'mollie_wc_gateway_ideal';
const DISABLED = 'mollie_wc_gateway_kbc';
const BACS = 'bacs';

const testOrder = { ...orders.default, products: [ products.mollieSimple100 ] };

let initiallyEnabled: Record< string, boolean >;

const surchargeFees = async (
	wooCommerceApi: WooCommerceApi,
	orderId: number
) =>
	( await wooCommerceApi.getOrder( orderId ) ).fee_lines
		.filter( ( fee: { name: string } ) => fee.name === FEE_LABEL )
		.map( ( fee: { total: string } ) => Number( fee.total ) );

test.beforeAll( async ( { wooCommerceApi, mollieApi } ) => {
	await updateCurrencyIfNeeded( wooCommerceApi, 'EUR' );
	initiallyEnabled = {};
	for ( const id of [ BACS, DISABLED ] ) {
		initiallyEnabled[ id ] = (
			await wooCommerceApi.wcRequest( 'get', `payment_gateways/${ id }` )
		).enabled;
	}
	await wooCommerceApi.enablePaymentGateway( BACS );
	await wooCommerceApi.disablePaymentGateway( DISABLED );
	await mollieApi.updateMollieGateway( 'creditcard', {
		payment_surcharge: 'fixed_fee',
		fixed_fee: '1',
	} );
	await mollieApi.updateMollieGateway( 'ideal', {
		payment_surcharge: 'fixed_fee',
		fixed_fee: '2',
	} );
} );

test.afterAll( async ( { wooCommerceApi, mollieApi } ) => {
	await mollieApi.updateMollieGateway( 'creditcard', {
		payment_surcharge: 'no_fee',
	} );
	await mollieApi.updateMollieGateway( 'ideal', {
		payment_surcharge: 'no_fee',
	} );
	for ( const [ id, enabled ] of Object.entries( initiallyEnabled ) ) {
		if ( enabled ) {
			await wooCommerceApi.enablePaymentGateway( id );
		} else {
			await wooCommerceApi.disablePaymentGateway( id );
		}
	}
} );

test.describe( 'Surcharge on the order-pay page (PIWOO-963 #50065)', () => {
	test( 'C4567675 | Fee follows the selected Mollie method, a non-Mollie gateway removes it', async ( {
		wooCommerceUtils,
		wooCommerceApi,
		payForOrder,
	} ) => {
		const order = await wooCommerceUtils.createApiOrder( testOrder );
		await payForOrder.visitAndWaitForSurcharge( order.id, order.order_key );
		const feeRow = payForOrder.orderReviewFeeRow( FEE_LABEL );

		await payForOrder.selectPaymentMethodAndWaitForSurcharge( CARD );
		await expect( feeRow, 'Assert card fee row' ).toContainText( '1.00' );
		expect( await surchargeFees( wooCommerceApi, order.id ) ).toEqual( [
			1,
		] );

		await payForOrder.selectPaymentMethodAndWaitForSurcharge( IDEAL );
		await expect(
			feeRow,
			'Assert iDEAL fee replaced card fee'
		).toContainText( '2.00' );
		expect( await surchargeFees( wooCommerceApi, order.id ) ).toEqual( [
			2,
		] );

		await payForOrder.selectPaymentMethodAndWaitForSurcharge( BACS );
		await expect(
			feeRow,
			'Assert no fee row for bank transfer'
		).toHaveCount( 0 );

		await payForOrder.payForOrderButton().click();
		await expect
			.poll(
				async () => ( await wooCommerceApi.getOrder( order.id ) ).status
			)
			.toBe( 'on-hold' );
		expect(
			await surchargeFees( wooCommerceApi, order.id ),
			'Assert order paid by bank transfer has no Mollie fee'
		).toEqual( [] );
	} );

	test.describe( 'Without JavaScript', () => {
		test.use( { javaScriptEnabled: false } );

		test( 'C4567676 | Fee is applied on submit without JavaScript', async ( {
			wooCommerceUtils,
			wooCommerceApi,
			payForOrder,
		} ) => {
			const order = await wooCommerceUtils.createApiOrder( testOrder );
			await payForOrder.visit( order.id, order.order_key );
			await payForOrder.paymentMethodLabel( IDEAL ).click();
			await payForOrder.payForOrderButton().click();
			await payForOrder.page.waitForLoadState();

			expect(
				await surchargeFees( wooCommerceApi, order.id ),
				'Assert iDEAL fee added by the submit, not by the page script'
			).toEqual( [ 2 ] );
		} );
	} );

	test( 'C4567677 | Tampered requests are rejected and the order is unchanged', async ( {
		wooCommerceUtils,
		wooCommerceApi,
		payForOrder,
		request,
	} ) => {
		const order = await wooCommerceUtils.createApiOrder( testOrder );
		const { orderKey, nonce } = await payForOrder.visitAndWaitForSurcharge(
			order.id,
			order.order_key
		);

		// Valid request first, so rejections below are not a broken setup
		const valid = await payForOrder.postSurchargeUpdate( {
			orderKey,
			nonce,
			method: CARD,
		} );
		expect( valid.success, 'Assert valid request is accepted' ).toBe(
			true
		);
		const before = await wooCommerceApi.getOrder( order.id );

		// Each tampered method would change the card fee if accepted
		const rejected: [
			string,
			{ orderKey: string; nonce: string; method: string },
			APIRequestContext?,
		][] = [
			[
				'SEC-04 Mollie-like prefix',
				{ orderKey, nonce, method: `x${ IDEAL }` },
			],
			[
				'SEC-05 disabled gateway',
				{ orderKey, nonce, method: DISABLED },
			],
			[
				'SEC-05 unknown gateway',
				{ orderKey, nonce, method: 'does_not_exist' },
			],
			[
				'SEC-06 wrong nonce',
				{ orderKey, nonce: '0000000000', method: IDEAL },
			],
			// Logged-in admin sends the guest nonce
			[
				'SEC-08 nonce of another user',
				{ orderKey, nonce, method: IDEAL },
				request,
			],
		];
		for ( const [ label, data, otherUser ] of rejected ) {
			await test.step( label, async () => {
				expect(
					await payForOrder.postSurchargeUpdate( data, otherUser )
				).toEqual( { success: false } );
			} );
		}

		await test.step( 'SEC-07 order that does not need payment', async () => {
			await wooCommerceApi.updateOrder( order.id, {
				status: 'processing',
			} );
			expect(
				await payForOrder.postSurchargeUpdate( {
					orderKey,
					nonce,
					method: IDEAL,
				} )
			).toEqual( { success: false } );
		} );

		const after = await wooCommerceApi.getOrder( order.id );
		expect( after.total, 'Assert order total unchanged' ).toEqual(
			before.total
		);
		expect( await surchargeFees( wooCommerceApi, order.id ) ).toEqual( [
			1,
		] );
	} );
} );
