export namespace MollieSettings {
	export type ApiKeys = {
		testModeEnabled?: 'yes' | 'no';
		liveApiKey?: string;
		testApiKey?: string;
	};

	export type OrderStatusCancelledPayment = 'pending' | 'cancelled';

	export type PaymentLocale =
		| 'detect_by_browser'
		| 'en_US'
		| 'nl_NL'
		| 'nl_BE'
		| 'fr_FR'
		| 'fr_BE'
		| 'de_DE'
		| 'de_AT'
		| 'de_CH'
		| 'es_ES'
		| 'ca_ES'
		| 'pt_PT'
		| 'it_IT'
		| 'nb_NO'
		| 'sv_SE'
		| 'fi_FI'
		| 'da_DK'
		| 'is_IS'
		| 'hu_HU'
		| 'pl_PL'
		| 'lv_LV'
		| 'lt_LT';

	export type ApiMethod = 'order' | 'payment';

	export type ApiPaymentDescription =
		| '{orderNumber}'
		| '{storeName}'
		| '{customer.firstname}'
		| '{customer.lastname}'
		| '{customer.company}';

	export type PaymentCapture = 'immediate_capture' | 'later_capture';

	export type Advanced = {
		debugLogEnabled?: boolean;
		orderStatusCancelledPayments?: OrderStatusCancelledPayment;
		paymentLocale?: PaymentLocale;
		customerDetails?: boolean;
		apiMethod?: ApiMethod;
		apiPaymentDescription?: ApiPaymentDescription;
		gatewayFeeLabel?: string;
		removeOptionsAndTransients?: boolean;
		placePaymentOnhold?: PaymentCapture;
	};

	export type PaymentSurcharge =
		| 'no_fee'
		| 'fixed_fee'
		| 'percentage'
		| 'fixed_fee_percentage';

	export type InitialOrderStatus = 'on-hold' | 'pending';

	export type PaypalButtonTextLanguageAndColor =
		| 'en-buy-pill-blue'
		| 'en-buy-rounded-blue'
		| 'en-buy-pill-golden'
		| 'en-buy-rounded-golden'
		| 'en-buy-pill-gray'
		| 'en-buy-rounded-gray'
		| 'en-buy-pill-white'
		| 'en-buy-rounded-white'
		| 'en-checkout-pill-black'
		| 'en-checkout-rounded-black'
		| 'en-checkout-pill-blue'
		| 'en-checkout-rounded-blue'
		| 'en-checkout-pill-golden'
		| 'en-checkout-rounded-golden'
		| 'en-checkout-pill-gray'
		| 'en-checkout-rounded-gray'
		| 'en-checkout-pill-white'
		| 'en-checkout-rounded-white'
		| 'nl-buy-pill-black'
		| 'nl-buy-rounded-black'
		| 'nl-buy-pill-blue'
		| 'nl-buy-rounded-blue'
		| 'nl-buy-pill-golden'
		| 'nl-buy-rounded-golden'
		| 'nl-buy-pill-gray'
		| 'nl-buy-rounded-gray'
		| 'nl-buy-pill-white'
		| 'nl-buy-rounded-white'
		| 'nl-checkout-pill-black'
		| 'nl-checkout-rounded-black'
		| 'nl-checkout-pill-blue'
		| 'nl-checkout-rounded-blue'
		| 'nl-checkout-pill-golden'
		| 'nl-checkout-rounded-golden'
		| 'nl-checkout-pill-gray'
		| 'nl-checkout-rounded-gray'
		| 'nl-checkout-pill-white'
		| 'nl-checkout-rounded-white'
		| 'de-buy-pill-black'
		| 'de-buy-rounded-black'
		| 'de-buy-pill-blue'
		| 'de-buy-rounded-blue'
		| 'de-buy-pill-golden'
		| 'de-buy-rounded-golden'
		| 'de-buy-pill-gray'
		| 'de-buy-rounded-gray'
		| 'de-buy-pill-white'
		| 'de-buy-rounded-white'
		| 'de-checkout-pill-black'
		| 'de-checkout-rounded-black'
		| 'de-checkout-pill-blue'
		| 'de-checkout-rounded-blue'
		| 'de-checkout-pill-golden'
		| 'de-checkout-rounded-golden'
		| 'de-checkout-pill-gray'
		| 'de-checkout-rounded-gray'
		| 'de-checkout-pill-white'
		| 'de-checkout-rounded-white'
		| 'fr-buy-rounded-gold'
		| 'fr-checkout-rounded-gold'
		| 'fr-checkout-rounded-silver'
		| 'pl-buy-rounded-gold'
		| 'pl-checkout-rounded-gold'
		| 'pl-checkout-rounded-silver';

	export type VoucherProductCategory =
		| 'no_category'
		| 'meal'
		| 'eco'
		| 'gift';

	export type Gateway = {
		enabled?: boolean;
		use_api_title?: 'yes' | 'no';
		title?: string;
		display_logo?: 'yes' | 'no';
		enable_custom_logo?: 'yes' | 'no';
		custom_logo_path?: string;
		upload_logo?: string;
		description?: string;
		'allowed_countries[]'?: string[];

		payment_surcharge?: PaymentSurcharge;
		fixed_fee?: string;
		maximum_limit?: string;
		percentage?: string;
		surcharge_limit?: string;

		activate_expiry_days_setting?: 'yes' | 'no';
		order_dueDate?: string;
		issuers_dropdown_shown?: 'yes' | 'no';
		initial_order_status?: InitialOrderStatus;
		mollie_components_enabled?: 'yes' | 'no';
		issuers_empty_option?: string;

		skip_mollie_payment_screen?: 'yes' | 'no'; // banktransfer

		mollie_apple_pay_button_enabled_cart?: 'yes' | 'no'; // applepay
		mollie_apple_pay_button_enabled_product?: 'yes' | 'no'; // applepay
		mollie_apple_pay_button_enabled_express_checkout?: 'yes' | 'no'; // applepay

		mollie_paypal_button_enabled_cart?: 'yes' | 'no'; // paypal
		mollie_paypal_button_enabled_product?: 'yes' | 'no'; // paypal
		paypal_color?: PaypalButtonTextLanguageAndColor; // paypal
		mollie_paypal_button_minimum_amount?: string; // paypal

		mealvoucher_category_default?: VoucherProductCategory; // voucher

		mollie_creditcard_icons_enabler?: 'yes' | 'no'; // creditcard
		mollie_creditcard_icons_amex?: 'yes' | 'no'; // creditcard
		mollie_creditcard_icons_cartasi?: 'yes' | 'no'; // creditcard
		mollie_creditcard_icons_cartebancaire?: 'yes' | 'no'; // creditcard
		mollie_creditcard_icons_maestro?: 'yes' | 'no'; // creditcard
		mollie_creditcard_icons_mastercard?: 'yes' | 'no'; // creditcard
		mollie_creditcard_icons_visa?: 'yes' | 'no'; // creditcard
		mollie_creditcard_icons_vpay?: 'yes' | 'no'; // creditcard
	};
}

export type MollieGateway = {
	slug: string;
	name: string;
	country: string;
	currency?: string;
	minAmount?: string;
	maxAmount?: string;
	settings?: MollieSettings.Gateway;
};

export type MolliePaymentStatus =
	| 'open'
	| 'pending'
	| 'paid'
	| 'authorized'
	| 'failed'
	| 'canceled'
	| 'expired';

export type BankIssuer =
	| 'ABN AMRO'
	| 'ING'
	| 'Rabobank'
	| 'ASN Bank'
	| 'bunq'
	| 'Knab'
	| 'N26'
	| 'NN'
	| 'Regiobank'
	| 'Revolut'
	| 'SNS Bank'
	| 'Triodos'
	| 'Van Lanschot Kempen'
	| 'Yoursafe'
	| 'CBC'
	| 'KBC';

export type MolliePayment = {
	gateway: MollieGateway;
	status: MolliePaymentStatus;
	bankIssuer?: BankIssuer;
	card?: WooCommerce.CreditCard;
	isVaulted?: boolean;
	saveToAccount?: boolean;
	amount?: number;
};

export namespace MollieTestData {
	export type SurchargeTest = {
		testId: string;
		gateway: string;
		product?: WooCommerce.CreateProduct;
		expectedFeeText?: string;
	}

	export type SurchargeTestsGroup = {
		describeTitle: string;
		testTitle: string;
		expectedAmount: number;
		expectedFeeText: string;
		settings: MollieSettings.Gateway;
		tests: MollieTestData.SurchargeTest[];
	};

	export type PaymentStatus = {
		testId: string;
		gatewaySlug: string;
		paymentStatus: MolliePaymentStatus;
		orderStatus: WooCommerce.OrderStatus;
		card?: WooCommerce.CreditCard;
		mollieComponentsEnabled?: 'yes' | 'no';
		bankIssuer?: string;
		billingCompany?: string;
	};
}
