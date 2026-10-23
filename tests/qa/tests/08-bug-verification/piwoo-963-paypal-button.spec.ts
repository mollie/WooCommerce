/**
 * Verifies PIWOO-963 (.org report #50066) for the old PayPal button on the
 * product page. mollie_paypal_update_amount and mollie_paypal_create_order must
 * reject products the button is not shown for, or that cannot be bought in the
 * requested quantity, and must not create an order for them.
 *
 * Not covered here (manual): subscription products (needs WC Subscriptions,
 * inactive in this shard), cart button with a mixed cart (SEC-16).
 *
 * Mollie 8.2.0 Phase 2 test cases: SEC-11 to SEC-15.
 */

/**
 * Internal dependencies
 */
import { test, expect, updateCurrencyIfNeeded } from '../../utils';
import { products } from '../../resources';

const virtualProduct = {
	...products.mollieVirtual100,
	name: 'QA PIWOO-963 Virtual Product',
	slug: 'qa-piwoo-963-virtual',
};
// Restored after each case that changes the product
const virtualBaseline = {
	status: 'publish',
	stock_status: 'instock',
	manage_stock: false,
	sold_individually: false,
};

test.beforeAll( async ( { wooCommerceApi, mollieApi } ) => {
	await updateCurrencyIfNeeded( wooCommerceApi, 'EUR' );
	await mollieApi.updateMollieGateway( 'paypal', {
		mollie_paypal_button_enabled_product: 'yes',
		mollie_paypal_button_minimum_amount: '0',
	} );
} );

test.afterAll( async ( { mollieApi } ) => {
	await mollieApi.updateMollieGateway( 'paypal', {
		mollie_paypal_button_enabled_product: 'no',
	} );
} );

test( 'C4567674 | PayPal product button rejects products it may not sell and creates no order', async ( {
	wooCommerceUtils,
	wooCommerceApi,
	product,
} ) => {
	const virtual = await wooCommerceUtils.createProduct( virtualProduct );
	await wooCommerceApi.updateProduct( virtual.id, virtualBaseline );
	const physical = await wooCommerceUtils.createProduct(
		products.mollieSimple100
	);
	const variable = await wooCommerceUtils.createProduct(
		products.mollieVariable100
	);

	await product.visit( virtual.slug );
	const nonce = await product.payPalExpressButtonNonceInput().inputValue();

	// Valid request first, so rejections below are not a broken setup
	const valid = await product.postPayPalButtonAction(
		'mollie_paypal_update_amount',
		{
			productId: virtual.id,
			productQuantity: 1,
			nonce,
		}
	);
	expect( valid.success, 'Assert valid update_amount is accepted' ).toBe(
		true
	);

	const lastOrderId = async () =>
		( await wooCommerceApi.getOrders( 1 ) )[ 0 ]?.id ?? 0;
	const lastOrderBefore = await lastOrderId();

	const cases: [ string, number, number, object? ][] = [
		[ 'SEC-11 quantity 0', virtual.id, 0 ],
		[ 'SEC-11 quantity -1', virtual.id, -1 ],
		[
			'SEC-12 more than stock',
			virtual.id,
			2,
			{ manage_stock: true, stock_quantity: 1 },
		],
		[
			'SEC-13 out of stock',
			virtual.id,
			1,
			{ stock_status: 'outofstock' },
		],
		[ 'SEC-13 draft', virtual.id, 1, { status: 'draft' } ],
		[ 'SEC-13 private', virtual.id, 1, { status: 'private' } ],
		[ 'SEC-14 variable parent', variable.id, 1 ],
		[ 'SEC-14 physical product', physical.id, 1 ],
		[
			'SEC-15 sold individually, quantity 2',
			virtual.id,
			2,
			{ sold_individually: true },
		],
	];
	for ( const [
		label,
		productId,
		productQuantity,
		productChange,
	] of cases ) {
		await test.step( label, async () => {
			if ( productChange ) {
				await wooCommerceApi.updateProduct( virtual.id, productChange );
			}
			const data = { productId, productQuantity, nonce };
			expect(
				await product.postPayPalButtonAction(
					'mollie_paypal_update_amount',
					data
				)
			).toEqual( { success: false, data: 'product not purchasable' } );
			expect(
				await product.postPayPalButtonAction(
					'mollie_paypal_create_order',
					data
				)
			).toEqual( {
				success: false,
				data: 'Could not create PayPal payment.',
			} );
			if ( productChange ) {
				await wooCommerceApi.updateProduct(
					virtual.id,
					virtualBaseline
				);
			}
		} );
	}

	expect( await lastOrderId(), 'Assert no order was created' ).toEqual(
		lastOrderBefore
	);
} );
