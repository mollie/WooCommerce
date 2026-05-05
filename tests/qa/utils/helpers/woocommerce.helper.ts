/**
 * External dependencies
 */
import { updateDotenv } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import {
	shopSettings,
	shippingZones,
	taxSettings,
	products,
	coupons,
	customers,
	disableNonceCheckPlugin,
	woocommerceSubscriptionsPlugin,
	disableWcSetupWizardPlugin,
	enableBizumPlugin,
} from '../../resources';

const installPluginResolveActiveState = async ( {
	requestUtils,
	plugins,
	slug,
	zipFilePath,
	isActive = true
} ) => {
	if ( ! ( await requestUtils.isPluginInstalled( slug ) ) ) {
		await plugins.installPluginFromFile( zipFilePath );
	}
	isActive
		? await requestUtils.activatePlugin( slug )
		: await requestUtils.deactivatePlugin( slug );
};

export const setupWooCommerce = async () => {
	// In CI wp-env is used and following setup is already done by wp-env, so skip it in CI to save time
	if ( ! process.env.CI ) {
		setup( 'Setup Permalinks', async ( { requestUtils } ) => {
			await requestUtils.setPermalinks( '/%postname%/' );
		} );

		setup(
			'Setup Disable Nonce plugin (active)',
			async ( { requestUtils, plugins } ) => {
				await installPluginResolveActiveState( {
					requestUtils,
					plugins,
					...disableNonceCheckPlugin,
				} );
			}
		);

		setup(
			'Setup Disable WooCommerce Setup Wizard Plugin (active)',
			async ( { requestUtils, plugins } ) => {
				await installPluginResolveActiveState( {
					requestUtils,
					plugins,
					...disableWcSetupWizardPlugin,
				} );
			}
		);

		setup( 'Setup WooCommerce plugin (active)', async ( { requestUtils } ) => {
			if ( ! ( await requestUtils.isPluginInstalled( 'woocommerce' ) ) ) {
				await requestUtils.installPlugin( 'woocommerce' );
			}
			await requestUtils.activatePlugin( 'woocommerce' );
		} );

		setup(
			'Setup WC Subscriptions plugin (inactive)',
			async ( { requestUtils, plugins } ) => {
				if (
					! ( await requestUtils.isPluginInstalled(
						woocommerceSubscriptionsPlugin.slug
					) )
				) {
					await plugins.installPluginFromFile(
						woocommerceSubscriptionsPlugin.zipFilePath
					);
				}
				await requestUtils.deactivatePlugin(
					woocommerceSubscriptionsPlugin.slug
				);
			}
		);

		setup( 'Setup theme', async ( { requestUtils } ) => {
			const slug = 'storefront';
			if ( ! ( await requestUtils.isThemeInstalled( slug ) ) ) {
				await requestUtils.installTheme( slug );
			}
			await requestUtils.activateTheme( slug );
		} );

		setup(
			'Setup WooCommerce Live site visibility',
			async ( { wooCommerceUtils } ) => {
				await wooCommerceUtils.setSiteVisibility();
			}
		);
	}

	setup( 'Setup WooCommerce API keys', async ( { wooCommerceUtils } ) => {
		if ( ! ( await wooCommerceUtils.apiKeysExist() ) ) {
			const apiKeys = await wooCommerceUtils.createApiKeys();
			if ( ! process.env.CI ) {
				await updateDotenv( './.env', apiKeys );
			}
			for ( const [ key, value ] of Object.entries( apiKeys ) ) {
				process.env[ key ] = value;
			}
		}
	} );

	setup( 'Setup Block and Classic pages', async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.publishBlockCartPage();
		await wooCommerceUtils.publishBlockCheckoutPage();
		await wooCommerceUtils.publishClassicCartPage();
		await wooCommerceUtils.publishClassicCheckoutPage();
	} );

	setup( 'Setup WooCommerce email settings', async ( { wooCommerceApi } ) => {
		const emailIds = [
			'email_new_order',
			'email_cancelled_order',
			'email_failed_order',
			'email_customer_failed_order',
			'email_customer_on_hold_order',
			'email_customer_processing_order',
			'email_customer_completed_order',
			'email_customer_refunded_order',
			'email_customer_note',
			'email_customer_reset_password',
			'email_customer_new_account',
			'email_customer_pos_refunded_order',
		];
		for ( const id of emailIds ) {
			await wooCommerceApi.updateEmailSubSettings( id, { enabled: 'no' } );
		}
	} );

	setup( 'Setup WooCommerce general settings', async ( { wooCommerceApi } ) => {
		await wooCommerceApi.updateGeneralSettings( shopSettings.germany.general );
	} );

	setup( 'Setup WooCommerce shipping', async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.configureShippingZone( shippingZones.worldwide );
	} );

	setup( 'Setup WooCommerce taxes (included)', async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	} );

	setup( 'Setup Registered Customer', async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.createCustomer( customers.germany );
	} );

	setup( 'Setup coupons', async ( { wooCommerceUtils } ) => {
		// create test coupons
		const couponItems = {};
		const couponEntries = Object.entries( coupons );
		await Promise.all(
			couponEntries.map( async ( [ , coupon ] ) => {
				const createdCoupon = await wooCommerceUtils.createCoupon( coupon );
				couponItems[ coupon.code ] = { id: createdCoupon.id };
			} )
		);
		// store created coupons as CART_ITEMS env var
		process.env.COUPONS = JSON.stringify( couponItems );
	} );

	setup( 'Setup products', async ( { wooCommerceUtils } ) => {
		// create test products
		const cartItems = {};
		const productEntries = Object.entries( products );
		await Promise.all(
			productEntries.map( async ( [ , product ] ) => {
				// check if not subscription product - requires Supscriptions plugin
				if ( ! product.slug.includes( 'subscription' ) ) {
					const createdProduct =
						await wooCommerceUtils.createProduct( product );
					cartItems[ product.slug ] = { id: createdProduct.id };
				}
			} )
		);
		// store created products as CART_ITEMS env var
		process.env.PRODUCTS = JSON.stringify( cartItems );
	} );

	setup(
		'Setup Enable Bizum plugin (active)',
		async ( { requestUtils, plugins } ) => {
			await installPluginResolveActiveState( {
				requestUtils,
				plugins,
				...enableBizumPlugin,
			} );
		}
	);
};
