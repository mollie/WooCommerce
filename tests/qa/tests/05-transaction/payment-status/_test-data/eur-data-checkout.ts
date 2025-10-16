/**
 * Internal dependencies
 */
import { cards, MollieTestData } from '../../../../resources';

export const checkoutEur: MollieTestData.PaymentStatus[] = [
	// {
	// 	testId: '',
	// 	gatewaySlug: 'applepay',
	// 	paymentStatus: 'paid',
	// 	orderStatus: 'processing',
	// },
	{
		testId: 'C4237595',
		gatewaySlug: 'mbway',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237596',
		gatewaySlug: 'mbway',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237597',
		gatewaySlug: 'mbway',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237598',
		gatewaySlug: 'mbway',
		paymentStatus: 'expired',
	},
	{
		testId: 'C4237591',
		gatewaySlug: 'multibanco',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237592',
		gatewaySlug: 'multibanco',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237593',
		gatewaySlug: 'multibanco',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237594',
		gatewaySlug: 'multibanco',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420219',
		gatewaySlug: 'in3',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420220',
		gatewaySlug: 'in3',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420221',
		gatewaySlug: 'in3',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420222',
		gatewaySlug: 'in3',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420230',
		gatewaySlug: 'bancontact',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420231',
		gatewaySlug: 'bancontact',
		paymentStatus: 'open',
	},
	{
		testId: 'C420232',
		gatewaySlug: 'bancontact',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420233',
		gatewaySlug: 'bancontact',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420234',
		gatewaySlug: 'bancontact',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420235',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420236',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420237',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420238',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'expired',
	},
	// {
	// 	testId: '',
	// 	gatewaySlug: 'voucher',
	// 	paymentStatus: 'expired',
	//
	// },
	{
		testId: 'C420244',
		gatewaySlug: 'ideal',
		paymentStatus: 'paid',
		bankIssuer: 'ING',
	},
	{
		testId: 'C420245',
		gatewaySlug: 'ideal',
		paymentStatus: 'open',
		bankIssuer: 'ING',
	},
	{
		testId: 'C420246',
		gatewaySlug: 'ideal',
		paymentStatus: 'failed',
		bankIssuer: 'ING',
	},
	{
		testId: 'C420248',
		gatewaySlug: 'ideal',
		paymentStatus: 'canceled',
		bankIssuer: 'ING',
	},
	{
		testId: 'C420247',
		gatewaySlug: 'ideal',
		paymentStatus: 'expired',
		bankIssuer: 'ING',
	},
	{
		testId: 'C420253',
		gatewaySlug: 'paypal',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420254',
		gatewaySlug: 'paypal',
		paymentStatus: 'pending',
	},
	{
		testId: 'C420255',
		gatewaySlug: 'paypal',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420256',
		gatewaySlug: 'paypal',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420257',
		gatewaySlug: 'paypal',
		paymentStatus: 'expired',
	},
	// {
	// 	testId: '',
	// 	gatewaySlug: 'giftcard',
	// 	paymentStatus: 'expired',
	//
	// },
	{
		testId: 'C420260',
		gatewaySlug: 'eps',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420261',
		gatewaySlug: 'eps',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420262',
		gatewaySlug: 'eps',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420263',
		gatewaySlug: 'eps',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420267',
		gatewaySlug: 'kbc',
		paymentStatus: 'paid',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C420264',
		gatewaySlug: 'kbc',
		paymentStatus: 'failed',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C420265',
		gatewaySlug: 'kbc',
		paymentStatus: 'canceled',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C420266',
		gatewaySlug: 'kbc',
		paymentStatus: 'expired',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C420274',
		gatewaySlug: 'creditcard',
		paymentStatus: 'open',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C420273',
		gatewaySlug: 'creditcard',
		paymentStatus: 'paid',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C420275',
		gatewaySlug: 'creditcard',
		paymentStatus: 'failed',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C420276',
		gatewaySlug: 'creditcard',
		paymentStatus: 'expired',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C420284',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420283',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'open',
		orderStatus: 'on-hold',
	},
	{
		testId: 'C420285',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'expired',
		orderStatus: 'on-hold',
	},
	{
		testId: 'C420286',
		gatewaySlug: 'mybank',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420287',
		gatewaySlug: 'mybank',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420288',
		gatewaySlug: 'mybank',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420289',
		gatewaySlug: 'mybank',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420298',
		gatewaySlug: 'belfius',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420299',
		gatewaySlug: 'belfius',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420300',
		gatewaySlug: 'belfius',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420301',
		gatewaySlug: 'belfius',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420302',
		gatewaySlug: 'billie',
		paymentStatus: 'authorized',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420303',
		gatewaySlug: 'billie',
		paymentStatus: 'failed',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420304',
		gatewaySlug: 'billie',
		paymentStatus: 'canceled',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420305',
		gatewaySlug: 'billie',
		paymentStatus: 'expired',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420306',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420307',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420308',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3007259',
		gatewaySlug: 'klarna',
		paymentStatus: 'authorized',
	},
	{
		testId: 'C3007260',
		gatewaySlug: 'klarna',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007261',
		gatewaySlug: 'klarna',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007262',
		gatewaySlug: 'klarna',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3007263',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3007264',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007265',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007266',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3241643',
		gatewaySlug: 'alma',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3241644',
		gatewaySlug: 'alma',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3241645',
		gatewaySlug: 'alma',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3241646',
		gatewaySlug: 'alma',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3437846',
		gatewaySlug: 'trustly',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3437847',
		gatewaySlug: 'trustly',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3437848',
		gatewaySlug: 'trustly',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3437849',
		gatewaySlug: 'trustly',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3622417',
		gatewaySlug: 'riverty',
		paymentStatus: 'authorized',
	},
	{
		testId: 'C3622418',
		gatewaySlug: 'riverty',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3622419',
		gatewaySlug: 'riverty',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3622420',
		gatewaySlug: 'riverty',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3622429',
		gatewaySlug: 'payconiq',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3622430',
		gatewaySlug: 'payconiq',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3622431',
		gatewaySlug: 'payconiq',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3622432',
		gatewaySlug: 'payconiq',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3757247',
		gatewaySlug: 'satispay',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3757248',
		gatewaySlug: 'satispay',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3757249',
		gatewaySlug: 'satispay',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3757250',
		gatewaySlug: 'satispay',
		paymentStatus: 'expired',
	},
];
