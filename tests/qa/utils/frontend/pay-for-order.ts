/**
 * External dependencies
 */
import { APIRequestContext, Response } from '@playwright/test';
import {
	expect,
	PayForOrder as PayForOrderBase,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { urls } from '../urls';

type SurchargeRequest = { orderKey: string; nonce: string; method: string };

const isSurchargeCall = ( response: Response ) =>
	response.url().includes( 'admin-ajax.php' ) &&
	!! response.request().postData()?.includes( 'update_surcharge_order_pay' );

export class PayForOrder extends PayForOrderBase {
	// Locators
	paymentOptionLabel = ( slug: string ) =>
		this.paymentOptionsContainer().locator(
			`label[for="payment_method_mollie_wc_gateway_${ slug }"]`
		);
	// Any gateway, also non-Mollie ones like bacs
	paymentMethodLabel = ( gatewayId: string ) =>
		this.paymentOptionsContainer().locator(
			`label[for="payment_method_${ gatewayId }"]`
		);
	orderReviewFeeRow = ( feeName: string ) =>
		this.page.locator( '#order_review tfoot tr', { hasText: feeName } );
	surchargeOrderKeyInput = () =>
		this.page
			.locator( 'input[name="mollie-woocommerce-orderKey"]' )
			.first();
	surchargeNonceInput = () =>
		this.page.locator( 'input[name="mollie-surcharge-nonce"]' ).first();
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
	bizumPhoneInput = () => this.page.locator( '#billing_phone_bizum' );

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
		if ( gateway.slug === 'bizum' ) {
			const phoneInput = this.bizumPhoneInput();
			await expect(
				phoneInput,
				'Assert bizum phone input is visible'
			).toBeVisible();
			await phoneInput.fill( '+34612345678' );
		}

		await this.payForOrderButton().click();
	};

	/**
	 * Opens the page and waits for the surcharge call the page script sends on load
	 *
	 * @param orderId
	 * @param orderKey
	 * @return the order key and surcharge nonce rendered for this visitor
	 */
	visitAndWaitForSurcharge = async ( orderId: number, orderKey: string ) => {
		await Promise.all( [
			this.page.waitForResponse( isSurchargeCall ),
			this.visit( orderId, orderKey ),
		] );
		return {
			orderKey: await this.surchargeOrderKeyInput().inputValue(),
			nonce: await this.surchargeNonceInput().inputValue(),
		};
	};

	/**
	 * Selects a payment method and waits for the surcharge call the page script sends on change
	 *
	 * @param gatewayId
	 */
	selectPaymentMethodAndWaitForSurcharge = async ( gatewayId: string ) => {
		await Promise.all( [
			this.page.waitForResponse( isSurchargeCall ),
			this.paymentMethodLabel( gatewayId ).click(),
		] );
	};

	/**
	 * Sends update_surcharge_order_pay directly, as the visitor or as another user
	 *
	 * @param data
	 * @param request defaults to the visitor of this page
	 */
	postSurchargeUpdate = async (
		data: SurchargeRequest,
		request: APIRequestContext = this.page.request
	) => {
		const response = await request.post( urls.admin.ajax, {
			form: { action: 'update_surcharge_order_pay', ...data },
		} );
		return response.json();
	};

	// Assertions
}
