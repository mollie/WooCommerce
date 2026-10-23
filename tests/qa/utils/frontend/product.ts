/**
 * External dependencies
 */
import { Locator } from '@playwright/test';
import { Product as ProductBase } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { urls } from '../urls';

export class Product extends ProductBase {
	// Locators
	payPalExpressButton = (): Locator =>
		this.page
			.locator( '#mollie-PayPal-button' )
			.locator( 'input[type="image"], button' );
	payPalExpressButtonNonceInput = (): Locator =>
		this.page.locator( '#mollie-PayPal-button input[name="_wpnonce"]' );

	// Actions

	/**
	 * Sends a PayPal button AJAX action as the visitor of this page
	 *
	 * @param action               mollie_paypal_update_amount or mollie_paypal_create_order
	 * @param data
	 * @param data.productId
	 * @param data.productQuantity
	 * @param data.nonce
	 */
	postPayPalButtonAction = async (
		action: string,
		data: { productId: number; productQuantity: number; nonce: string }
	) => {
		const response = await this.page.request.post( urls.admin.ajax, {
			form: { action, ...data },
		} );
		return response.json();
	};

	// Assertions
}
