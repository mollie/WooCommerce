/**
 * External dependencies
 */
import { Locator, expect } from '@playwright/test';
import {
	WooCommerceOrderEdit as wooCommerceOrderEditBase,
	formatMoney,
} from '@inpsyde/playwright-utils/build';

const { WLOP_NAME } = process.env;

export class WooCommerceOrderEdit extends wooCommerceOrderEditBase {
	// Locators
	transactionIdText = ( transactionId ): Locator =>
		this.orderNumberContainer().getByText( `(${ transactionId })` );
	billingDataTransactionIdInput = (): Locator =>
		this.billingDataContainer().getByLabel( 'Transaction ID' );

	refundViaMollieButton = () =>
		this.page.locator( '.do-api-refund', { hasText: WLOP_NAME } );

	productsTable = () => this.page.locator( '#order_line_items' );
	productRow = ( name ): Locator => this.productsTable().getByRole( 'row', { name } );
	productRefundQtyInput = ( name ): Locator =>
		this.productRow( name ).locator( '.refund_order_item_qty' );
	productRefundTotalInput = ( name ): Locator =>
		this.productRow( name ).locator( '.refund_line_total' );
	productRefundTaxInput = ( name ): Locator =>
		this.productRow( name ).locator( '.refund_line_tax' );

	firstRefundTotalInput = (): Locator =>
		this.productsTable().locator( '.refund_line_total' ).first();

	shippingTable = () => this.page.locator( '#order_shipping_line_items' );
	shippingRow = ( name ): Locator => this.shippingTable().getByRole( 'row', { name } );
	shippingRefundTotalInput = ( name ): Locator =>
		this.shippingRow( name ).locator( '.refund_line_total' );
	shippingRefundTaxInput = ( name ): Locator =>
		this.shippingRow( name ).locator( '.refund_line_tax' );

	totalWorldlineRefunded = (): Locator =>
		this.totalsTableRow( `${ WLOP_NAME } Refunded:` );
	totalWorldlineNetTotal = (): Locator =>
		this.totalsTableRow( `${ WLOP_NAME } Net Total:` );

	
	orderStatusLabels = {
		pending: 'Pending payment',
		processing: 'Processing',
		'on-hold': 'On hold',
		completed: 'Completed',
		cancelled: 'Cancelled',
		refunded: 'Refunded',
		failed: 'Failed',
		draft: 'Draft',
	};

	// Actions
	/**
	 * TODO: needs update
	 * Performs Worldline refund
	 *
	 * @param amount
	 */
	makeRefund = async ( amount?: string ) => {
		await this.refundButton().click();
		if ( ! amount ) {
			const totalAmount =
				( await this.totalAvailableToRefund().textContent() ) || '';
			amount = parseFloat(
				totalAmount.replace( /[^\d.-]+/g, '' ).trim()
			).toFixed( 2 );
		}
		await this.firstRefundTotalInput().fill( amount );
		await this.page.on( 'dialog', ( dialog ) => dialog.accept() );
		// await this.page.on('dialog', dialog => dialog.accept());
		await this.refundViaMollieButton().click();
	};

	/**
	 * TODO: needs update
	 * Performs Mollie refund for specific product
	 *
	 * @param productName
	 * @param qty
	 */
	makeRefundForProduct = async ( productName: string, qty: number = 1 ) => {
		await this.refundButton().click();
		await this.lineItemRefundQuantityInput( productName ).fill(
			String( qty )
		);
		await this.page.on( 'dialog', ( dialog ) => dialog.accept() );
		// await this.page.on('dialog', dialog => dialog.accept());
		await this.refundViaMollieButton().click();
	};

	// Assertions

	/**
	 * Asserts data provided on the order page
	 *
	 * @param orderId
	 * @param orderData
	 * @param mollieData
	 */
	assertOrderDetails = async (
		orderId: number,
		orderData: WooCommerce.ShopOrder,
		mollieData?
	) => {
		await this.visit( orderId );
		const orderStatusLabel = this.orderStatusLabels[ orderData.orderStatus ];
		const orderStatusLocator = this.statusCombobox().filter( { hasText: orderStatusLabel } );
		await this.retryLocatorVisibility( orderStatusLocator );

		// if( orderData.orderStatus === 'processing' ) {
		// 	const orderNoteRegex = new RegExp(`Order status changed from .*? to ${ orderStatusLabel }\\.`);
		// 	const orderNoteLocator = this.orderNoteContent().filter( { hasText: orderNoteRegex } );
		// 	await this.retryLocatorVisibility( orderNoteLocator );
		// 	await expect( orderNoteLocator ).toBeVisible();
		// }

		await super.assertOrderDetails( orderId, orderData );

		if ( ! mollieData ) {
			return;
		}

		// Transaction ID
		if (
			mollieData.transaction_id !== undefined &&
			mollieData.orderTotal > 0
		) {
		}
	};

	/**
	 * TODO: needs update
	 * 
	 * @param amount 
	 * @param currency 
	 */
	assertRefundRequested = async ( amount: string, currency? ) => {
		const orderNote = this.orderNoteWithText(
			`${ WLOP_NAME }: Your refund request for ${ await formatMoney(
				Number( amount ),
				currency
			) } has been submitted and is pending approval.`
		);
		await this.retryLocatorVisibility( orderNote );
		await expect( orderNote ).toBeVisible();
	};

	/**
	 * TODO: needs update
	 * Asserts refund has been finished:
	 * - Order note received
	 * - Processed refund ID is present
	 * - Order status is expected
	 *
	 * @param mollieRefundId
	 * @param orderStatus
	 * @param amount
	 * @param currency
	 */
	assertRefundFinished = async (
		mollieRefundId: string,
		orderStatus: WooCommerce.OrderStatus,
		amount: string,
		currency?
	) => {
		const orderNote = this.orderNoteWithText(
			`${ WLOP_NAME }: ${ await formatMoney(
				Number( amount ),
				currency
			) } was refunded.`
		);
		const refundProcessedText = this.page.getByText(
			`Refund processed. ${ WLOP_NAME } transaction ID: ${ mollieRefundId }`
		);
		await this.retryLocatorVisibility( orderNote );
		await expect( orderNote ).toBeVisible();
		await expect( refundProcessedText ).toBeVisible();
		await this.assertOrderStatus( orderStatus );
	};

	retryLocatorVisibility = async ( locator, retries = 20 ) => {
		let i = 0;
		while ( i < retries ) {
			await this.page.reload();
			if ( await locator.isVisible() ) {
				return true;
			}
			await this.page.waitForTimeout( 1000 );
			i++;
		}
		return false;
	};
}
