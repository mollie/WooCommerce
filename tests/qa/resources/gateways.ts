/**
 * Internal dependencies
 */
import { MollieGateway, MollieSettings } from './types';

export const defaultGatewaySettings: MollieSettings.Gateway = {
	enabled: true,
	use_api_title: 'no',
	display_logo: 'yes',
	enable_custom_logo: 'no',
	description: '',
	'allowed_countries[]': [],

	payment_surcharge: 'no_fee',
	fixed_fee: '',
	maximum_limit: '',
	percentage: '',
	surcharge_limit: '',

	activate_expiry_days_setting: 'no',
	order_dueDate: '10',
	mollie_components_enabled: 'no',
};

const alma: MollieGateway = {
	country: 'france', // Belgium also supported
	minAmount: '50.00',
	maxAmount: '2000.00',
	slug: 'alma',
	name: 'Pay in 3 or 4 installments free of charge',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_alma',
		title: 'Pay in 3 or 4 installments free of charge',
	},
};

const applepay: MollieGateway = {
	country: 'germany', // Global availability
	minAmount: '0.01',
	slug: 'applepay',
	name: 'Apple Pay',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_applepay',
		title: 'Apple Pay',
		mollie_apple_pay_button_enabled_cart: 'no',
		mollie_apple_pay_button_enabled_product: 'no',
		mollie_apple_pay_button_enabled_express_checkout: 'no',
	},
};

const bacs: MollieGateway = {
	country: 'uk',
	minAmount: '1.00',
	slug: 'bacs',
	name: 'BACS Direct Debit',
	currency: 'GBP',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_bacs',
		title: 'BACS Direct Debit',
	},
};

const bancomatpay: MollieGateway = {
	country: 'italy',
	minAmount: '0.01',
	slug: 'bancomatpay',
	name: 'Bancomat Pay',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_bancomatpay',
		title: 'Bancomat Pay',
	},
};

const bancontact: MollieGateway = {
	country: 'belgium',
	minAmount: '1.00',
	maxAmount: '50000.00',
	slug: 'bancontact',
	name: 'Bancontact',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_bancontact',
		title: 'Bancontact',
		initial_order_status: 'on-hold',
	},
};

const banktransfer: MollieGateway = {
	country: 'germany', // Europe
	minAmount: '1.00',
	slug: 'banktransfer',
	name: 'Bank transfer',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_banktransfer',
		title: 'Bank transfer',
		initial_order_status: 'on-hold',
		order_dueDate: '12',
		skip_mollie_payment_screen: 'no',
	},
};

const belfius: MollieGateway = {
	country: 'belgium',
	minAmount: '0.01',
	slug: 'belfius',
	name: 'Belfius Pay Button',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_belfius',
		title: 'Belfius Pay Button',
		initial_order_status: 'on-hold',
	},
};

const billie: MollieGateway = {
	country: 'germany', // Netherlands also supported
	minAmount: '100.00',
	maxAmount: '50000.00',
	slug: 'billie',
	name: 'Pay by Invoice for Businesses - Billie',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_billie',
		title: 'Pay by Invoice for Businesses - Billie',
	},
};

const blik: MollieGateway = {
	country: 'poland',
	minAmount: '1.00',
	maxAmount: '10000.00',
	slug: 'blik',
	name: 'Blik',
	currency: 'PLN',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_blik',
		title: 'Blik',
	},
};

const creditcard: MollieGateway = {
	country: 'germany', // Global availability
	minAmount: '0.01',
	slug: 'creditcard',
	name: 'Card',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_creditcard',
		title: 'Card',
		mollie_components_enabled: 'yes',
		mollie_creditcard_icons_enabled: 'no',
		mollie_creditcard_icons_amex: 'no',
		mollie_creditcard_icons_cartasi: 'no',
		mollie_creditcard_icons_cartebancaire: 'no',
		mollie_creditcard_icons_maestro: 'no',
		mollie_creditcard_icons_mastercard: 'no',
		mollie_creditcard_icons_visa: 'no',
		mollie_creditcard_icons_vpay: 'no',
	},
};

const directdebit: MollieGateway = {
	country: 'germany', // Europe
	minAmount: '1.00',
	slug: 'directdebit',
	name: 'SEPA Direct Debit',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_directdebit',
		title: 'SEPA Direct Debit',
		initial_order_status: 'on-hold',
	},
};

const eps: MollieGateway = {
	country: 'austria',
	minAmount: '0.01',
	slug: 'eps',
	name: 'eps',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_eps',
		title: 'eps',
		initial_order_status: 'on-hold',
	},
};

const giftcard: MollieGateway = {
	country: 'germany',
	minAmount: '0.01',
	slug: 'giftcard',
	name: 'Gift cards',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_giftcard',
		title: 'Gift cards',
		description: 'Select your gift card',
		issuers_dropdown_shown: 'no',
		issuers_empty_option: 'Select your gift card',
	},
};

const ideal: MollieGateway = {
	country: 'netherlands',
	minAmount: '1.00',
	maxAmount: '50000.00',
	slug: 'ideal',
	name: 'iDEAL',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_ideal',
		title: 'iDEAL',
		initial_order_status: 'on-hold',
	},
};

const in3: MollieGateway = {
	country: 'netherlands',
	minAmount: '100.00',
	maxAmount: '5000.00',
	slug: 'in3',
	name: 'iDEAL Pay in 3 instalments, 0% interest',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_in3',
		title: 'iDEAL Pay in 3 instalments, 0% interest',
		description: 'iDEAL Pay in 3 instalments, 0% interest',
	},
};

const kbc: MollieGateway = {
	country: 'belgium',
	minAmount: '1.00',
	slug: 'kbc',
	name: 'KBC/CBC Payment Button',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_kbc',
		title: 'KBC/CBC Payment Button',
		description: 'Select your bank',
		issuers_dropdown_shown: 'yes',
		issuers_empty_option: 'Select your bank',
		initial_order_status: 'on-hold',
	},
};

const klarna: MollieGateway = {
	country: 'germany', // AT, BE, DK, FI, FR, DE, IT, IE, NL, NO, PT, ES, SE, IE, CH, UK
	minAmount: '1.00',
	slug: 'klarna',
	name: 'Pay with Klarna',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_klarna',
		title: 'Pay with Klarna',
	},
};

const mbway: MollieGateway = {
	country: 'portugal',
	minAmount: '0.01',
	maxAmount: '5000.00',
	slug: 'mbway',
	name: 'MB Way',
	currency: 'EUR',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_mbway',
		title: 'MB Way',
	},
};

const multibanco: MollieGateway = {
	country: 'portugal',
	minAmount: '0.01',
	maxAmount: '5000.00',
	slug: 'multibanco',
	name: 'Multibanco',
	currency: 'EUR',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_multibanco',
		title: 'Multibanco',
	},
};

const mybank: MollieGateway = {
	country: 'italy',
	minAmount: '1.00',
	slug: 'mybank',
	name: 'MyBank',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_mybank',
		title: 'MyBank',
	},
};

const paybybank: MollieGateway = {
	country: 'germany', // AT, BE, CY, EE, FI, FR, DE, GR, IE, IT, LV, LT, LU, MT, NL, PT, SK, SI, ES, UK
	minAmount: '0.01',
	slug: 'paybybank',
	name: 'Pay By Bank',
	currency: 'EUR', // EUR, GBP
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_paybybank',
		title: 'Pay By Bank',
	},
};

const payconiq: MollieGateway = {
	country: 'belgium',
	minAmount: '0.01',
	slug: 'payconiq',
	name: 'Payconiq',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_payconiq',
		title: 'Payconiq',
	},
};

const paypal: MollieGateway = {
	country: 'germany', // Global availability
	minAmount: '0.01',
	slug: 'paypal',
	name: 'PayPal',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_paypal',
		title: 'PayPal',
		mollie_paypal_button_enabled_cart: 'no',
		mollie_paypal_button_enabled_product: 'no',
		paypal_color: 'en-buy-pill-blue',
		mollie_paypal_button_minimum_amount: '0',
	},
};

const paysafecard: MollieGateway = {
	country: 'germany',
	minAmount: '1.00',
	maxAmount: '1000.00',
	slug: 'paysafecard',
	name: 'paysafecard',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_paysafecard',
		title: 'paysafecard',
	},
};

const pointofsale: MollieGateway = {
	country: 'germany',
	minAmount: '1.00',
	slug: 'pointofsale',
	name: 'Point of sale',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_',
		title: 'Point of sale',
	},
};

const przelewy24: MollieGateway = {
	country: 'poland',
	minAmount: '1.00',
	slug: 'przelewy24',
	name: 'Przelewy24',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_przelewy24',
		title: 'Przelewy24',
	},
};

const riverty: MollieGateway = {
	country: 'germany',
	minAmount: '50.00',
	maxAmount: '2000.00',
	slug: 'riverty',
	name: 'Buy now, pay later with Riverty',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_riverty',
		title: 'Buy now, pay later with Riverty',
	},
};

const satispay: MollieGateway = {
	country: 'italy',
	minAmount: '1.00',
	slug: 'satispay',
	name: 'Satispay',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_satispay',
		title: 'Satispay',
	},
};

const swish: MollieGateway = {
	country: 'sweden',
	currency: 'SEK',
	minAmount: '0.01',
	maxAmount: '115000.00',
	slug: 'swish',
	name: 'Swish',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_swish',
		title: 'Swish',
	},
};

const trustly: MollieGateway = {
	country: 'germany', // Europe
	minAmount: '1.00',
	slug: 'trustly',
	name: 'Trustly',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_trustly',
		title: 'Trustly',
		initial_order_status: 'on-hold',
	},
};

const twint: MollieGateway = {
	country: 'switzerland',
	minAmount: '0.01',
	slug: 'twint',
	name: 'TWINT',
	currency: 'CHF',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_twint',
		title: 'TWINT',
	},
};

const voucher: MollieGateway = {
	country: 'germany',
	minAmount: '1.00',
	slug: 'voucher',
	name: 'Vouchers',
	settings: {
		...defaultGatewaySettings,
		id: 'mollie_wc_gateway_voucher',
		title: 'Vouchers',
		mealvoucher_category_default: 'no_category',
	},
};

export const gateways: {
	[ key: string ]: MollieGateway;
} = {
	alma, // >50.00
	// applepay,
	// bacs, // currency: GBP
	bancomatpay,
	bancontact,
	banktransfer,
	belfius,
	billie, // >100.00
	blik, // currency: PLN
	creditcard,
	directdebit,
	eps,
	giftcard,
	ideal,
	in3, // >100.00
	kbc,
	klarna,
	mbway,
	multibanco,
	mybank,
	paybybank, // currency: GBP
	payconiq,
	paypal,
	paysafecard,
	// pointofsale,
	przelewy24,
	riverty, // >50.00
	satispay,
	swish, // Sweden, currency: SEK
	trustly,
	twint, // currency: CHF
	voucher,
};
