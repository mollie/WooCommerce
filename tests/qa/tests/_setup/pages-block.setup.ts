/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';

setup( 'Setup Block checkout pages', async ( { utils } ) => {
	await utils.configureStore( { classicPages: false } );
} );
