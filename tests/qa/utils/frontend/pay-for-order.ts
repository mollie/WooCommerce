/**
 * External dependencies
 */
import {
	expect,
	PayForOrder as PayForOrderBase,
} from '@inpsyde/playwright-utils/build';

export class PayForOrder extends PayForOrderBase {
	// Locators
	cardComponentsContainer = () =>
		this.page.locator(
			'.payment_method_mollie_wc_gateway_creditcard .mollie-components'
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
	billieBillingCompanyInput = () =>
		this.page.locator( '#billing_company_billie' );
	in3PhoneInput = () => this.page.locator( '#billing_phone_in3' );
	in3BirthDateInput = () => this.page.locator( '#billing_birthdate_in3' );
	rivertyBirthDateInput = () =>
		this.page.locator( '#billing_birthdate_riverty' );
	rivertyPhoneInput = () => this.page.locator( '#billing_phone_riverty' );
	vippsPhoneInput = () => this.page.locator( '#billing_phone_vipps' );
	mobilepayPhoneInput = () => this.page.locator( '#billing_phone_mobilepay' );

	// Actions

	/**
	 * Makes order on Pay for order page:
	 * - selects gateway
	 * - clicks Place Order button
	 *
	 * @param orderId
	 * @param orderKey
	 * @param order
	 */
	makeOrder = async (
		orderId: number,
		orderKey: string,
		order: WooCommerce.ShopOrder
	) => {
		const { payment, customer } = order;
		const { gateway, card } = payment;
		await this.visit( orderId, orderKey );
		await expect( this.paymentOption( gateway.name ) ).toBeVisible();
		await this.paymentOption( gateway.name ).click();
		await this.page.waitForLoadState( 'networkidle' );

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
			await expect( phoneInput ).toBeVisible();
			await phoneInput.fill( customer.billing.phone );

			const birthDateInput = this.in3BirthDateInput();
			await expect( birthDateInput ).toBeVisible();
			await birthDateInput.click();
			for ( const char of customer.birth_date ) {
				await this.page.keyboard.type( char );
				await this.page.waitForTimeout( 100 );
			}
		}

		if ( gateway.slug === 'billie' ) {
			await expect( this.billieBillingCompanyInput() ).toBeVisible();
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
			await this.cardComponentsContainer().scrollIntoViewIfNeeded();

			await expect( this.cardNumberInput() ).toBeVisible();
			await expect( this.cardHolderInput() ).toBeVisible();
			await expect( this.cardExpiryDateInput() ).toBeVisible();
			await expect( this.cardVerificationCodeInput() ).toBeVisible();
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
			await expect( phoneInput ).toBeVisible();
			await phoneInput.fill( customer.billing.phone );
			
			const birthDateInput = this.rivertyBirthDateInput();
			await expect( birthDateInput ).toBeVisible();
			await birthDateInput.click();
			for ( const char of customer.birth_date ) {
				await this.page.keyboard.type( char );
				await this.page.waitForTimeout( 100 );
			}
		}

		if ( gateway.slug === 'vipps' ) {
			const phoneInput = this.vippsPhoneInput();
			await expect( phoneInput ).toBeVisible();
			await phoneInput.fill( customer.billing.phone );
		}

		if ( gateway.slug === 'mobilepay' ) {
			const phoneInput = this.mobilepayPhoneInput();
			await expect( phoneInput ).toBeVisible();
			await phoneInput.fill( customer.billing.phone );
		}

		await this.payForOrderButton().click();
	};

	// Assertions
}
