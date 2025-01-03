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
} from '.';
import {
	MollieSettings,
	molliePlugin,
	mollieConfigGeneral,
} from '../resources';

export class Utils {
	plugins: Plugins;
	wooCommerceUtils: WooCommerceUtils;
	requestUtils: RequestUtils;
	wooCommerceApi: WooCommerceApi;
	visitorWooCommerceApi: WooCommerceApi;
	mollieSettingsApiKeys: MollieSettingsApiKeys;
	mollieSettingsAdvanced: MollieSettingsAdvanced;

	constructor( {
		plugins,
		wooCommerceUtils,
		requestUtils,
		wooCommerceApi,
		visitorWooCommerceApi,
		mollieSettingsApiKeys,
		mollieSettingsAdvanced,
	} ) {
		this.plugins = plugins;
		this.wooCommerceUtils = wooCommerceUtils;
		this.requestUtils = requestUtils;
		this.wooCommerceApi = wooCommerceApi;
		this.visitorWooCommerceApi = visitorWooCommerceApi;
		this.mollieSettingsApiKeys = mollieSettingsApiKeys;
		this.mollieSettingsAdvanced = mollieSettingsAdvanced;
	}

	// Tested plugin preconditions

	installActivateMollie = async () => {
		if (
			! ( await this.requestUtils.isPluginInstalled( molliePlugin.slug ) )
		) {
			await this.plugins.installPluginFromFile(
				molliePlugin.zipFilePath
			);
		}
		await this.requestUtils.activatePlugin( molliePlugin.slug );
	};

	cleanMollieDb = async () => {
		await this.mollieSettingsAdvanced.visit();
		await this.mollieSettingsAdvanced.cleanDb();
	};

	connectMollieApi = async (
		data: MollieSettings.ApiKeys = mollieConfigGeneral.default
	) => {
		await this.mollieSettingsApiKeys.visit();
		await this.mollieSettingsApiKeys.setup( data );
		await this.mollieSettingsApiKeys.saveChanges();
	};

	cleanReconnectMollie = async () => {
		await this.connectMollieApi();
		await this.cleanMollieDb();
		await this.connectMollieApi();
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
	configureStore = async ( data ) => {
		if ( data.classicPages === true ) {
			await this.wooCommerceUtils.activateClassicCartPage();
			await this.wooCommerceUtils.activateClassicCheckoutPage();
		}

		if ( data.classicPages === false ) {
			await this.wooCommerceUtils.activateBlockCartPage();
			await this.wooCommerceUtils.activateBlockCheckoutPage();
		}

		if ( data.settings?.general ) {
			await this.wooCommerceApi.updateGeneralSettings(
				data.settings.general
			);
		}

		if ( data.taxes ) {
			await this.wooCommerceUtils.setTaxes( data.taxes );
		}

		if ( data.customer ) {
			await this.restoreCustomer( data.customer );
		}
	};
}
