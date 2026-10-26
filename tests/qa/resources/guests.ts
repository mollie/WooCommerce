/**
 * External dependencies
 */
import { guests as baseGuests } from '@inpsyde/playwright-utils/build/e2e/plugins/woocommerce';

// Billink rejects the shared NL test address (Mollie 422 on billingAddress),
// so NL guests use a real Dutch street and postcode format.
const nlAddress = {
	address_1: 'Damrak 1',
	postcode: '1012 LG',
};

export const guests: typeof baseGuests = {
	...baseGuests,
	netherlands: {
		...baseGuests.netherlands,
		billing: { ...baseGuests.netherlands.billing, ...nlAddress },
		shipping: { ...baseGuests.netherlands.shipping, ...nlAddress },
	},
};
