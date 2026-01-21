/**
 * External dependencies
 */
import {
	WooCommerceApi,
	RequestUtils,
	Plugins,
	WooCommerceUtils,
	restLogin,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import {
	MollieSettingsApiKeys,
	MollieSettingsAdvanced,
	getCustomerStorageStateName,
	MollieApi,
} from '.';
import {
	MollieSettings,
	molliePlugin,
	mollieApiKeys,
	subscriptionsPlugin,
	ShopConfig,
} from '../resources';

export class Utils {
	mollieApi: MollieApi;
	mollieApiMethod: MollieSettings.ApiMethod;
	plugins: Plugins;
	wooCommerceUtils: WooCommerceUtils;
	requestUtils: RequestUtils;
	wooCommerceApi: WooCommerceApi;
	visitorWooCommerceApi: WooCommerceApi;
	mollieSettingsApiKeys: MollieSettingsApiKeys;
	mollieSettingsAdvanced: MollieSettingsAdvanced;

	constructor( {
		mollieApi,
		mollieApiMethod,
		plugins,
		wooCommerceUtils,
		requestUtils,
		wooCommerceApi,
		visitorWooCommerceApi,
		mollieSettingsApiKeys,
		mollieSettingsAdvanced,
	} ) {
		this.mollieApi = mollieApi;
		this.mollieApiMethod = mollieApiMethod;
		this.plugins = plugins;
		this.wooCommerceUtils = wooCommerceUtils;
		this.requestUtils = requestUtils;
		this.wooCommerceApi = wooCommerceApi;
		this.visitorWooCommerceApi = visitorWooCommerceApi;
		this.mollieSettingsApiKeys = mollieSettingsApiKeys;
		this.mollieSettingsAdvanced = mollieSettingsAdvanced;
	}

	// Tested plugin preconditions

	installAndActivateMollie = async () => {
		if (
			! ( await this.requestUtils.isPluginInstalled( molliePlugin.slug ) )
		) {
			await this.plugins.installPluginFromFile(
				molliePlugin.zipFilePath
			);
		}
		await this.requestUtils.activatePlugin( molliePlugin.slug );
	};

	/**
	 * Resets and reconnects Mollie:
	 * 	- Clears Mollie DB
	 * 	- Sets mollie API keys
	 * 	- Sets API method (Payment or Order API)
	 */
	cleanReconnectMollie = async () => {
		await this.mollieApi.setMollieApiKeys( mollieApiKeys.default );
		await this.mollieApi.cleanMollieDb();
		await this.mollieApi.setMollieApiKeys( mollieApiKeys.default );
		await this.mollieApi.setAdvancedSettings( {
			apiMethod: this.mollieApiMethod
		} );
	};

	/**
	 * Pays for order on checkout page
	 *
	 * @param products
	 */
	fillVisitorsCart = async ( products: WooCommerce.CreateProduct[] ) => {
		const cartProducts = await this.wooCommerceUtils.createCartProducts(
			products
		);
		await this.visitorWooCommerceApi.clearCart();
		await this.visitorWooCommerceApi.addProductsToCart( cartProducts );
	};

	/**
	 * (Re)creates registered customer and refreshes his storage state.
	 * May be required for testing subscriptions/vaulting.
	 *
	 * @param customer
	 */
	restoreCustomer = async ( customer: WooCommerce.CreateCustomer ) => {
		await this.wooCommerceUtils.deleteCustomer( customer );
		await this.wooCommerceUtils.createCustomer( customer );
		const storageStateName = getCustomerStorageStateName( customer );
		const storageStatePath = `${ process.env.STORAGE_STATE_PATH }/${ storageStateName }.json`;
		await restLogin( {
			baseURL: process.env.WP_BASE_URL,
			httpCredentials: {
				username: process.env.WP_BASIC_AUTH_USER,
				password: process.env.WP_BASIC_AUTH_PASS,
			},
			storageStatePath,
			user: {
				username: customer.username,
				password: customer.password,
			},
		} );
	};

	/**
	 * Configures store according to the data provided
	 *
	 * @param {Object} data see /resources/woocommerce-config.ts
	 */
	configureStore = async ( data: ShopConfig ) => {
		const {
			enableSubscriptionsPlugin,
			enableClassicPages,
			settings,
			taxes,
			customer,
			products,
		}: ShopConfig = data;

		if ( enableSubscriptionsPlugin === true ) {
			await this.requestUtils.activatePlugin( subscriptionsPlugin.slug );
		}

		if ( enableSubscriptionsPlugin === false ) {
			await this.requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
		}

		if ( enableClassicPages === true ) {
			await this.wooCommerceUtils.activateClassicCartPage();
			await this.wooCommerceUtils.activateClassicCheckoutPage();
		}

		if ( enableClassicPages === false ) {
			await this.wooCommerceUtils.activateBlockCartPage();
			await this.wooCommerceUtils.activateBlockCheckoutPage();
		}

		if ( settings?.general ) {
			await this.wooCommerceApi.updateGeneralSettings(
				settings.general
			);
		}

		if ( taxes ) {
			await this.wooCommerceUtils.setTaxes( taxes );
		}

		if ( customer ) {
			await this.restoreCustomer( customer );
		}

		if ( products ) {
			// create test products
			const cartItems = {};
			await Promise.all(
				products.map( async ( product ) => {
					const createdProduct =
						await this.wooCommerceUtils.createProduct( product );
					// Create cart items { id: 123 }
					cartItems[ product.slug ] = { id: createdProduct.id };
				} )
			);

			// Parse existing PRODUCTS, if any
			const existingProducts = process.env.PRODUCTS
				? JSON.parse( process.env.PRODUCTS )
				: {};

			// Merge created products with existing and store back as JSON string
			process.env.PRODUCTS = JSON.stringify( {
				...existingProducts,
				...cartItems,
			} );
		}
	};
}
