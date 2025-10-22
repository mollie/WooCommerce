/**
 * External dependencies
 */
import { Locator } from '@playwright/test';
import {
	expect,
	Checkout as CheckoutBase,
} from '@inpsyde/playwright-utils/build';

export class Checkout extends CheckoutBase {
	// Locators
	cardNumberInput = () =>
		this.page
			.frameLocator( '[title="cardNumber input"]' )
			.locator( '#cardNumber' );
	cardHolderInput = () =>
		this.page
			.frameLocator( '[title="cardHolder input"]' )
			.locator( '#cardHolder' );
	cardExpiryDateInput = () =>
		this.page
			.frameLocator( '[title="expiryDate input"]' )
			.locator( '#expiryDate' );
	cardVerificationCodeInput = () =>
		this.page
			.frameLocator( '[title="verificationCode input"]' )
			.locator( '#verificationCode' );
	giftCardSelect = () =>
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
		this.paymentOptionsContainer().locator( '#billing-phone' );
	in3BirthDateInput = (): Locator =>
		this.paymentOptionsContainer().locator( '#billing-birthdate' );
	rivertyBirthDateInput = (): Locator =>
		this.page.locator( '#billing-birthdate' );
	rivertyPhoneInput = (): Locator =>
		this.page.locator( '#billing-phone-riverty' );
	paymentOptionLogo = ( name: string ): Locator =>
		this.paymentOptionsContainer()
			.locator( '.wc-block-components-radio-control__option', {
				has: this.page.getByText( name, { exact: true } )
			} )
			.locator( 'img' );

	continueWithShippingButton = () =>
		this.page.getByRole( 'button', { name: 'Continue with Shipping' } );
	continueWithPaymentButton = () =>
		this.page.getByRole( 'button', { name: 'Continue with Payment' } );
	continueWithConfirmationButton = () =>
		this.page.getByRole( 'button', { name: 'Continue with Confirmation' } );
	termsAndConditionsCheckbox = () =>
		this.page.locator( '#checkbox-legal' );

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
			// the card fields seem to be only rendered when they are scrolled in otherwise they are not visible
			await this.page.locator(
				'#radio-control-wc-payment-method-options-mollie_wc_gateway_creditcard__content'
			).scrollIntoViewIfNeeded();
			
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
	 * Makes order on Checkout:
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
		await this.placeOrder();
	};

	/**
	 * Makes order on multistep Checkout
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
		await expect( this.continueWithShippingButton() ).toBeVisible();
		await this.continueWithShippingButton().click();

		await this.selectShippingMethod( order.shipping.settings.title );
		await expect( this.continueWithPaymentButton() ).toBeVisible();
		await this.continueWithPaymentButton().click();

		await this.processPaymentMethod( order );
		await expect( this.continueWithConfirmationButton() ).toBeVisible();
		await this.continueWithConfirmationButton().click();

		await expect( this.termsAndConditionsCheckbox() ).toBeVisible();
		await this.termsAndConditionsCheckbox().check();

		await this.placeOrder();
	};

	// Assertions
}
