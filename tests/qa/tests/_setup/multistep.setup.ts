/**
 * Internal dependencies
 */
import { test as setup, urls, expect } from '../../utils';

const germanizedSlugs = [
	'germanized-for-woocommerce',
	'germanized-for-woocommerce-pro',
];

for( const pluginSlug of germanizedSlugs ) {
	setup(
		`Setup ${ pluginSlug } plugin (active)`,
		async ( { requestUtils, plugins } ) => {
			if (
				! ( await requestUtils.isPluginInstalled(
					pluginSlug
				) )
			) {
				await plugins.installPluginFromFile(
					`./resources/files/${ pluginSlug }.zip`
				);
			}
			await requestUtils.activatePlugin( pluginSlug );
			// additional visits to avoid Germanized welcome wizard page
			await plugins.visit( urls.admin.plugins.home );
			await plugins.visit( urls.admin.plugins.installed );
		}
	);
}

setup(
	'Setup Multistep checkout',
	async ( { requestUtils } ) => {
		const security = await requestUtils.getRegexMatchValueOnPage(
			urls.germanized.admin.home,
			/\"tab_toggle_nonce\":\"([^"&]+)\"/,
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
	}
);

setup(
	'Setup Germanized additional costs settings',
	async ( { requestUtils } ) => {
		const url = urls.germanized.admin.taxes.additionalCosts;
		const wpnonce = await requestUtils.getPageNonce( url );
		const response = await requestUtils.request.post( url, {
			form: {
				woocommerce_gzd_tax_mode_additional_costs: 'none',
				woocommerce_gzd_tax_mode_additional_costs_detect_main_service: 'highest_net_amount',
				woocommerce_gzd_tax_mode_additional_costs_split_tax: '',
				woocommerce_gzd_tax_mode_additional_costs_main_service_net_amount: '',
				woocommerce_gzd_tax_mode_additional_costs_main_service_tax_rate: '',
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
