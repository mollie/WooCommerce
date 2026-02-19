/**
 * External dependencies
 */
import {
	testPluginInstallationFromFile,
	testPluginReinstallationFromFile,
	testPluginDeactivation,
	testPluginRemoval,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { molliePlugin } from '../../resources';

testPluginInstallationFromFile( 'C419986', molliePlugin, '@Critical' );

testPluginReinstallationFromFile( 'C3322', molliePlugin );

testPluginDeactivation( 'C3319', molliePlugin );

testPluginRemoval( 'C3318', molliePlugin );
