const mollieSimple100: WooCommerce.CreateProduct = {
	name: 'Mollie Simple Test Product 100',
	slug: 'mollie-product-simple-100',
	type: 'simple',
	regular_price: '0.01',
	description:
		'Our clothes are juicy, sweet, and ideal for a healthy snack or enhancing your favorite recipes.',
	short_description: 'Fresh, crisp clothes perfect for snacks and desserts.',
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/beanie-2.jpg',
		},
	],
	virtual: false,
	downloadable: false,
	meta_data: [ { key: '_mollie_voucher_category', value: 'no_category' } ],
};

const mollieSimleVoucherMeal100: WooCommerce.CreateProduct = {
	...mollieSimple100,
	name: 'Mollie Simple Voucher Meal Test Product 100',
	slug: 'mollie-product-simple-voucher-meal-100',
	meta_data: [ { key: '_mollie_voucher_category', value: 'meal' } ],
};

const mollieSimleVoucherEco100: WooCommerce.CreateProduct = {
	...mollieSimple100,
	name: 'Mollie Simple Voucher Eco Test Product 100',
	slug: 'mollie-product-simple-voucher-eco-100',
	meta_data: [ { key: '_mollie_voucher_category', value: 'eco' } ],
};

const mollieSimleVoucherGift100: WooCommerce.CreateProduct = {
	...mollieSimple100,
	name: 'Mollie Simple Voucher Gift Test Product 100',
	slug: 'mollie-product-simple-voucher-gift-100',
	meta_data: [ { key: '_mollie_voucher_category', value: 'gift' } ],
};

const mollieVirtual100: WooCommerce.CreateProduct = {
	...mollieSimple100,
	name: 'Mollie Virtual Test Product 100',
	slug: 'mollie-product-virtual-100',
	virtual: true,
	downloadable: true,
};

const mollieVariable100: WooCommerce.CreateProduct = {
	name: 'Mollie Variable Test Product 100',
	slug: 'mollie-product-variable-100',
	type: 'variable',
	regular_price: '0.01',
	description:
		'Green and red clothes. Green and red clothes. Green and red clothes.',
	short_description: 'Green and blue clothes.',
	attributes: [
		{
			name: 'Color',
			variation: true,
			options: [ 'Blue', 'Green' ],
		},
	],
	default_attributes: [
		{
			name: 'Color',
			option: 'Blue',
		},
	],
	variations: [
		{
			attributes: [
				{
					name: 'Color',
					option: 'Blue',
				},
			],
			regular_price: '0.01',
			stock_status: 'instock',
			manage_stock: false,
		},
		{
			attributes: [
				{
					name: 'Color',
					option: 'Green',
				},
			],
			regular_price: '0.01',
			stock_status: 'instock',
			manage_stock: false,
		},
	],
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/hoodie-blue-1.jpg',
		},
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/hoodie-green-1.jpg',
		},
	],
};

const mollieSubscription100 = {
	name: 'Mollie Subscription Test Product 100',
	slug: 'mollie-product-subscription-100',
	type: 'subscription',
	regular_price: '0.01',
	description:
		'Subscribe to our magazine for expert advice, tips, and insights on growing the best clothes year-round.',
	short_description: 'Monthly magazine on apple growing and orchard tips.',
	meta_data: [
		{ key: '_subscription_price', value: '0.01' },
		{ key: '_subscription_period', value: 'month' },
		{ key: '_subscription_period_interval', value: '1' },
		{ key: '_subscription_length', value: '0' },
	],
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/album-1.jpg',
		},
	],
};

// Extra products for manual live-mode tests (8.2.0 Express): prices kept minimal, payments are real
const mollieSimple002: WooCommerce.CreateProduct = {
	...mollieSimple100,
	name: 'Mollie Simple Test Product 0.02',
	slug: 'mollie-product-simple-002',
	regular_price: '0.02',
};

// Untyped like mollieSubscription100: CreateProduct has no stock fields, the REST API accepts them
const mollieSimpleStock2 = {
	...mollieSimple100,
	name: 'Mollie Simple Stock 2 Test Product',
	slug: 'mollie-product-simple-stock-2',
	manage_stock: true,
	stock_quantity: 2,
};

// Tax and percent coupons round to zero on cent prices
const mollieSimpleTax: WooCommerce.CreateProduct = {
	...mollieSimple100,
	name: 'Mollie Simple Tax Test Product 1.00',
	slug: 'mollie-product-simple-tax',
	regular_price: '1.00',
};

const mollieVirtualNotDownloadable: WooCommerce.CreateProduct = {
	...mollieSimple100,
	name: 'Mollie Virtual Not Downloadable Test Product',
	slug: 'mollie-product-virtual-not-downloadable',
	virtual: true,
	downloadable: false,
};

export const products: {
	[ key: string ]: WooCommerce.CreateProduct;
} = {
	mollieSimple100,
	mollieSimleVoucherMeal100,
	mollieSimleVoucherEco100,
	mollieSimleVoucherGift100,
	mollieVirtual100,
	mollieVariable100,
	mollieSubscription100,
	mollieSimple002,
	mollieSimpleStock2,
	mollieSimpleTax,
	mollieVirtualNotDownloadable,
};
