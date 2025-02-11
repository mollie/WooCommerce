/**
 * External dependencies
 */
import { PayForOrder as PayForOrderBase } from '@inpsyde/playwright-utils/build';

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
	billieBillingCompanyInput = () => this.page.locator( '#billing_company_billie' );
	in3PhoneInput = () => this.page.locator( '#billing_phone_in3' );
	in3BirthDateInput = () => this.page.locator( '#billing_birthdate_in3' );

	// Actions

	/**
	 * Makes order on Pay for order page:
	 * - selects gateway
	 * - clicks Place Order button
	 *
	 * @param orderId
	 * @param orderKey
	 * @param data
	 */
	makeOrder = async (
		orderId: number,
		orderKey: string,
		data: WooCommerce.ShopOrder
	) => {
		await this.visit( orderId, orderKey );
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
			data.payment.gateway.settings.issuers_dropdown_shown === 'yes'
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

		await this.payForOrderButton().click();
	};

	// Assertions
}
