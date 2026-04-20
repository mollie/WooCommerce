/**
 * Internal dependencies
 */
import { test as setup, urls, expect } from '../../utils';
import {
	germanizedPlugin,
	germanizedProPlugin,
} from '../../resources/e2e-plugins';

setup(
	'setup:multistep:checkout;',
	async ( { requestUtils, plugins } ) => {
		
		setup.setTimeout( 2 * 60_000 );

		await setup.step(
			`Setup ${ germanizedPlugin.name } plugin (active)`,
			async () => {
				// in CI plugin is installed in tests/qa/bin/test-env-setup.js, so we can skip installation step
				if( ! process.env.CI ) {
					if ( ! ( await requestUtils.isPluginInstalled( germanizedPlugin.slug ) ) ) {
						await plugins.installPluginFromFile( germanizedPlugin.zipFilePath );
					}
				}
				await requestUtils.activatePlugin( germanizedPlugin.slug );
				await plugins.visit( urls.admin.plugins.home );
				await plugins.visit( urls.admin.plugins.installed );
			}
		);

		await setup.step(
			`Setup ${ germanizedProPlugin.name } plugin (active)`,
			async () => {
				// in CI plugin is added directly to the wp-content/plugins folder, so we can skip installation step
				if( ! process.env.CI ) {
					if (
						! ( await requestUtils.isPluginInstalled( germanizedProPlugin.slug ) )
					) {
						await plugins.installPluginFromFile( germanizedProPlugin.zipFilePath );
					}
				}
				await requestUtils.activatePlugin( germanizedProPlugin.slug );
				await plugins.visit( urls.admin.plugins.home );
				await plugins.visit( urls.admin.plugins.installed );
			}
		);

		await setup.step(
			'Enable multistep checkout',
			async () => {
				const security =
					await requestUtils.getRegexMatchValueOnPage(
						urls.germanized.admin.home,
						/\"tab_toggle_nonce\":\"([^"&]+)\"/
					);
				const response =
					await requestUtils.request.post( urls.admin.ajax, {
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

		await setup.step(
			'Configure additional costs settings',
			async () => {
				const url =
					urls.germanized.admin.taxes.additionalCosts;
				const wpnonce =
					await requestUtils.getPageNonce( url );
				const response =
					await requestUtils.request.post( url, {
						form: {
							woocommerce_gzd_tax_mode_additional_costs:
								'none',
							woocommerce_gzd_tax_mode_additional_costs_detect_main_service:
								'highest_net_amount',
							woocommerce_gzd_tax_mode_additional_costs_split_tax:
								'',
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
	}
);