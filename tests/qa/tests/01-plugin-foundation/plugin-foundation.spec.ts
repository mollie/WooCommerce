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

testPluginReinstallationFromFile( 'C3322', molliePlugin, '@Critical' );

testPluginDeactivation( 'C3319', molliePlugin, '@Critical' );

testPluginRemoval( 'C3318', molliePlugin, '@Critical' );
