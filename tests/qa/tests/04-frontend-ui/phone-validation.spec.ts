/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { shopConfigDefault } from '../../resources';
import { testReachesMollieHostedCheckout } from './_test-scenarios';
import { phoneValidationData } from './_test-data';

test.beforeAll( async ( { utils } ) => {
	await utils.configureStore( shopConfigDefault );
	await utils.installAndActivateMollie();
	await utils.cleanReconnectMollie();
} );

test.describe( () => {
	for ( const testData of phoneValidationData ) {
		testReachesMollieHostedCheckout( testData );
	}
} );
