/**
 * External dependencies
 */
import {
	expect,
	PayForOrder as PayForOrderBase,
} from '@inpsyde/playwright-utils/build';

export class PayForOrder extends PayForOrderBase {
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
	billieBillingCompanyInput = () =>
		this.page.locator( '#billing_company_billie' );
	in3PhoneInput = () => this.page.locator( '#billing_phone_in3' );
	in3BirthDateInput = () => this.page.locator( '#billing_birthdate_in3' );
	rivertyBirthDateInput = () =>
		this.page.locator( '#billing_birthdate_riverty' );
	rivertyPhoneInput = () => this.page.locator( '#billing_phone_riverty' );

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
				payment.billingCompany
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

		await this.payForOrderButton().click();
	};

	// Assertions
}
