/**
 * External dependencies
 */
import {
	testPluginInstallationFromFile,
	testPluginReinstallationFromFile,
	testPluginInstallationFromMarketplace,
	testPluginDeactivation,
	testPluginRemoval,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { molliePlugin, gateways } from '../../resources';

testPluginInstallationFromFile( 'C419986', molliePlugin );

testPluginInstallationFromMarketplace( 'C3317', molliePlugin );

testPluginReinstallationFromFile( 'C3322', molliePlugin );

testPluginDeactivation( 'C3319', molliePlugin );

testPluginRemoval( 'C3318', molliePlugin );

test.describe( 'Plugin foundation', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.installActivateMollie();
	} );

	test( 'C419984 | Plugin foundation - Mollie gateways are present in WooCommerce payment methods', async ( {
		wooCommerceSettings,
	} ) => {
		await wooCommerceSettings.visit( 'payments' );
		for ( const key in gateways ) {
			const gateway = gateways[ key ];
			const mollieGatewayname = `Mollie - ${ gateway.name }`;
			await expect
				.soft( wooCommerceSettings.gatewayLink( mollieGatewayname ) )
				.toBeVisible();
		}
	} );
} );
