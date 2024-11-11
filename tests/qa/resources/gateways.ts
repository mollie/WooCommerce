/**
 * Internal dependencies
 */
import { MollieGateway, MollieSettings } from './types';

export const defaultGatewaySettings: MollieSettings.Gateway = {
	enableGateway: true,
	useApiTitle: false,
	displayLogo: true,
	enableCustomLogo: false,
	description: '',
	sellToCountries: [],

	payment_surcharge: 'no_fee',
	fixed_fee: '',
	maximum_limit: '',
	percentage: '',
	surcharge_limit: '',

	activateExpiryDaysSetting: false,
	orderDueDate: '10',
	enableMollieComponents: false,
};

const alma: MollieGateway = {
	country: 'france', // Belgium also supported
	minAmount: '50.00',
	maxAmount: '2000.00',
	slug: 'alma',
	name: 'Pay in 3 or 4 installments free of charge',
	settings: {
		...defaultGatewaySettings,
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
		title: 'Apple Pay',
		enableApplePayButtonOnCart: false,
		enableApplePayButtonOnProduct: false,
		enableApplePayExpressButtonOnCheckout: false,
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
		title: 'Bancontact',
		initialOrderStatus: 'on-hold',
	},
};

const banktransfer: MollieGateway = {
	country: 'germany', // Europe
	minAmount: '1.00',
	slug: 'banktransfer',
	name: 'Bank transfer',
	settings: {
		...defaultGatewaySettings,
		title: 'Bank transfer',
		initialOrderStatus: 'on-hold',
		orderDueDate: '12',
		banktransferSkipMolliePaymentScreen: false,
	},
};

const belfius: MollieGateway = {
	country: 'belgium',
	minAmount: '0.01',
	slug: 'belfius',
	name: 'Belfius Pay Button',
	settings: {
		...defaultGatewaySettings,
		title: 'Belfius Pay Button',
		initialOrderStatus: 'on-hold',
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
		title: 'Card',
		enableMollieComponents: false,
		enableMollieCreditcardIcons: false,
		enableMollieCreditcardIconsAmex: false,
		enableMollieCreditcardIconsCartaSi: false,
		enableMollieCreditcardICarteBancaire: false,
		enableMollieCreditcardIconsMaestro: false,
		enableMollieCreditcardIconsMastercard: false,
		enableMollieCreditcardIconsVisa: false,
		enableMollieCreditcardIconsVpay: false,
	},
};

const directdebit: MollieGateway = {
	country: 'germany', // Europe
	minAmount: '1.00',
	slug: 'directdebit',
	name: 'SEPA Direct Debit',
	settings: {
		...defaultGatewaySettings,
		title: 'SEPA Direct Debit',
		initialOrderStatus: 'on-hold',
	},
};

const eps: MollieGateway = {
	country: 'austria',
	minAmount: '0.01',
	slug: 'eps',
	name: 'eps',
	settings: {
		...defaultGatewaySettings,
		title: 'eps',
		initialOrderStatus: 'on-hold',
	},
};

const giftcard: MollieGateway = {
	country: 'germany',
	minAmount: '0.01',
	slug: 'giftcard',
	name: 'Gift cards',
	settings: {
		...defaultGatewaySettings,
		title: 'Gift cards',
		description: 'Select your gift card',
		showIssuersDropdown: true,
		issuersEmptyOption: 'Select your gift card',
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
		title: 'iDEAL',
		initialOrderStatus: 'on-hold',
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
		title: 'KBC/CBC Payment Button',
		description: 'Select your bank',
		showIssuersDropdown: true,
		issuersEmptyOption: 'Select your bank',
		initialOrderStatus: 'on-hold',
	},
};

const klarna: MollieGateway = {
	country: 'germany', // AT, BE, DK, FI, FR, DE, IT, IE, NL, NO, PT, ES, SE, IE, CH, UK
	minAmount: '1.00',
	slug: 'klarna',
	name: 'Pay with Klarna',
	settings: {
		...defaultGatewaySettings,
		title: 'Pay with Klarna',
	},
};

const mybank: MollieGateway = {
	country: 'italy',
	minAmount: '1.00',
	slug: 'mybank',
	name: 'MyBank',
	settings: {
		...defaultGatewaySettings,
		title: 'MyBank',
	},
};

const paybybank: MollieGateway = {
	country: 'uk',
	minAmount: '1.00',
	slug: 'paybybank',
	name: 'Pay by Bank',
	currency: 'GBP',
	settings: {
		...defaultGatewaySettings,
		title: 'Pay by Bank',
	},
};

const payconiq: MollieGateway = {
	country: 'belgium',
	minAmount: '0.01',
	slug: 'payconiq',
	name: 'Payconiq',
	settings: {
		...defaultGatewaySettings,
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
		title: 'PayPal',
		paypalDisplayOnCart: false,
		paypalDisplayOnProduct: false,
		paypalButtonTextLanguageAndColor: 'en-buy-pill-blue',
		paypalMinimumAmountToDisplayButton: '0',
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
		title: 'Satispay',
	},
};

const trustly: MollieGateway = {
	country: 'germany', // Europe
	minAmount: '1.00',
	slug: 'trustly',
	name: 'Trustly',
	settings: {
		...defaultGatewaySettings,
		title: 'Trustly',
		initialOrderStatus: 'on-hold',
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
		title: 'Vouchers',
		voucherDefaultProductsCategory: 'no_category',
	},
};

// Deprecated gateways
const giropay: MollieGateway = {
	country: 'germany',
	minAmount: '1.00',
	slug: 'giropay',
	name: 'Giropay',
	settings: {
		...defaultGatewaySettings,
		title: 'Giropay',
		initialOrderStatus: 'on-hold',
	},
};

const klarnapaylater: MollieGateway = {
	country: 'germany', // Austria, Germany, Netherlands
	minAmount: '50.00',
	maxAmount: '2000.00',
	slug: 'klarnapaylater',
	name: 'Pay later.',
	settings: {
		...defaultGatewaySettings,
		title: 'Pay later.',
	},
};

const klarnapaynow: MollieGateway = {
	country: 'germany', // Austria, Germany, Netherlands
	minAmount: '50.00',
	maxAmount: '2000.00',
	slug: 'klarnapaynow',
	name: 'Pay now.',
	settings: {
		...defaultGatewaySettings,
		title: 'Pay now.',
	},
};

const klarnasliceit: MollieGateway = {
	country: 'germany', // Austria, Germany, Netherlands
	minAmount: '50.00',
	maxAmount: '2000.00',
	slug: 'klarnasliceit',
	name: 'Slice it.',
	settings: {
		...defaultGatewaySettings,
		title: 'Slice it.',
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
	mybank,
	// paybybank, // currency: GBP
	payconiq,
	paypal,
	paysafecard,
	// pointofsale,
	przelewy24,
	riverty, // >50.00
	satispay,
	trustly,
	twint, // currency: CHF
	voucher,

	// Deprecated gateways
	// giropay,
	// klarnapaylater, // >50.00
	// klarnapaynow, // >50.00
	// klarnasliceit, // >50.00
};
