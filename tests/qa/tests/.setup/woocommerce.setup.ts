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
	disableNoncePlugin,
	subscriptionsPlugin,
} from '../../resources';

// setup( 'Setup WooCommerce plugin, API, go Live', async ( { requestUtils } ) => {} );

setup( 'Setup theme',
	async ( { requestUtils } ) => {
		const slug = 'storefront';
		if( ! await requestUtils.isThemeInstalled( slug ) ) {
			await requestUtils.installTheme( slug );
		}
		await requestUtils.activateTheme( slug );
	}
);

setup( 'Setup Disable Nonce Plugin',
	async ( { requestUtils, plugins } ) => {
		if (
			! ( await requestUtils.isPluginInstalled(
				disableNoncePlugin.slug
			) )
		) {
			await plugins.installPluginFromFile(
				disableNoncePlugin.zipFilePath
			);
		}
		await requestUtils.activatePlugin( disableNoncePlugin.slug );
	}
);

setup( 'Setup WooCommerce Subscriptions Plugin (deactivated)',
	async ( { requestUtils, plugins } ) => {
		if (
			! ( await requestUtils.isPluginInstalled(
				subscriptionsPlugin.slug
			) )
		) {
			await plugins.installPluginFromFile(
				subscriptionsPlugin.zipFilePath
			);
		}
		await requestUtils.deactivatePlugin( subscriptionsPlugin.slug );
	}
);

setup( 'Setup Disable WooCommerce Setup Plugin',
	async ( { requestUtils, plugins } ) => {
		const helperPluginSlug = 'disable-wc-setup-wizard';
		if ( ! ( await requestUtils.isPluginInstalled( helperPluginSlug ) ) ) {
			await plugins.installPluginFromFile(
				`./resources/files/${ helperPluginSlug }.zip`
			);
		}
		await requestUtils.activatePlugin( helperPluginSlug );
	}
);

setup( 'Setup Block and Classic pages',
	async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.publishBlockCartPage();
		await wooCommerceUtils.publishBlockCheckoutPage();
		await wooCommerceUtils.publishClassicCartPage();
		await wooCommerceUtils.publishClassicCheckoutPage();
	}
);

setup( 'Setup WooCommerce general settings', async ( { wooCommerceApi } ) => {
	const country = 'germany';
	await wooCommerceApi.updateGeneralSettings(
		shopSettings[ country ].general
	);
} );

setup( 'Setup WooCommerce shipping',
	async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.configureShippingZone( shippingZones.worldwide );
	}
);

setup( 'Setup WooCommerce taxes (included)',
	async ( { wooCommerceUtils } ) => {
		await wooCommerceUtils.setTaxes( taxSettings.including );
	}
);

setup( 'Setup Registered Customer',
	async ( { wooCommerceUtils } ) => {
		const country = 'germany';
		await wooCommerceUtils.createCustomer( customers[ country ] );
	}
);

setup( 'Setup Delete Previous Orders',
	async ( { wooCommerceApi } ) => {
		await wooCommerceApi.deleteAllOrders();
	}
);

setup( 'Setup coupons', async ( { wooCommerceUtils } ) => {
	// create test coupons
	const couponItems = {};
	const couponEntries = Object.entries( coupons );
	await Promise.all(
		couponEntries.map( async ( [ key, coupon ] ) => {
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
		productEntries.map( async ( [ key, product ] ) => {
			// check if not subscription product - requires Supscriptions plugin
			if ( ! product.slug.includes( 'subscription' ) ) {
				const createdProduct = await wooCommerceUtils.createProduct(
					product
				);
				cartItems[ product.slug ] = { id: createdProduct.id };
			}
		} )
	);
	// store created products as CART_ITEMS env var
	process.env.PRODUCTS = JSON.stringify( cartItems );
} );
