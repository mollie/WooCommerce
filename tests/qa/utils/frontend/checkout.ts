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
	cardComponentsContainer = () =>
		this.page.locator(
			'#radio-control-wc-payment-method-options-mollie_wc_gateway_creditcard__content'
		);
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
		this.paymentOptionsContainer().locator(
			'#billing-phone-mollie_wc_gateway_in3'
		);
	in3BirthDateInput = (): Locator =>
		this.paymentOptionsContainer().locator( '#billing-birthdate' );
	rivertyBirthDateInput = (): Locator =>
		this.page.locator( '#billing-birthdate' );
	rivertyPhoneInput = (): Locator =>
		this.page.locator( '#billing-phone-mollie_wc_gateway_riverty' );
	vippsPhoneInput = () =>
		this.page.locator( '#billing-phone-mollie_wc_gateway_vipps' );
	mobilepayPhoneInput = () =>
		this.page.locator( '#billing-phone-mollie_wc_gateway_mobilepay' );
	paymentOptionLabel = ( slug ) =>
		this.paymentOptionsContainer().locator(
			`label[for="radio-control-wc-payment-method-options-mollie_wc_gateway_${ slug }"]`
		);

	paymentOptionLogo = ( name: string ): Locator =>
		this.paymentOptionsContainer()
			.locator( '.wc-block-components-radio-control__option', {
				has: this.page.getByText( name, { exact: true } ),
			} )
			.locator( 'img' );

	continueWithShippingButton = () =>
		this.page.getByRole( 'button', { name: 'Continue with Shipping' } );
	continueWithPaymentButton = () =>
		this.page.getByRole( 'button', { name: 'Continue with Payment' } );
	continueWithConfirmationButton = () =>
		this.page.getByRole( 'button', { name: 'Continue with Confirmation' } );
	termsAndConditionsCheckbox = () => this.page.locator( '#checkbox-legal' );

	// Actions

	/**
	 * Selects payment gateway and enters required data (if needed)
	 *
	 * @param order
	 */
	processPaymentMethod = async ( order: WooCommerce.ShopOrder ) => {
		const { payment, customer } = order;
		const { gateway, card } = payment;

		await expect(
			this.paymentOptionLabel( gateway.slug ),
			'Assert payment option label is visible'
		).toBeVisible();
		await this.paymentOptionLabel( gateway.slug ).click();
		await this.page.waitForLoadState( 'networkidle' );

		if (
			gateway.slug === 'kbc' &&
			gateway.settings.issuers_dropdown_shown === 'yes'
		) {
			await expect(
				this.kbcIssuerSelect(),
				'Assert KBC issuer select is visible'
			).toBeVisible();
			await this.kbcIssuerSelect().selectOption(
				order.payment.bankIssuer
			);
		}

		if ( gateway.slug === 'in3' ) {
			const phoneInput = this.in3PhoneInput();
			await expect(
				phoneInput,
				'Assert in3 phone input is visible'
			).toBeVisible();
			await phoneInput.fill( customer.billing.phone );

			const birthDateInput = this.in3BirthDateInput();
			await expect(
				birthDateInput,
				'Assert in3 birth date input is visible'
			).toBeVisible();
			await birthDateInput.click();
			for ( const char of customer.birth_date ) {
				await this.page.keyboard.type( char );
				await this.page.waitForTimeout( 100 );
			}
		}

		if ( gateway.slug === 'billie' ) {
			await expect(
				this.billieBillingCompanyInput(),
				'Assert billie billing company input is visible'
			).toBeVisible();
			await this.billieBillingCompanyInput().fill(
				order.payment.billingCompany
			);
		}

		if (
			gateway.slug === 'giftcard' &&
			gateway.settings.issuers_dropdown_shown === 'yes'
		) {
			await expect(
				this.giftCardSelect(),
				'Assert gift card select is visible'
			).toBeVisible();
			await this.giftCardSelect().selectOption( 'fashioncheque' );
		}

		if (
			gateway.slug === 'creditcard' &&
			gateway.settings.mollie_components_enabled !== 'no'
		) {
			// the card fields seem to be only rendered when they are scrolled in otherwise they are not visible
			await this.cardComponentsContainer().scrollIntoViewIfNeeded();

			await expect(
				this.cardNumberInput(),
				'Assert card number input is visible'
			).toBeVisible();
			await expect(
				this.cardHolderInput(),
				'Assert card holder input is visible'
			).toBeVisible();
			await expect(
				this.cardExpiryDateInput(),
				'Assert card expiry date input is visible'
			).toBeVisible();
			await expect(
				this.cardVerificationCodeInput(),
				'Assert card verification code input is visible'
			).toBeVisible();
			await expect
				.soft( this.page.getByText( 'Secure payments provided by' ) )
				.toBeVisible();

			await this.cardNumberInput().fill( card.card_number );
			await this.cardHolderInput().fill( card.card_holder );
			await this.cardExpiryDateInput().fill( card.expiration_date );
			await this.cardVerificationCodeInput().fill( card.card_cvv );
		}

		if ( gateway.slug === 'riverty' ) {
			const phoneInput = this.rivertyPhoneInput();
			await expect(
				phoneInput,
				'Assert riverty phone input is visible'
			).toBeVisible();
			await phoneInput.fill( customer.billing.phone );

			const birthDateInput = this.rivertyBirthDateInput();
			await expect(
				birthDateInput,
				'Assert riverty birth date input is visible'
			).toBeVisible();
			await birthDateInput.click();
			for ( const char of customer.birth_date ) {
				await this.page.keyboard.type( char );
				await this.page.waitForTimeout( 100 );
			}
		}

		if ( gateway.slug === 'vipps' ) {
			const phoneInput = this.vippsPhoneInput();
			await expect(
				phoneInput,
				'Assert vipps phone input is visible'
			).toBeVisible();
			await phoneInput.fill( customer.billing.phone );
		}

		if ( gateway.slug === 'mobilepay' ) {
			const phoneInput = this.mobilepayPhoneInput();
			await expect(
				phoneInput,
				'Assert mobilepay phone input is visible'
			).toBeVisible();
			await phoneInput.fill( customer.billing.phone );
		}
	};

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
		const { customer, coupons } = order;
		if ( customer.shipping.country !== 'IT' ) {
			// Clear state for countries where it is optional
			// Causes problems: in multistep checkout can be text input instead of select
			customer.shipping.state = '';
			customer.billing.state = '';
		}
		await this.visit();
		await this.applyCoupons( coupons );
		await this.page.waitForLoadState( 'networkidle' );
		await this.fillCheckoutForm( customer );
		await this.page.waitForTimeout( 1000 );
		await this.page.waitForLoadState( 'networkidle' );
		await expect(
			this.continueWithShippingButton(),
			'Assert continue with shipping button is visible'
		).toBeVisible();
		await this.continueWithShippingButton().click();
		await this.page.waitForLoadState();

		await this.selectShippingMethod( order.shipping.settings.title );
		await this.page.waitForTimeout( 1000 );
		await this.page.waitForLoadState( 'networkidle' );
		await expect(
			this.continueWithPaymentButton(),
			'Assert continue with payment button is visible'
		).toBeVisible();
		await this.continueWithPaymentButton().click();
		await this.page.waitForLoadState();

		await this.processPaymentMethod( order );
		await this.page.waitForTimeout( 1000 );
		await this.page.waitForLoadState( 'networkidle' );
		await expect(
			this.continueWithConfirmationButton(),
			'Assert continue with confirmation button is visible'
		).toBeVisible();
		await this.continueWithConfirmationButton().click();
		await this.page.waitForLoadState();

		// Terms and conditions checkbox was removed (found on 04.12.2025)
		// await expect( this.termsAndConditionsCheckbox(), 'Assert terms and conditions checkbox is visible' ).toBeVisible();
		// await this.termsAndConditionsCheckbox().check();

		await this.placeOrder();
	};

	// Assertions
}
