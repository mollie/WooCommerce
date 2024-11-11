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
			`#mollie_wc_gateway_${ this.gateway.slug }_enabled`
		);
	useApiDynamicTitleAndGatewayLogoCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_use_api_title`
		);
	titleInput = () =>
		this.page.locator( `#mollie_wc_gateway_${ this.gateway.slug }_title` );
	displayLogoCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_display_logo`
		);
	enableCustomLogoCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_enable_custom_logo`
		);
	uploadCustomLogoButton = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_upload_logo`
		);
	descriptionTextarea = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_description`
		);

	sellToSpecificCountriesCombobox = () =>
		this.page.getByPlaceholder( 'Choose countries' );
	selectAllButton = () => this.page.locator( 'a.select_all.button' );
	selectNoneButton = () => this.page.locator( 'a.select_none.button' );

	paymentSurchargeSelect = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_payment_surcharge`
		);
	paymentSurchargeFixedAmountInput = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_fixed_fee`
		);
	surchargeOnlyUnderLimitInput = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_maximum_limit`
		);
	paymentSurchargePercentageAmountInput = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_percentage`
		);
	paymentSurchargeLimitInput = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_surcharge_limit`
		);

	activateExpiryTimeSettingCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_activate_expiry_days_setting`
		);
	expiryTimeInput = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_order_dueDate`
		);
	showIssuersDropdownCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_issuers_dropdown_shown`
		);
	initialOrderStatusSelect = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_initial_order_status`
		);
	skipMolliePaymentScreenCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_skip_mollie_payment_screen`
		);
	enableMollieComponentsCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_components_enabled`
		);

	enableApplePayButtonOnCartCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_apple_pay_button_enabled_cart`
		);
	enableApplePayButtonOnProductCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_apple_pay_button_enabled_product`
		);
	enableApplePayExpressButtonOnCheckoutCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_apple_pay_button_enabled_express_checkout`
		);

	paypalDisplayOnCartCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_paypal_button_enabled_cart`
		);
	paypalDisplayOnProductCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_paypal_button_enabled_product`
		);
	paypalButtonTextLanguageAndColorSelect = () =>
		this.page.locator( `#mollie_wc_gateway_${ this.gateway.slug }_color` );
	paypalMinimumAmountToDisplayButtonInput = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_paypal_button_minimum_amount`
		);

	giftcardsShowDropdownCheckbox = () => this.showIssuersDropdownCheckbox();
	kbcShowBanksDropdownCheckbox = () => this.showIssuersDropdownCheckbox();
	kbcIssuersEmptyOptionInput = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_issuers_empty_option`
		);

	voucherDefaultProductsCategorySelect = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mealvoucher_category_default`
		);

	enableIconsSelectorCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_enabler`
		);
	showAmericanExpressIconCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_amex`
		);
	showCartaSiIconCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_cartasi`
		);
	showCarteBancaireIconCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_cartebancaire`
		);
	showMaestroIconCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_maestro`
		);
	showMastercardIconCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_mastercard`
		);
	showVisaIconCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_visa`
		);
	showVpayIconCheckbox = () =>
		this.page.locator(
			`#mollie_wc_gateway_${ this.gateway.slug }_mollie_creditcard_icons_vpay`
		);

	// Actions

	/**
	 * Setup Mollie gateway settings
	 *
	 * @param data
	 */
	setup = async ( data: MollieSettings.Gateway = this.gateway.settings ) => {
		if ( data.enableGateway !== undefined ) {
			await this.enableGatewayCheckbox().setChecked( data.enableGateway );
		}

		if ( data.useApiTitle !== undefined ) {
			await this.useApiDynamicTitleAndGatewayLogoCheckbox().setChecked(
				data.useApiTitle
			);
		}

		if ( data.title ) {
			await this.titleInput().fill( data.title );
		}

		if ( data.displayLogo !== undefined ) {
			await this.displayLogoCheckbox().setChecked( data.displayLogo );
		}

		if ( data.enableCustomLogo !== undefined ) {
			await this.enableCustomLogoCheckbox().setChecked(
				data.enableCustomLogo
			);
		}

		if ( data.enableCustomLogo === true && data.customLogoPath ) {
			await this.uploadCustomLogoButton().setInputFiles(
				'./resources/files/mollie-test-logo.png'
			);
		}

		if ( data.description !== undefined ) {
			await this.descriptionTextarea().fill( data.description );
		}

		if ( data.sellToCountries && data.sellToCountries.length > 0 ) {
			await this.selectNoneButton().click();
			if ( data.sellToCountries.length ) {
				for ( const country of data.sellToCountries ) {
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

		if ( data.activateExpiryDaysSetting !== undefined ) {
			await this.activateExpiryTimeSettingCheckbox().setChecked(
				data.activateExpiryDaysSetting
			);
		}

		if ( data.orderDueDate ) {
			await this.expiryTimeInput().fill( data.orderDueDate );
		}

		if ( data.showIssuersDropdown ) {
			await this.showIssuersDropdownCheckbox().setChecked(
				data.showIssuersDropdown
			);
		}

		if ( data.initialOrderStatus ) {
			await this.initialOrderStatusSelect().selectOption(
				data.initialOrderStatus
			);
		}

		if ( data.banktransferSkipMolliePaymentScreen !== undefined ) {
			await this.skipMolliePaymentScreenCheckbox().setChecked(
				data.banktransferSkipMolliePaymentScreen
			);
		}

		if ( data.enableApplePayButtonOnCart !== undefined ) {
			await this.enableApplePayButtonOnCartCheckbox().setChecked(
				data.enableApplePayButtonOnCart
			);
		}

		if ( data.enableApplePayButtonOnProduct !== undefined ) {
			await this.enableApplePayButtonOnProductCheckbox().setChecked(
				data.enableApplePayButtonOnProduct
			);
		}

		if ( data.enableApplePayExpressButtonOnCheckout !== undefined ) {
			await this.enableApplePayExpressButtonOnCheckoutCheckbox().setChecked(
				data.enableApplePayExpressButtonOnCheckout
			);
		}

		if ( data.paypalDisplayOnCart !== undefined ) {
			await this.paypalDisplayOnCartCheckbox().setChecked(
				data.paypalDisplayOnCart
			);
		}

		if ( data.paypalDisplayOnProduct !== undefined ) {
			await this.paypalDisplayOnProductCheckbox().setChecked(
				data.paypalDisplayOnProduct
			);
		}

		if ( data.paypalButtonTextLanguageAndColor ) {
			await this.paypalButtonTextLanguageAndColorSelect().selectOption(
				data.paypalButtonTextLanguageAndColor
			);
		}

		if ( data.paypalMinimumAmountToDisplayButton ) {
			await this.paypalMinimumAmountToDisplayButtonInput().fill(
				data.paypalMinimumAmountToDisplayButton
			);
		}

		if ( data.giftcardShowDropdown !== undefined ) {
			await this.giftcardsShowDropdownCheckbox().setChecked(
				data.giftcardShowDropdown
			);
		}

		if ( data.kbcShowBanksDropdown !== undefined ) {
			await this.kbcShowBanksDropdownCheckbox().setChecked(
				data.kbcShowBanksDropdown
			);
		}

		if ( data.issuersEmptyOption ) {
			await this.kbcIssuersEmptyOptionInput().fill(
				data.issuersEmptyOption
			);
		}

		if ( data.voucherDefaultProductsCategory ) {
			await this.voucherDefaultProductsCategorySelect().selectOption(
				data.voucherDefaultProductsCategory
			);
		}

		if ( data.enableMollieCreditcardIcons !== undefined ) {
			await this.enableIconsSelectorCheckbox().setChecked(
				data.enableMollieCreditcardIcons
			);
		}

		if ( data.enableMollieCreditcardIconsAmex !== undefined ) {
			await this.showAmericanExpressIconCheckbox().setChecked(
				data.enableMollieCreditcardIconsAmex
			);
		}

		if ( data.enableMollieCreditcardIconsCartaSi !== undefined ) {
			await this.showCartaSiIconCheckbox().setChecked(
				data.enableMollieCreditcardIconsCartaSi
			);
		}

		if ( data.enableMollieCreditcardICarteBancaire !== undefined ) {
			await this.showCarteBancaireIconCheckbox().setChecked(
				data.enableMollieCreditcardICarteBancaire
			);
		}

		if ( data.enableMollieCreditcardIconsMaestro !== undefined ) {
			await this.showMaestroIconCheckbox().setChecked(
				data.enableMollieCreditcardIconsMaestro
			);
		}

		if ( data.enableMollieCreditcardIconsMastercard !== undefined ) {
			await this.showMastercardIconCheckbox().setChecked(
				data.enableMollieCreditcardIconsMastercard
			);
		}

		if ( data.enableMollieCreditcardIconsVisa !== undefined ) {
			await this.showVisaIconCheckbox().setChecked(
				data.enableMollieCreditcardIconsVisa
			);
		}

		if ( data.enableMollieCreditcardIconsVpay !== undefined ) {
			await this.showVpayIconCheckbox().setChecked(
				data.enableMollieCreditcardIconsVpay
			);
		}
	};

	// Assertions
}
