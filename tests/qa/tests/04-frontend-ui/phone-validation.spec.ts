/**
 * Internal dependencies
 */
import { test } from '../../utils';
import { testReachesMollieHostedCheckout } from './_test-scenarios';
import { phoneValidationData } from './_test-data';

test.describe( () => {
	for ( const testData of phoneValidationData ) {
		testReachesMollieHostedCheckout( testData );
	}
} );
