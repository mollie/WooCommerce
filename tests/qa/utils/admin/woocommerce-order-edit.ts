/**
 * External dependencies
 */
import { expect } from '@playwright/test';
import {
	WooCommerceOrderEdit as wooCommerceOrderEditBase,
	formatMoney,
} from '@inpsyde/playwright-utils/build';

const { WLOP_NAME } = process.env;

export class WooCommerceOrderEdit extends wooCommerceOrderEditBase {
	// Locators
	transactionIdText = ( transactionId ) =>
		this.orderNumberContainer().getByText( `(${ transactionId })` );
	billingDataTransactionIdInput = () =>
		this.billingDataContainer().getByLabel( 'Transaction ID' );

	refundViaWorldlineButton = () =>
		this.page.locator( '.do-api-refund', { hasText: WLOP_NAME } );

	productsTable = () => this.page.locator( '#order_line_items' );
	productRow = ( name ) => this.productsTable().getByRole( 'row', { name } );
	productRefundQtyInput = ( name ) =>
		this.productRow( name ).locator( '.refund_order_item_qty' );
	productRefundTotalInput = ( name ) =>
		this.productRow( name ).locator( '.refund_line_total' );
	productRefundTaxInput = ( name ) =>
		this.productRow( name ).locator( '.refund_line_tax' );

	firstRefundTotalInput = () =>
		this.productsTable().locator( '.refund_line_total' ).first();

	shippingTable = () => this.page.locator( '#order_shipping_line_items' );
	shippingRow = ( name ) => this.shippingTable().getByRole( 'row', { name } );
	shippingRefundTotalInput = ( name ) =>
		this.shippingRow( name ).locator( '.refund_line_total' );
	shippingRefundTaxInput = ( name ) =>
		this.shippingRow( name ).locator( '.refund_line_tax' );

	totalWorldlineRefunded = () =>
		this.totalsTableRow( `${ WLOP_NAME } Refunded:` );
	totalWorldlineNetTotal = () =>
		this.totalsTableRow( `${ WLOP_NAME } Net Total:` );

	// Actions
	/**
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
		await this.refundViaWorldlineButton().click();
	};

	/**
	 * Performs Worldline refund for specific product
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
		await this.refundViaWorldlineButton().click();
	};

	// Assertions

	/**
	 * Asserts data provided on the order page
	 *
	 * @param orderId
	 * @param orderData
	 * @param millieData
	 */
	assertOrderDetails = async (
		orderId: number,
		orderData: WooCommerce.ShopOrder,
		millieData?
	) => {
		await super.assertOrderDetails( orderId, orderData );

		if ( ! millieData ) {
			return;
		}

		// Transaction ID
		if (
			millieData.transaction_id !== undefined &&
			millieData.orderTotal > 0
		) {
		}
	};

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
	 * Asserts refund has been finished:
	 * - Order note received
	 * - Processed refund ID is present
	 * - Order status is expected
	 *
	 * @param wlopRefundId
	 * @param orderStatus
	 * @param amount
	 * @param currency
	 */
	assertRefundFinished = async (
		wlopRefundId: string,
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
			`Refund processed. ${ WLOP_NAME } transaction ID: ${ wlopRefundId }`
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
