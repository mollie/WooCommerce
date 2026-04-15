/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';
import { setupWooCommerce } from '../../utils/helpers/';

setup.describe( 'setup:wc;', async () => {
	await setupWooCommerce();
} );
