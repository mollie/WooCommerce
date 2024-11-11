/**
 * External dependencies
 */
import { Checkout as CheckoutBase } from '@inpsyde/playwright-utils/build';

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
	billieBillingCompanyInput = () =>
		this.paymentOptionsContainer().locator( '#billing-company' );
	in3PhoneInput = () =>
		this.paymentOptionsContainer().locator( '#billing-phone' );
	in3BirthDateInput = () =>
		this.paymentOptionsContainer().locator( '#billing-birthdate' );

	// Actions

	/**
	 * Makes order on Checkout:
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
			data.payment.gateway.settings.kbcShowBanksDropdown
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
			data.payment.gateway.settings.giftcardsShowDropdown &&
			( await this.giftCardSelect().isVisible() )
		) {
			await this.giftCardSelect().selectOption( 'fashioncheque' );
		}

		if (
			data.payment.gateway.slug === 'creditcard' &&
			data.payment.gateway.settings.enableMollieComponents
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