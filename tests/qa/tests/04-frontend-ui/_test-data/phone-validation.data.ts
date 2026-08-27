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

export const phoneValidationData: MollieTestData.ShopOrder[] = [
	// Baseline: phone already in the gateway's local format - must keep working.
	{
		...baseOrder,
		testId: 'C4567605',
		testLabel: '@Critical',
		customer: guests.germany,
		payment: {
			gateway: gateways.riverty,
			status: 'authorized',
		},
	},
	// Local mobile number with no leading zero and no country code (e.g. Italian
	// mobile "3887403368"). Region hint comes from the billing address, so this must
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
			gateway: gateways.riverty,
			status: 'authorized',
		},
	},
	// Phone already E.164-formatted ("+" prefix) must be left as-is and still work.
	{
		...baseOrder,
		testId: 'C4567607',
		customer: {
			...guests.germany,
			billing: { ...guests.germany.billing, phone: '+4917612345678' },
			shipping: { ...guests.germany.shipping, phone: '+4917612345678' },
		},
		payment: {
			gateway: gateways.riverty,
			status: 'authorized',
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
