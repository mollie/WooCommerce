export const request = ( countryCode, currencyCode, totalLabel, subtotal ) => {
	return {
		countryCode,
		currencyCode,
		supportedNetworks: [ 'amex', 'maestro', 'masterCard', 'visa', 'vPay' ],
		merchantCapabilities: [ 'supports3DS' ],
		shippingType: 'shipping',
		requiredBillingContactFields: [ 'postalAddress', 'email' ],
		requiredShippingContactFields: [ 'postalAddress', 'email' ],
		total: {
			label: totalLabel,
			amount: subtotal,
			type: 'final',
		},
	};
};
