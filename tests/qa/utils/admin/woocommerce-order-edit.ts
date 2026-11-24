/**
 * External dependencies
 */
import { type Dialog, Locator, expect } from '@playwright/test';
import { WooCommerceOrderEdit as wooCommerceOrderEditBase } from '@inpsyde/playwright-utils/build';

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
	 * Refunds via the Mollie gateway in the open refund box.
	 * Puts the whole amount (default: all available) into the first line item.
	 * Fails on the WooCommerce error alert.
	 * TODO: replace with pw-utils refundOrder() once released.
	 *
	 * @param gatewayName
	 * @param amount
	 */
	makeRefund = async ( gatewayName: string, amount?: string ) => {
		if ( ! amount ) {
			const totalAmount =
				( await this.totalAvailableToRefund().textContent() ) || '';
			amount = totalAmount.replace( /[^\d.-]+/g, '' );
		}
		if ( Number.isNaN( Number( amount ) ) ) {
			throw new Error(
				`Refund amount "${ amount }" is not a number (only the 1,234.56 price format is supported)`
			);
		}
		// Round once, so fill() and the poll below use the same value
		const refundAmount = Number( Number( amount ).toFixed( 2 ) );
		if ( refundAmount <= 0 ) {
			throw new Error(
				`Refund amount "${ amount }" must be greater than 0`
			);
		}

		const lineTotalInput = this.firstRefundTotalInput();
		await lineTotalInput.fill( refundAmount.toFixed( 2 ) );
		// Blur fires change, which updates #refund_amount
		await lineTotalInput.blur();
		await expect
			.poll(
				async () =>
					Number(
						( await this.refundAmountInput().inputValue() ).replace(
							',',
							'.'
						)
					),
				{ message: 'Assert refund amount is updated' }
			)
			.toBe( refundAmount );

		const refundError = await this.submitRefund( gatewayName );
		expect(
			refundError,
			`Assert refund via ${ gatewayName } has no error`
		).toBe( '' );
	};

	/**
	 * Clicks "Refund via gatewayName" in the open refund box.
	 * Returns the WooCommerce error alert text, or '' when the page reloads (refund saved).
	 *
	 * @param gatewayName
	 */
	submitRefund = async ( gatewayName: string ): Promise< string > => {
		const refundViaMollieButton = this.refundViaMollieButton( gatewayName );
		await expect(
			refundViaMollieButton,
			`Assert refund via ${ gatewayName } button is visible`
		).toBeVisible();

		// Success reloads the page, an error shows alert()
		const pageReload = this.page
			.waitForEvent( 'load', { timeout: 60_000 } )
			.then( () => '' )
			.catch( () => 'No page reload after refund' );
		let onDialog: ( dialog: Dialog ) => Promise< void >;
		const errorAlert = new Promise< string >( ( resolve ) => {
			onDialog = async ( dialog ) => {
				const isAlert = dialog.type() === 'alert';
				const message = dialog.message();
				await dialog.accept();
				if ( isAlert ) {
					resolve( message );
				}
			};
			this.page.on( 'dialog', onDialog );
		} );
		try {
			await refundViaMollieButton.click();
			return await Promise.race( [ pageReload, errorAlert ] );
		} finally {
			this.page.off( 'dialog', onDialog );
		}
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
	 * Asserts the order status combobox value on the order edit page.
	 * Retries every 5 sec during 1 min.
	 *
	 * @param expectedStatus
	 */
	assertOrderStatus = async (
		expectedStatus: WooCommerce.OrderStatus = 'processing'
	) => {
		const orderStatus = this.getOrderStatusLabel( expectedStatus );
		let isFirstAttempt = true;

		await expect
			.soft( async () => {
				if ( isFirstAttempt ) {
					isFirstAttempt = false;
				} else {
					await this.page.reload();
				}
				// Not soft: toPass() retries only when the inner assertion throws
				await expect(
					this.statusCombobox(),
					'Assert order status combobox'
				).toHaveText( orderStatus );
			}, 'Assert order status combobox with retries' )
			.toPass( {
				timeout: 60_000,
				intervals: [ 5_000 ],
			} );
	};

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
		await expect
			.soft(
				this.paymentVia( gateway.name ),
				'Assert payment via method'
			)
			.toBeVisible();

		// Transaction ID
		if ( transactionId ) {
			await expect
				.soft(
					this.transactionIdLink( transactionId ),
					'Assert transaction ID link'
				)
				.toBeVisible();
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
