/**
 * External dependencies
 */
import { Locator } from '@playwright/test';
import { Product as ProductBase } from '@inpsyde/playwright-utils/build';

export class Product extends ProductBase {
	// Locators
	payPalExpressButton = (): Locator =>
		this.page
			.locator( '#mollie-PayPal-button' )
			.locator( 'input[type="image"], button' );
}
