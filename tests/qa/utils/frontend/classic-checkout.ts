/**
 * External dependencies
 */
import { Locator } from '@playwright/test';
import {
	expect,
	ClassicCheckout as ClassicCheckoutBase,
} from '@inpsyde/playwright-utils/build';

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
	rivertyBirthDateInput = (): Locator =>
		this.page.locator( '#billing_birthdate_riverty' );
	rivertyPhoneInput = () => this.page.locator( '#billing_phone_riverty' );
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

	continueWithStep2Button = () => this.page.locator( '#next-step-address' );
	continueWithStep3Button = () => this.page.locator( '#next-step-payment' );
	termsAndConditionsCheckbox = () => this.page.locator( '#legal' );

	// Actions
	
	/**
	 * Selects payment gateway and enters required data (if needed)
	 * 
	 * @param order 
	 */
	processPaymentMethod = async ( order: WooCommerce.ShopOrder ) => {
		const { payment, customer } = order;
		const { gateway, card } = payment;

		await expect( this.paymentOption( gateway.name ) ).toBeVisible();
		await this.paymentOption( gateway.name ).click();

		if (
			gateway.slug === 'kbc' &&
			gateway.settings.issuers_dropdown_shown === 'yes'
		) {
			await expect( this.kbcIssuerSelect() ).toBeVisible();
			await this.kbcIssuerSelect().click();
			await this.kbcIssuerSelect().selectOption(
				order.payment.bankIssuer
			);
		}

		if ( gateway.slug === 'in3' ) {
			const phoneInput = this.in3PhoneInput();
			if ( await phoneInput.isVisible() ) {
				await phoneInput.fill( customer.billing.phone );
			}
			const birthDateInput = this.in3BirthDateInput();
			if ( await birthDateInput.isVisible() ) {
				await birthDateInput.click();
				for ( const char of customer.birth_date ) {
					await this.page.keyboard.type( char );
					await this.page.waitForTimeout( 100 );
				}
			}
		}

		if (
			gateway.slug === 'billie' &&
			( await this.billieBillingCompanyInput().isVisible() )
		) {
			await this.billieBillingCompanyInput().fill(
				order.payment.billingCompany
			);
		}

		if (
			gateway.slug === 'giftcard' &&
			gateway.settings.issuers_dropdown_shown === 'yes'
		) {
			await expect( this.giftCardSelect() ).toBeVisible();
			await this.giftCardSelect().selectOption( 'fashioncheque' );
		}

		if (
			gateway.slug === 'creditcard' &&
			gateway.settings.mollie_components_enabled !== 'no'
		) {
			await expect.soft( this.page.getByText( 'Secure payments provided by' ) ).toBeVisible();

			await expect( this.cardNumberInput() ).toBeVisible();
			await expect( this.cardHolderInput() ).toBeVisible();
			await expect( this.cardExpiryDateInput() ).toBeVisible();
			await expect( this.cardVerificationCodeInput() ).toBeVisible();

			await this.cardNumberInput().fill( card.card_number );
			await this.cardHolderInput().fill( card.card_holder );
			await this.cardExpiryDateInput().fill( card.expiration_date );
			await this.cardVerificationCodeInput().fill( card.card_cvv );
		}

		if ( gateway.slug === 'riverty' ) {
			const phoneInput = this.rivertyPhoneInput();
			if ( await phoneInput.isVisible() ) {
				await phoneInput.fill( customer.billing.phone );
			}
			const birthDateInput = this.rivertyBirthDateInput();
			if ( await birthDateInput.isVisible() ) {
				await birthDateInput.click();
				for ( const char of customer.birth_date ) {
					await this.page.keyboard.type( char );
					await this.page.waitForTimeout( 100 );
				}
			}
		}
	}

	/**
	 * Makes order on Classic checkout:
	 * - fills checkout form
	 * - selects shipping method
	 * - selects gateway
	 * - clicks Place Order button
	 *
	 * @param order
	 */
	makeOrder = async ( order: WooCommerce.ShopOrder ) => {
		const { customer, coupons } = order;
		await this.visit();
		await this.applyCoupons( coupons );
		await this.fillCheckoutForm( customer );
		await this.selectShippingMethod( order.shipping.settings.title );
		await this.processPaymentMethod( order );
		await this.page.waitForLoadState( 'networkidle' );
		await expect( this.placeOrderButton() ).toBeVisible();
		await this.placeOrderButton().click( { force: true } );
		await this.page.waitForLoadState();
		// await this.placeOrder();
	};

	/**
	 * Makes order on multistep Classic checkout
	 *
	 * @param order
	 */
	makeMultistepOrder = async ( order: WooCommerce.ShopOrder ) => {
		const {  customer, coupons } = order;
		if( customer.shipping.country !== 'IT' ) {
			// Clear state for countries where it is optional
			// Causes problems: in multistep checkout can be text input instead of select
			customer.shipping.state = '';
			customer.billing.state = '';
		}
		await this.visit();
		await this.applyCoupons( coupons );
		await this.fillCheckoutForm( customer );
		await this.page.waitForTimeout( 1000 );
		await expect( this.continueWithStep2Button() ).toBeVisible();
		await this.continueWithStep2Button().click();

		await this.processPaymentMethod( order );
		await this.page.waitForTimeout( 1000 );
		await expect( this.continueWithStep3Button() ).toBeVisible();
		await this.continueWithStep3Button().click();

		await this.selectShippingMethod( order.shipping.settings.title );
		await this.page.waitForTimeout( 1000 );
		await expect( this.termsAndConditionsCheckbox() ).toBeVisible();
		await this.termsAndConditionsCheckbox().check();

		await this.placeOrder();
	};

	// Assertions
}
