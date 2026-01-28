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
	cardComponentsContainer = () =>
		this.page.locator(
			'.payment_method_mollie_wc_gateway_creditcard .mollie-components'
		);
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
	vippsPhoneInput = () => this.page.locator( '#billing_phone_vipps' );
	mobilepayPhoneInput = () => this.page.locator( '#billing_phone_mobilepay' );
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
	paymentOptionLabel = ( slug: string ): Locator =>
		this.paymentOptionsContainer().locator(
			`label[for="payment_method_mollie_wc_gateway_${ slug }"]`
		);

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

		await expect(
			this.paymentOptionLabel( gateway.slug ),
			'Assert payment option label is visible'
		).toBeVisible();
		await this.paymentOptionLabel( gateway.slug ).click();
		// await this.page.waitForLoadState( 'networkidle' );
		await this.page.waitForTimeout( 2500 ); // couldn't overcome timeout issues with networkidle

		if (
			gateway.slug === 'kbc' &&
			gateway.settings.issuers_dropdown_shown === 'yes'
		) {
			await expect(
				this.kbcIssuerSelect(),
				'Assert KBC issuer select is visible'
			).toBeVisible();
			await this.kbcIssuerSelect().click();
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
		await expect(
			this.placeOrderButton(),
			'Assert place order button is visible'
		).toBeVisible();
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
			this.continueWithStep2Button(),
			'Assert continue with step 2 button is visible'
		).toBeVisible();
		await this.continueWithStep2Button().click();
		await this.page.waitForLoadState();

		await this.processPaymentMethod( order );
		await this.page.waitForTimeout( 1000 );
		await this.page.waitForLoadState( 'networkidle' );
		await expect(
			this.continueWithStep3Button(),
			'Assert continue with step 3 button is visible'
		).toBeVisible();
		await this.continueWithStep3Button().click();
		await this.page.waitForLoadState();

		await this.selectShippingMethod( order.shipping.settings.title );
		await this.page.waitForTimeout( 1000 );
		await this.page.waitForLoadState( 'networkidle' );
		await expect(
			this.termsAndConditionsCheckbox(),
			'Assert terms and conditions checkbox is visible'
		).toBeVisible();
		await this.termsAndConditionsCheckbox().check();

		await this.placeOrder();
	};

	// Assertions

	assertPaymentOptionLabel = async (
		slug: string,
		name: string,
		options: { isSoftAssertion?: boolean } = { isSoftAssertion: false }
	) => {
		const paymentOptionLabel = this.paymentOptionLabel( slug );
		const expectFn = options.isSoftAssertion ? expect.soft : expect;

		await expectFn( paymentOptionLabel ).toBeVisible();
		await expectFn( paymentOptionLabel ).toHaveText(
			new RegExp( `^\\s*${ name }\\s*$` )
		);
	};
}
