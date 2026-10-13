/**
 * External dependencies
 */
import { Locator } from '@playwright/test';
import { Cart as CartBase } from '@inpsyde/playwright-utils/build';

export class Cart extends CartBase {
	// Locators
	payPalExpressButton = (): Locator =>
		this.page
			.locator( '#mollie-PayPal-button' )
			.locator( 'input[type="image"], button' );
}
