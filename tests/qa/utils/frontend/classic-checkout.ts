/**
 * External dependencies
 */
import { Locator } from '@playwright/test';
import { ClassicCheckout as ClassicCheckoutBase } from '@inpsyde/playwright-utils/build';

export class ClassicCheckout extends ClassicCheckoutBase {
	// Locators
	cardNumberInput = (): Locator =>
		this.page
			.frameLocator( '[title="cardNumber input"]' )
			.locator( '#cardNumber' );
	cardHolderInput = (): Locator =>
		this.page
			.frameLocator( '[title="cardHolder input"]' )
			.locator( '#cardHolder' );
	cardExpiryDateInput = (): Locator =>
		this.page
			.frameLocator( '[title="expiryDate input"]' )
			.locator( '#expiryDate' );
	cardVerificationCodeInput = (): Locator =>
		this.page
			.frameLocator( '[title="verificationCode input"]' )
			.locator( '#verificationCode' );
	giftCardSelect = (): Locator =>
		this.page.locator(
			'select[name="mollie-payments-for-woocommerce_issuer_mollie_wc_gateway_giftcard"]'
		);
	kbcIssuerSelect = () =>
		this.page.locator(
			'select[name="mollie-payments-for-woocommerce_issuer_mollie_wc_gateway_kbc"]'
		);
	billieBillingCompanyInput = (): Locator =>
		this.paymentOptionsContainer().locator( '#billing_company_billie' );
	in3PhoneInput = (): Locator =>
		this.paymentOptionsContainer().locator( '#billing_phone_in3' );
	in3BirthDateInput = (): Locator =>
		this.paymentOptionsContainer().locator( '#billing_birthdate_in3' );
	paymentOptionListitems = (): Locator =>
		this.paymentOptionsContainer().locator( 'li' );
	paymentOptionFee = ( name: string ): Locator =>
		this.paymentOptionsContainer()
			.locator( 'li', {
				has: this.page.locator( `label:text-is("${ name }")` ),
			} )
			.locator( 'p.mollie-gateway-fee' );
	paymentOptionLogo = ( name: string ): Locator =>
		this.paymentOptionsContainer()
			.locator( 'li', {
				has: this.page.locator( `label:text-is("${ name }")` ),
			} )
			.locator( 'img.mollie-gateway-icon' );

	// Actions

	/**
	 * Makes order on Classic Checkout:
	 * - fills checkout form
	 * - selects shipping method
	 * - selects gateway
	 * - clicks Place Order button
	 *
	 * @param data
	 */
	makeOrder = async ( data: WooCommerce.ShopOrder ) => {
		await this.visit();
		await this.applyCoupons( data.coupons );
		await this.fillCheckoutForm( data.customer );
		await this.selectShippingMethod( data.shipping.settings.title );
		await this.paymentOption( data.payment.gateway.name ).click();
		if (
			data.payment.gateway.slug === 'kbc' &&
			data.payment.gateway.settings.issuers_dropdown_shown === 'yes'
		) {
			await this.kbcIssuerSelect().selectOption(
				data.payment.bankIssuer
			);
		}

		if ( data.payment.gateway.slug === 'in3' ) {
			const phoneInput = this.in3PhoneInput();
			if ( await phoneInput.isVisible() ) {
				await phoneInput.fill( data.customer.billing.phone );
			}
			const birthDateInput = this.in3BirthDateInput();
			if ( await birthDateInput.isVisible() ) {
				await birthDateInput.click();
				for ( const char of data.customer.birth_date ) {
					await this.page.keyboard.type( char );
					await this.page.waitForTimeout( 100 );
				}
			}
		}

		if (
			data.payment.gateway.slug === 'billie' &&
			( await this.billieBillingCompanyInput().isVisible() )
		) {
			await this.billieBillingCompanyInput().fill(
				data.payment.billingCompany
			);
		}
		if (
			data.payment.gateway.slug === 'giftcard' &&
			data.payment.gateway.settings.issuers_dropdown_shown === 'yes' &&
			( await this.giftCardSelect().isVisible() )
		) {
			await this.giftCardSelect().selectOption( 'fashioncheque' );
		}
		if (
			data.payment.gateway.slug === 'creditcard' &&
			data.payment.gateway.settings.mollie_components_enabled === 'yes'
		) {
			await this.cardNumberInput().fill( data.payment.card.card_number );
			await this.cardHolderInput().fill( data.payment.card.card_holder );
			await this.cardExpiryDateInput().fill(
				data.payment.card.expiration_date
			);
			await this.cardVerificationCodeInput().fill(
				data.payment.card.card_cvv
			);
		}
		await this.placeOrder();
	};

	// Assertions
}
