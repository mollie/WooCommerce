/**
 * Internal dependencies
 */
import { testPaymentStatusOnClassicCheckout } from './_test-scenarios';
import { classicCheckoutNl } from './_test-data';

// Billink only works with an NL merchant profile, so this runs against a
// dedicated NL Mollie account/shop config (see the `transaction:nl-classic`
// shard in tests/qa/project-shards.ts).
for ( const testData of classicCheckoutNl ) {
	testPaymentStatusOnClassicCheckout( testData );
}
