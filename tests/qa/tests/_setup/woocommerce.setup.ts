// /**
//  * External dependencies
//  */
// import { updateDotenv } from '@inpsyde/playwright-utils/build';
// /**
//  * Internal dependencies
//  */
// import { test as setup } from '../../utils';
// import {
// 	shopSettings,
// 	shippingZones,
// 	taxSettings,
// 	products,
// 	coupons,
// 	customers,
// 	disableNoncePlugin,
// 	subscriptionsPlugin,
// 	disableWcSetupWizard,
// } from '../../resources';

// setup( 'Setup Permalinks', async ( { requestUtils } ) => {
// 	await requestUtils.setPermalinks( '/%postname%/' );
// } );

// setup(
// 	'Setup Disable Nonce plugin (inactive)',
// 	async ( { requestUtils, plugins } ) => {
// 		if (
// 			! ( await requestUtils.isPluginInstalled(
// 				disableNoncePlugin.slug
// 			) )
// 		) {
// 			await plugins.installPluginFromFile(
// 				disableNoncePlugin.zipFilePath
// 			);
// 		}
// 		await requestUtils.activatePlugin( disableNoncePlugin.slug );
// 	}
// );

// setup(
// 	'Setup Disable WooCommerce Setup Wizard Plugin (active)',
// 	async ( { requestUtils, plugins } ) => {
// 		if (
// 			! ( await requestUtils.isPluginInstalled(
// 				disableWcSetupWizard.slug
// 			) )
// 		) {
// 			await plugins.installPluginFromFile(
// 				disableWcSetupWizard.zipFilePath
// 			);
// 		}
// 		await requestUtils.activatePlugin( disableWcSetupWizard.slug );
// 	}
// );

// setup( 'Setup WooCommerce plugin (active)', async ( { requestUtils } ) => {
// 	if ( ! ( await requestUtils.isPluginInstalled( 'woocommerce' ) ) ) {
// 		await requestUtils.installPlugin( 'woocommerce' );
// 	}
// 	await requestUtils.activatePlugin( 'woocommerce' );
// } );

// setup(
// 	'Setup WC Subscriptions plugin (inactive)',
// 	async ( { requestUtils, plugins } ) => {
// 		if (
// 			! ( await requestUtils.isPluginInstalled(
// 				subscriptionsPlugin.slug
// 			) )
// 		) {
// 			await plugins.installPluginFromFile(
// 				subscriptionsPlugin.zipFilePath
// 			);
// 		}
// 		await requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
// 	}
// );

// setup( 'Setup theme', async ( { requestUtils } ) => {
// 	const slug = 'storefront';
// 	if ( ! ( await requestUtils.isThemeInstalled( slug ) ) ) {
// 		await requestUtils.installTheme( slug );
// 	}
// 	await requestUtils.activateTheme( slug );
// } );

// setup(
// 	'Setup WooCommerce Live site visibility',
// 	async ( { wooCommerceUtils } ) => {
// 		await wooCommerceUtils.setSiteVisibility();
// 	}
// );

// setup( 'Setup WooCommerce API keys', async ( { wooCommerceUtils } ) => {
// 	if ( ! ( await wooCommerceUtils.apiKeysExist() ) ) {
// 		const apiKeys = await wooCommerceUtils.createApiKeys();
// 		if ( ! process.env.CI ) {
// 			await updateDotenv( './.env', apiKeys );
// 		}
// 		for ( const [ key, value ] of Object.entries( apiKeys ) ) {
// 			process.env[ key ] = value;
// 		}
// 	}
// } );

// setup( 'Setup Block and Classic pages', async ( { wooCommerceUtils } ) => {
// 	await wooCommerceUtils.publishBlockCartPage();
// 	await wooCommerceUtils.publishBlockCheckoutPage();
// 	await wooCommerceUtils.publishClassicCartPage();
// 	await wooCommerceUtils.publishClassicCheckoutPage();
// } );

// setup( 'Setup WooCommerce email settings', async ( { wooCommerceApi } ) => {
// 	const disabled = { enabled: 'no' };
// 	await wooCommerceApi.updateEmailSubSettings( 'email_new_order', disabled );
// 	await wooCommerceApi.updateEmailSubSettings(
// 		'email_cancelled_order',
// 		disabled
// 	);
// 	await wooCommerceApi.updateEmailSubSettings(
// 		'email_failed_order',
// 		disabled
// 	);
// 	await wooCommerceApi.updateEmailSubSettings(
// 		'email_customer_on_hold_order',
// 		disabled
// 	);
// 	await wooCommerceApi.updateEmailSubSettings(
// 		'email_customer_processing_order',
// 		disabled
// 	);
// 	await wooCommerceApi.updateEmailSubSettings(
// 		'email_customer_completed_order',
// 		disabled
// 	);
// 	await wooCommerceApi.updateEmailSubSettings(
// 		'email_customer_refunded_order',
// 		disabled
// 	);
// 	await wooCommerceApi.updateEmailSubSettings(
// 		'email_customer_note',
// 		disabled
// 	);
// 	await wooCommerceApi.updateEmailSubSettings(
// 		'email_customer_reset_password',
// 		disabled
// 	);
// 	await wooCommerceApi.updateEmailSubSettings(
// 		'email_customer_new_account',
// 		disabled
// 	);
// } );

// setup( 'Setup WooCommerce general settings', async ( { wooCommerceApi } ) => {
// 	await wooCommerceApi.updateGeneralSettings( shopSettings.germany.general );
// } );

// setup( 'Setup WooCommerce shipping', async ( { wooCommerceUtils } ) => {
// 	await wooCommerceUtils.configureShippingZone( shippingZones.worldwide );
// } );

// setup( 'Setup WooCommerce taxes (included)', async ( { wooCommerceUtils } ) => {
// 	await wooCommerceUtils.setTaxes( taxSettings.including );
// } );

// setup( 'Setup Registered Customer', async ( { wooCommerceUtils } ) => {
// 	await wooCommerceUtils.createCustomer( customers.germany );
// } );

// setup( 'Setup Delete Previous Orders', async ( { wooCommerceApi } ) => {
// 	await wooCommerceApi.deleteAllOrders();
// } );

// setup( 'Setup coupons', async ( { wooCommerceUtils } ) => {
// 	// create test coupons
// 	const couponItems = {};
// 	const couponEntries = Object.entries( coupons );
// 	await Promise.all(
// 		couponEntries.map( async ( [ key, coupon ] ) => {
// 			const createdCoupon = await wooCommerceUtils.createCoupon( coupon );
// 			couponItems[ coupon.code ] = { id: createdCoupon.id };
// 		} )
// 	);
// 	// store created coupons as CART_ITEMS env var
// 	process.env.COUPONS = JSON.stringify( couponItems );
// } );

// setup( 'Setup products', async ( { wooCommerceUtils } ) => {
// 	// create test products
// 	const cartItems = {};
// 	const productEntries = Object.entries( products );
// 	await Promise.all(
// 		productEntries.map( async ( [ key, product ] ) => {
// 			// check if not subscription product - requires Supscriptions plugin
// 			if ( ! product.slug.includes( 'subscription' ) ) {
// 				const createdProduct = await wooCommerceUtils.createProduct(
// 					product
// 				);
// 				cartItems[ product.slug ] = { id: createdProduct.id };
// 			}
// 		} )
// 	);
// 	// store created products as CART_ITEMS env var
// 	process.env.PRODUCTS = JSON.stringify( cartItems );
// } );
