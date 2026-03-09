/**
 * Internal dependencies
 */
import { test as setup, urls, expect } from '../../utils';
import {
	germanizedPlugin,
	germanizedProPlugin,
} from '../../resources/e2e-plugins';

const germanizedSlugs = [ germanizedPlugin, germanizedProPlugin ];

for ( const plugin of germanizedSlugs ) {
	setup(
		`Setup ${ plugin.slug } plugin (active)`,
		async ( { requestUtils, plugins } ) => {
			if ( ! ( await requestUtils.isPluginInstalled( plugin.slug ) ) ) {
				await plugins.installPluginFromFile( plugin.zipFilePath );
			}
			await requestUtils.activatePlugin( plugin.slug );
			// additional visits to avoid Germanized welcome wizard page
			await plugins.visit( urls.admin.plugins.home );
			await plugins.visit( urls.admin.plugins.installed );
		}
	);
}

setup( 'Setup Multistep checkout', async ( { requestUtils } ) => {
	const security = await requestUtils.getRegexMatchValueOnPage(
		urls.germanized.admin.home,
		/\"tab_toggle_nonce\":\"([^"&]+)\"/
	);
	const response = await requestUtils.request.post( urls.admin.ajax, {
		form: {
			action: 'woocommerce_gzd_toggle_tab_enabled',
			security,
			enable: 'yes',
			tab: 'multistep_checkout',
		},
	} );
	await expect(
		response,
		'Assert Multistep checkout is enabled successfully'
	).toBeOK();
} );

setup(
	'Setup Germanized additional costs settings',
	async ( { requestUtils } ) => {
		const url = urls.germanized.admin.taxes.additionalCosts;
		const wpnonce = await requestUtils.getPageNonce( url );
		const response = await requestUtils.request.post( url, {
			form: {
				woocommerce_gzd_tax_mode_additional_costs: 'none',
				woocommerce_gzd_tax_mode_additional_costs_detect_main_service:
					'highest_net_amount',
				woocommerce_gzd_tax_mode_additional_costs_split_tax: '',
				woocommerce_gzd_tax_mode_additional_costs_main_service_net_amount:
					'',
				woocommerce_gzd_tax_mode_additional_costs_main_service_tax_rate:
					'',
				save: 'Save changes',
				_wpnonce: wpnonce,
			},
		} );
		await expect(
			response,
			'Assert Germanized additional costs settings are saved successfully'
		).toBeOK();
	}
);
