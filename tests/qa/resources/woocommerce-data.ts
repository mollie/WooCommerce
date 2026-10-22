/**
 * External dependencies
 */
import { freeShipping } from '@inpsyde/playwright-utils/build/e2e/plugins/woocommerce';

// Local copies of the playwright-utils coupons and shipping with minimal amounts for manual live-mode tests (8.2.0 Express)

// Title kept: specs select the rate by flatRate.settings.title
export const flatRate: WooCommerce.CreateShippingZoneMethod = {
	method_id: 'flat_rate',
	enabled: true,
	settings: {
		title: 'Very flat rate',
		cost: '0.01',
		tax_status: 'taxable',
	},
	total: '0.01',
};

// Second rate for the shipping rate change cases
export const flatRate2: WooCommerce.CreateShippingZoneMethod = {
	method_id: 'flat_rate',
	enabled: true,
	settings: {
		title: 'Second flat rate',
		cost: '0.02',
		tax_status: 'taxable',
	},
	total: '0.02',
};

// Exported for the specs that use it, not added to the zone: a free rate makes the rate change cases unclear
export { freeShipping };

export const shippingZones: {
	[ key: string ]: WooCommerce.CreateShippingZone;
} = {
	worldwide: {
		name: 'Worldwide',
		methods: {
			flat_rate: flatRate,
			flat_rate_2: flatRate2,
		},
	},
};

// Customer 2 for EXP-51b: only the billing country is saved, so Express knows the tax country but no address
export const customerBillingCountryOnly: WooCommerce.CreateCustomer = {
	email: 'emma.weber@personal.example.de',
	first_name: 'Emma',
	last_name: 'Weber',
	username: 'emma.weber',
	password: 'emma.weber',
	billing: {
		first_name: '',
		last_name: '',
		company: '',
		address_1: '',
		address_2: '',
		city: '',
		state: '',
		postcode: '',
		country: 'DE',
		// The REST API rejects an empty billing email
		email: 'emma.weber@personal.example.de',
		phone: '',
	},
	shipping: {
		first_name: '',
		last_name: '',
		company: '',
		address_1: '',
		address_2: '',
		city: '',
		state: '',
		postcode: '',
		country: '',
	},
};

const percent10: WooCommerce.CreateCoupon = {
	code: 'percent 10',
	discount_type: 'percent',
	amount: '10.00',
};

const percent100: WooCommerce.CreateCoupon = {
	code: 'percent 100',
	discount_type: 'percent',
	amount: '100.00',
};

const fixed001: WooCommerce.CreateCoupon = {
	code: 'fixed 0.01',
	discount_type: 'fixed_cart',
	amount: '0.01',
};

export const coupons: {
	[ key: string ]: WooCommerce.CreateCoupon;
} = {
	percent10,
	percent100,
	fixed001,
};
