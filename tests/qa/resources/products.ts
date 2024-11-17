const mollieSimple100: WooCommerce.CreateProduct = {
	name: 'Mollie Simple Test Product 100',
	slug: 'mollie-product-simple-100',
	type: 'simple',
	regular_price: '100.00',
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
	meta_data: [
		{ key: '_mollie_voucher_category', value: 'no_category'}
	]
};

const mollieSimleVoucherMeal100: WooCommerce.CreateProduct = {
	...mollieSimple100,
	name: 'Mollie Simple Voucher Meal Test Product 100',
	slug: 'mollie-product-simple-voucher-meal-100',
	meta_data: [
		{ key: '_mollie_voucher_category', value: 'meal'}
	]
};

const mollieSimleVoucherEco100: WooCommerce.CreateProduct = {
	...mollieSimple100,
	name: 'Mollie Simple Voucher Eco Test Product 100',
	slug: 'mollie-product-simple-voucher-eco-100',
	meta_data: [
		{ key: '_mollie_voucher_category', value: 'eco'}
	]
};

const mollieSimleVoucherGift100: WooCommerce.CreateProduct = {
	...mollieSimple100,
	name: 'Mollie Simple Voucher Gift Test Product 100',
	slug: 'mollie-product-simple-voucher-gift-100',
	meta_data: [
		{ key: '_mollie_voucher_category', value: 'gift'}
	]
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
	regular_price: '100.00',
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
					id: '0',
					option: 'Blue',
				},
				{
					id: '0',
					option: 'Green',
				},
			],
			regular_price: '100.00',
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

export const products: {
	[ key: string ]: WooCommerce.CreateProduct;
} = {
	mollieSimple100,
	mollieSimleVoucherMeal100,
	mollieSimleVoucherEco100,
	mollieSimleVoucherGift100,
	mollieVirtual100,
	mollieVariable100,
};
