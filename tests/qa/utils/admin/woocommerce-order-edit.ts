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
	paymentVia = ( method ) =>
		this.orderNumberContainer().getByText( `Payment via ${ method }` );
	transactionIdLink = ( transactionId ) =>
		this.orderNumberContainer().getByRole( 'link', {
			name: transactionId,
		} );
	transactionIdText = ( transactionId ): Locator =>
		this.orderNumberContainer().getByText( `(${ transactionId })` );
	billingDataTransactionIdInput = (): Locator =>
		this.billingDataContainer().getByLabel( 'Transaction ID' );

	refundViaMollieButton = ( gatewayName: string ) =>
		this.page.locator( '.do-api-refund', {
			hasText: new RegExp( `Refund .* via ${ gatewayName }` ),
		} );

	productsTable = () => this.page.locator( '#order_line_items' );
	productRow = ( name ): Locator =>
		this.productsTable().getByRole( 'row', { name } );
	productRefundQtyInput = ( name ): Locator =>
		this.productRow( name ).locator( '.refund_order_item_qty' );
	productRefundTotalInput = ( name ): Locator =>
		this.productRow( name ).locator( '.refund_line_total' );
	productRefundTaxInput = ( name ): Locator =>
		this.productRow( name ).locator( '.refund_line_tax' );

	firstRefundTotalInput = (): Locator =>
		this.productsTable().locator( '.refund_line_total' ).first();

	shippingTable = () => this.page.locator( '#order_shipping_line_items' );
	shippingRow = ( name ): Locator =>
		this.shippingTable().getByRole( 'row', { name } );
	shippingRefundTotalInput = ( name ): Locator =>
		this.shippingRow( name ).locator( '.refund_line_total' );
	shippingRefundTaxInput = ( name ): Locator =>
		this.shippingRow( name ).locator( '.refund_line_tax' );

	totalWorldlineRefunded = (): Locator =>
		this.totalsTableRow( `${ WLOP_NAME } Refunded:` );
	totalWorldlineNetTotal = (): Locator =>
		this.totalsTableRow( `${ WLOP_NAME } Net Total:` );

	// Actions

	/**
	 * Performs Mollie refund
	 *
	 * @param gatewayName
	 * @param amount
	 */
	makeRefund = async ( gatewayName: string, amount?: string ) => {
		const refundViaMollieButton = this.refundViaMollieButton( gatewayName );
		await expect(
			refundViaMollieButton,
			`Assert refund via ${ gatewayName } button is visible`
		).toBeVisible();
		if ( ! amount ) {
			const totalAmount =
				( await this.totalAvailableToRefund().textContent() ) || '';
			amount = parseFloat(
				totalAmount.replace( /[^\d.-]+/g, '' ).trim()
			).toFixed( 2 );
		}
		await this.firstRefundTotalInput().fill( amount );
		await this.page.on( 'dialog', ( dialog ) => dialog.accept() );
		await refundViaMollieButton.click();
		await this.page.waitForLoadState( 'networkidle' );
	};

	/**
	 * TODO: needs update
	 * Performs Mollie refund for specific product
	 *
	 * @param gatewayName
	 * @param productName
	 * @param qty
	 */
	makeRefundForProduct = async (
		gatewayName: string,
		productName: string,
		qty: number = 1
	) => {
		await this.refundButton().click();
		await this.lineItemRefundQuantityInput( productName ).fill(
			String( qty )
		);
		await this.page.on( 'dialog', ( dialog ) => dialog.accept() );
		// await this.page.on('dialog', dialog => dialog.accept());
		await this.refundViaMollieButton( gatewayName ).click();
	};

	// Assertions

	/**
	 * Asserts order edit page including PayPal related fields
	 *
	 * @param orderData
	 * @param transactionId
	 */
	assertOrderDetails = async (
		orderData: WooCommerce.ShopOrder,
		transactionId?: string
	) => {
		await super.assertOrderDetails( orderData );

		const { gateway } = orderData.payment;

		// Payment via text
		await expect(
			this.paymentVia( gateway.name ),
			'Assert payment via method'
		).toBeVisible();

		// Transaction ID
		if ( transactionId ) {
			await expect(
				this.transactionIdLink( transactionId ),
				'Assert transaction ID link'
			).toBeVisible();
		}
	};

	retryLocatorVisibility = async ( locator, retries = 5 ) => {
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
