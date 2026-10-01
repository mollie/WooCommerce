/**
 * Internal dependencies
 */
import {
	orders,
	MollieTestData,
	gateways,
	guests,
	products,
} from '../../../resources';

const baseOrder: MollieTestData.ShopOrder = {
	...orders.default,
	products: [ products.mollieSimple100 ],
};

// The E.164 reformatting (AddressMiddleware::getPhoneNumber()) reads the general
// WooCommerce billing/shipping phone field first, for every gateway - it's checked
// before any gateway-specific field is even looked at. So no phone-required gateway
// (In3/Riverty/Vipps/MobilePay) is needed here, and using one would only add a
// country/gateway-availability constraint with no test-value payoff. MyBank is
// Italy-native and phone-optional, so it lets the billing country genuinely be Italy
// (needed for the region hint) without the payment option itself being filtered out.
export const phoneValidationData: MollieTestData.ShopOrder[] = [
	// Baseline: phone already in the gateway's local format - must keep working.
	{
		...baseOrder,
		testId: 'C4567605',
		testLabel: '@Critical',
		customer: guests.italy,
		payment: {
			gateway: gateways.mybank,
			status: 'paid',
		},
	},
	// Local mobile number with no leading zero and no country code (the exact
	// PIWOO-925 repro case). Region hint comes from the billing address, so this must
	// now be reformatted to a valid E.164 number instead of failing at Mollie with a 422.
	{
		...baseOrder,
		testId: 'C4567606',
		testLabel: '@Critical',
		customer: {
			...guests.italy,
			billing: { ...guests.italy.billing, phone: '3887403368' },
			shipping: { ...guests.italy.shipping, phone: '3887403368' },
		},
		payment: {
			gateway: gateways.mybank,
			status: 'paid',
		},
	},
	// Phone already E.164-formatted ("+" prefix) must be left as-is and still work.
	{
		...baseOrder,
		testId: 'C4567607',
		customer: {
			...guests.italy,
			billing: { ...guests.italy.billing, phone: '+393887403368' },
			shipping: { ...guests.italy.shipping, phone: '+393887403368' },
		},
		payment: {
			gateway: gateways.mybank,
			status: 'paid',
		},
	},
	// iDEAL doesn't require a phone number, so checkout must still complete even
	// though this value can't be formatted into any valid E.164 number.
	{
		...baseOrder,
		testId: 'C4567608',
		customer: {
			...guests.netherlands,
			billing: { ...guests.netherlands.billing, phone: '99999999999999' },
			shipping: {
				...guests.netherlands.shipping,
				phone: '99999999999999',
			},
		},
		payment: {
			gateway: gateways.ideal,
			status: 'open',
		},
	},
];
