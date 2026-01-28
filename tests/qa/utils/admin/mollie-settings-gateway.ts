/**
 * External dependencies
 */
import { WooCommerceAdminPage } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { gateways, MollieGateway, MollieSettings } from '../../resources';
import { urls } from '../urls';

export class MollieSettingsGateway extends WooCommerceAdminPage {
	url: string;
	gateway: MollieGateway;

	constructor( { page, gatewaySlug } ) {
		super( { page } );
		this.url = urls.mollie.admin.settings.gateway + gatewaySlug;
		this.gateway = gateways[ gatewaySlug ];
	}

	// Locators
	enableGatewayCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_enabled`
		);
	useApiDynamicTitleAndGatewayLogoCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_use_api_title`
		);
	titleInput = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_title`
		);
	displayLogoCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_display_logo`
		);
	enableCustomLogoCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_enable_custom_logo`
		);
	uploadCustomLogoButton = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_upload_logo`
		);
	descriptionTextarea = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_description`
		);

	sellToSpecificCountriesCombobox = () =>
		this.page.getByPlaceholder( 'Choose countries' );
	selectAllButton = () => this.page.locator( 'a.select_all.button' );
	selectNoneButton = () => this.page.locator( 'a.select_none.button' );

	paymentSurchargeSelect = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_payment_surcharge`
		);
	paymentSurchargeFixedAmountInput = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_fixed_fee`
		);
	surchargeOnlyUnderLimitInput = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_maximum_limit`
		);
	paymentSurchargePercentageAmountInput = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_percentage`
		);
	paymentSurchargeLimitInput = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_surcharge_limit`
		);

	activateExpiryTimeSettingCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_activate_expiry_days_setting`
		);
	expiryTimeInput = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_order_dueDate`
		);
	showIssuersDropdownCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_issuers_dropdown_shown`
		);
	initialOrderStatusSelect = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_initial_order_status`
		);
	skipMolliePaymentScreenCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_skip_mollie_payment_screen`
		);
	enableMollieComponentsCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_components_enabled`
		);

	enableApplePayButtonOnCartCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_apple_pay_button_enabled_cart`
		);
	enableApplePayButtonOnProductCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_apple_pay_button_enabled_product`
		);
	enableApplePayExpressButtonOnCheckoutCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_apple_pay_button_enabled_express_checkout`
		);

	paypalDisplayOnCartCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_paypal_button_enabled_cart`
		);
	paypalDisplayOnProductCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_paypal_button_enabled_product`
		);
	paypalButtonTextLanguageAndColorSelect = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_color`
		);
	paypalMinimumAmountToDisplayButtonInput = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_paypal_button_minimum_amount`
		);

	giftcardsShowDropdownCheckbox = () => this.showIssuersDropdownCheckbox();
	kbcShowBanksDropdownCheckbox = () => this.showIssuersDropdownCheckbox();
	kbcIssuersEmptyOptionInput = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_issuers_empty_option`
		);

	voucherDefaultProductsCategorySelect = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mealvoucher_category_default`
		);

	enableIconsSelectorCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_enabler`
		);
	showAmericanExpressIconCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_amex`
		);
	showCartaSiIconCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_cartasi`
		);
	showCarteBancaireIconCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_cartebancaire`
		);
	showMaestroIconCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_maestro`
		);
	showMastercardIconCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_mastercard`
		);
	showVisaIconCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_visa`
		);
	showVpayIconCheckbox = () =>
		this.page.locator(
			`#woocommerce_mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_vpay`
		);

	// Actions

	/**
	 * Setup Mollie gateway settings
	 *
	 * @param data
	 */
	setup = async ( data: MollieSettings.Gateway = this.gateway.settings ) => {
		if ( data.enabled !== undefined ) {
			await this.enableGatewayCheckbox().setChecked( data.enabled );
		}

		if ( data.use_api_title !== undefined ) {
			await this.useApiDynamicTitleAndGatewayLogoCheckbox().setChecked(
				data.use_api_title === 'yes'
			);
		}

		if ( data.title ) {
			await this.titleInput().fill( data.title );
		}

		if ( data.display_logo !== undefined ) {
			await this.displayLogoCheckbox().setChecked(
				data.display_logo === 'yes'
			);
		}

		if ( data.enable_custom_logo !== undefined ) {
			await this.enableCustomLogoCheckbox().setChecked(
				data.enable_custom_logo === 'yes'
			);
		}

		if ( data.enable_custom_logo === 'yes' && data.custom_logo_path ) {
			await this.uploadCustomLogoButton().setInputFiles(
				'./resources/files/mollie-test-logo.png'
			);
		}

		if ( data.description !== undefined ) {
			await this.descriptionTextarea().fill( data.description );
		}

		if (
			data[ 'allowed_countries[]' ] &&
			data[ 'allowed_countries[]' ].length > 0
		) {
			await this.selectNoneButton().click();
			if ( data[ 'allowed_countries[]' ].length ) {
				for ( const country of data[ 'allowed_countries[]' ] ) {
					await this.sellToSpecificCountriesCombobox().click();
					await this.dropdownOption( country ).click();
				}
			}
		}

		if ( data.payment_surcharge ) {
			await this.paymentSurchargeSelect().selectOption(
				data.payment_surcharge
			);

			if ( data.fixed_fee ) {
				await this.paymentSurchargeFixedAmountInput().fill(
					data.fixed_fee
				);
			}

			if ( data.maximum_limit ) {
				await this.surchargeOnlyUnderLimitInput().fill(
					data.maximum_limit
				);
			}

			if ( data.percentage ) {
				await this.paymentSurchargePercentageAmountInput().fill(
					data.percentage
				);
			}

			if ( data.surcharge_limit ) {
				await this.paymentSurchargeLimitInput().fill(
					data.surcharge_limit
				);
			}
		}

		if ( data.activate_expiry_days_setting !== undefined ) {
			await this.activateExpiryTimeSettingCheckbox().setChecked(
				data.activate_expiry_days_setting === 'yes'
			);
		}

		if ( data.order_dueDate ) {
			await this.expiryTimeInput().fill( data.order_dueDate );
		}

		if ( data.issuers_dropdown_shown ) {
			await this.showIssuersDropdownCheckbox().setChecked(
				data.issuers_dropdown_shown === 'yes'
			);
		}

		if ( data.initial_order_status ) {
			await this.initialOrderStatusSelect().selectOption(
				data.initial_order_status
			);
		}

		if ( data.skip_mollie_payment_screen !== undefined ) {
			await this.skipMolliePaymentScreenCheckbox().setChecked(
				data.skip_mollie_payment_screen === 'yes'
			);
		}

		if ( data.mollie_apple_pay_button_enabled_cart !== undefined ) {
			await this.enableApplePayButtonOnCartCheckbox().setChecked(
				data.mollie_apple_pay_button_enabled_cart === 'yes'
			);
		}

		if ( data.mollie_apple_pay_button_enabled_product !== undefined ) {
			await this.enableApplePayButtonOnProductCheckbox().setChecked(
				data.mollie_apple_pay_button_enabled_product === 'yes'
			);
		}

		if (
			data.mollie_apple_pay_button_enabled_express_checkout !== undefined
		) {
			await this.enableApplePayExpressButtonOnCheckoutCheckbox().setChecked(
				data.mollie_apple_pay_button_enabled_express_checkout === 'yes'
			);
		}

		if ( data.mollie_paypal_button_enabled_cart !== undefined ) {
			await this.paypalDisplayOnCartCheckbox().setChecked(
				data.mollie_paypal_button_enabled_cart === 'yes'
			);
		}

		if ( data.mollie_paypal_button_enabled_product !== undefined ) {
			await this.paypalDisplayOnProductCheckbox().setChecked(
				data.mollie_paypal_button_enabled_product === 'yes'
			);
		}

		if ( data.paypal_color ) {
			await this.paypalButtonTextLanguageAndColorSelect().selectOption(
				data.paypal_color
			);
		}

		if ( data.mollie_paypal_button_minimum_amount ) {
			await this.paypalMinimumAmountToDisplayButtonInput().fill(
				data.mollie_paypal_button_minimum_amount
			);
		}

		if ( data.issuers_dropdown_shown !== undefined ) {
			await this.giftcardsShowDropdownCheckbox().setChecked(
				data.issuers_dropdown_shown === 'yes'
			);
		}

		if ( data.issuers_dropdown_shown !== undefined ) {
			await this.kbcShowBanksDropdownCheckbox().setChecked(
				data.issuers_dropdown_shown === 'yes'
			);
		}

		if ( data.issuers_empty_option ) {
			await this.kbcIssuersEmptyOptionInput().fill(
				data.issuers_empty_option
			);
		}

		if ( data.mealvoucher_category_default ) {
			await this.voucherDefaultProductsCategorySelect().selectOption(
				data.mealvoucher_category_default
			);
		}

		if ( data.mollie_creditcard_icons_enabled !== undefined ) {
			await this.enableIconsSelectorCheckbox().setChecked(
				data.mollie_creditcard_icons_enabled === 'yes'
			);
		}

		if ( data.mollie_creditcard_icons_amex !== undefined ) {
			await this.showAmericanExpressIconCheckbox().setChecked(
				data.mollie_creditcard_icons_amex === 'yes'
			);
		}

		if ( data.mollie_creditcard_icons_cartasi !== undefined ) {
			await this.showCartaSiIconCheckbox().setChecked(
				data.mollie_creditcard_icons_cartasi === 'yes'
			);
		}

		if ( data.mollie_creditcard_icons_cartebancaire !== undefined ) {
			await this.showCarteBancaireIconCheckbox().setChecked(
				data.mollie_creditcard_icons_cartebancaire === 'yes'
			);
		}

		if ( data.mollie_creditcard_icons_maestro !== undefined ) {
			await this.showMaestroIconCheckbox().setChecked(
				data.mollie_creditcard_icons_maestro === 'yes'
			);
		}

		if ( data.mollie_creditcard_icons_mastercard !== undefined ) {
			await this.showMastercardIconCheckbox().setChecked(
				data.mollie_creditcard_icons_mastercard === 'yes'
			);
		}

		if ( data.mollie_creditcard_icons_visa !== undefined ) {
			await this.showVisaIconCheckbox().setChecked(
				data.mollie_creditcard_icons_visa === 'yes'
			);
		}

		if ( data.mollie_creditcard_icons_vpay !== undefined ) {
			await this.showVpayIconCheckbox().setChecked(
				data.mollie_creditcard_icons_vpay === 'yes'
			);
		}
	};

	// Assertions
}
