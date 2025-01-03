/**
 * External dependencies
 */
import { WpPage, expect } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { urls } from '../urls';
import {
	BankIssuer,
	MolliePayment,
	MolliePaymentStatus,
} from '../../resources';

export class MollieHostedCheckout extends WpPage {
	url = urls.mollie.hostedCheckout;
	mollieUrlRegex = /mollie\.com\/checkout/;
	testModeUrlRegex = /checkout\/test-mode/;
	issuerSelectionUrlRegex = /select-issuer/;
	creditCardUrlRegex = /credit-card\/embedded/;
	expiredUrlRegex = /test-mode\/completed/;

	// Locators
	totalAmountHeader = () => this.page.locator( 'div.header__amount ' );
	orderNumberHeader = () => this.page.locator( 'div.header__info ' );
	selectIssuerButton = ( bankIssuer: BankIssuer ) =>
		this.page.locator( 'ul.payment-method-list' ).getByText( bankIssuer );
	paymentStatusRadio = ( paymentStatus: MolliePaymentStatus ) =>
		this.page.locator( `input[type="radio"][value="${ paymentStatus }"]` );
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
	payButton = () => this.page.locator( '#submit-button' );
	continueButton = () => this.page.locator( 'button[name="submit"]' );

	// Actions

	capturePaymentAmount = async () => {
		const amountText = await this.totalAmountHeader().textContent();
		const amount = parseFloat(
			amountText.replace( /[^0-9,.]/g, '' ).replace( ',', '.' )
		);

		return parseFloat( amount.toFixed( 2 ) );
	};

	captureOrderNumber = async () => {
		const orderText = await this.orderNumberHeader().textContent();
		const orderNumber = orderText.match( /\d+/ )?.[ 0 ];
		return parseInt( orderNumber );
	};

	payWithBank = async ( issuer: BankIssuer ) => {
		await this.page.waitForURL( this.issuerSelectionUrlRegex );
		await this.selectIssuerButton( issuer ).click();
		await this.page.waitForLoadState();
	};

	payWithCard = async ( card: WooCommerce.CreditCard ) => {
		await this.page.waitForURL( this.mollieUrlRegex );
		await this.cardNumberInput().fill( card.card_number );
		await this.cardHolderInput().fill( card.card_holder );
		await this.cardExpiryDateInput().fill( card.expiration_date );
		await this.cardVerificationCodeInput().fill( card.card_cvv );
		await this.payButton().click();
		await this.page.waitForLoadState();
	};

	pay = async ( payment: MolliePayment ) => {
		await this.page.waitForURL( this.mollieUrlRegex );
		await this.assertPaymentAmount( payment.amount );

		if ( payment.gateway.slug === 'ideal' ) {
			await this.payWithBank( payment.bankIssuer );
		}

		if (
			payment.gateway.slug === 'kbc' &&
			payment.gateway.settings.issuers_dropdown_shown === 'no'
		) {
			await this.payWithBank( payment.bankIssuer );
		}

		if (
			payment.gateway.slug === 'creditcard' &&
			payment.gateway.settings.mollie_components_enabled === 'no'
		) {
			await this.payWithCard( payment.card );
		}

		await this.page.waitForURL( this.testModeUrlRegex );
		const orderNumber = this.captureOrderNumber();
		await this.paymentStatusRadio( payment.status ).click();
		await this.continueButton().click();
		await this.page.waitForLoadState();
		if ( payment.status ) return orderNumber;
	};

	// Assertions
	assertUrl = async () => {
		await expect( this.page.url() ).toContain( this.url );
	};

	assertPaymentAmount = async ( expectedTotalAmount: number ) => {
		const totalAmount = await this.capturePaymentAmount();
		await expect( totalAmount ).toEqual( expectedTotalAmount );
	};
}
