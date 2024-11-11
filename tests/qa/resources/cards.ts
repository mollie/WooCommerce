export const cardTypes = [ 'Visa' ];

const visa: WooCommerce.CreditCard = {
	card_holder: 'Carl Holdersson',
	card_number: '4543474002249996',
	expiration_date: '12/2030',
	card_cvv: '123',
};

export const cards: {
	[ key: string ]: WooCommerce.CreditCard;
} = {
	visa,
};
