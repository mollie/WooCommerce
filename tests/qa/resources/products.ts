const mollieSurcharge10: WooCommerce.CreateProduct = {
	name: 'Mollie Surcharge Test Product 10',
	slug: 'mollie-product-surcharge-10',
	type: 'simple',
	regular_price: '10.00',
	description: 'Mollie surcharge product, mollie surcharge product.',
	short_description: 'Libre prodotto.',
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/cap-2.jpg',
		},
	],
	sale_price: '10.00',
	virtual: false,
	downloadable: false,
};

const mollieSimple10: WooCommerce.CreateProduct = {
	name: 'Mollie Simple Test Product 10',
	slug: 'mollie-product-simple-10',
	type: 'simple',
	regular_price: '10.00',
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
};

const mollieSimple50: WooCommerce.CreateProduct = {
	name: 'Mollie Simple Test Product 50',
	slug: 'mollie-product-simple-50',
	type: 'simple',
	regular_price: '50.00',
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
};

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
};

const mollieSimple18: WooCommerce.CreateProduct = {
	name: 'Mollie Simple Test Product 18',
	slug: 'mollie-product-simple-18',
	type: 'simple',
	regular_price: '20.00',
	description:
		'Our clothes are juicy, sweet, and ideal for a healthy snack or enhancing your favorite recipes.',
	short_description: 'Fresh, crisp clothes perfect for snacks and desserts.',
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/beanie-2.jpg',
		},
	],
	sale_price: '18.00',
	virtual: false,
	downloadable: false,
};

const mollieVirtual10: WooCommerce.CreateProduct = {
	name: 'Mollie Virtual Test Product 10',
	slug: 'mollie-product-virtual-10',
	type: 'simple',
	regular_price: '10.00',
	description:
		'Our clothes are juicy, sweet, and ideal for a healthy snack or enhancing your favorite recipes.',
	short_description: 'Fresh, crisp clothes perfect for snacks and desserts.',
	images: [
		{
			src: 'https://woocommercecore.mystagingwebsite.com/wp-content/uploads/2017/12/sunglasses-2.jpg',
		},
	],
	virtual: true,
	downloadable: true,
};

const mollieVariable10: WooCommerce.CreateProduct = {
	name: 'Mollie Variable Test Product 10',
	slug: 'mollie-product-variable-10',
	type: 'variable',
	regular_price: '10.00',
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
			regular_price: '10.00',
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
	mollieSurcharge10,
	mollieSimple10,
	mollieSimple50,
	mollieSimple100,
	mollieSimple18,
	mollieVirtual10,
	mollieVariable10,
};
