/**
 * Internal dependencies
 */
import { test as setup } from '../../utils';

setup( 'Setup Classic checkout pages', async ( { utils } ) => {
	await utils.configureStore( { classicPages: true } );
} );
