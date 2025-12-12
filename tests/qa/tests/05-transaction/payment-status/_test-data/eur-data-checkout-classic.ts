/**
 * Internal dependencies
 */
import { cards, MollieTestData } from '../../../../resources';

export const classicCheckoutEur: MollieTestData.Transaction[] = [
	// {
	// 	testId: '',
	// 	gatewaySlug: 'applepay',
	// 	paymentStatus: 'paid',
	// 	orderStatus: 'processing',
	// },
	{
		testId: 'C4237579',
		gatewaySlug: 'mbway',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237580',
		gatewaySlug: 'mbway',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237581',
		gatewaySlug: 'mbway',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237582',
		gatewaySlug: 'mbway',
		paymentStatus: 'expired',
	},
	{
		testId: 'C4237575',
		gatewaySlug: 'multibanco',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237576',
		gatewaySlug: 'multibanco',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237577',
		gatewaySlug: 'multibanco',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237578',
		gatewaySlug: 'multibanco',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3731',
		gatewaySlug: 'in3',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3732',
		gatewaySlug: 'in3',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3733',
		gatewaySlug: 'in3',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3734',
		gatewaySlug: 'in3',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3387',
		gatewaySlug: 'bancontact',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3388',
		gatewaySlug: 'bancontact',
		paymentStatus: 'open',
	},
	{
		testId: 'C3389',
		gatewaySlug: 'bancontact',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3390',
		gatewaySlug: 'bancontact',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3391',
		gatewaySlug: 'bancontact',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3424',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3425',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3426',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3427',
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
		testId: 'C3382',
		gatewaySlug: 'ideal',
		paymentStatus: 'paid',
		bankIssuer: 'ING',
	},
	{
		testId: 'C3383',
		gatewaySlug: 'ideal',
		paymentStatus: 'open',
		bankIssuer: 'ING',
	},
	{
		testId: 'C3384',
		gatewaySlug: 'ideal',
		paymentStatus: 'failed',
		bankIssuer: 'ING',
	},
	{
		testId: 'C3385',
		gatewaySlug: 'ideal',
		paymentStatus: 'expired',
		bankIssuer: 'ING',
	},
	{
		testId: 'C3386',
		gatewaySlug: 'ideal',
		paymentStatus: 'canceled',
		bankIssuer: 'ING',
	},
	{
		testId: 'C3392',
		gatewaySlug: 'paypal',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3393',
		gatewaySlug: 'paypal',
		paymentStatus: 'pending',
	},
	{
		testId: 'C3394',
		gatewaySlug: 'paypal',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3395',
		gatewaySlug: 'paypal',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3396',
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
		testId: 'C3412',
		gatewaySlug: 'eps',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3413',
		gatewaySlug: 'eps',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3414',
		gatewaySlug: 'eps',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3415',
		gatewaySlug: 'eps',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3419',
		gatewaySlug: 'kbc',
		paymentStatus: 'paid',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C3416',
		gatewaySlug: 'kbc',
		paymentStatus: 'failed',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C3417',
		gatewaySlug: 'kbc',
		paymentStatus: 'canceled',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C3418',
		gatewaySlug: 'kbc',
		paymentStatus: 'expired',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C3376',
		gatewaySlug: 'creditcard',
		paymentStatus: 'paid',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C3377',
		gatewaySlug: 'creditcard',
		paymentStatus: 'open',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C3379',
		gatewaySlug: 'creditcard',
		paymentStatus: 'expired',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C3378',
		gatewaySlug: 'creditcard',
		paymentStatus: 'failed',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C3433',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3432',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'open',
		orderStatus: 'on-hold',
	},
	{
		testId: 'C3434',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'expired',
		orderStatus: 'on-hold',
	},
	{
		testId: 'C420294',
		gatewaySlug: 'mybank',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420295',
		gatewaySlug: 'mybank',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420296',
		gatewaySlug: 'mybank',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420297',
		gatewaySlug: 'mybank',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3428',
		gatewaySlug: 'belfius',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3429',
		gatewaySlug: 'belfius',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3430',
		gatewaySlug: 'belfius',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3431',
		gatewaySlug: 'belfius',
		paymentStatus: 'expired',
	},
	{
		testId: 'C354674',
		gatewaySlug: 'billie',
		paymentStatus: 'authorized',
		billingCompany: 'Syde',
	},
	{
		testId: 'C354675',
		gatewaySlug: 'billie',
		paymentStatus: 'failed',
		billingCompany: 'Syde',
	},
	{
		testId: 'C354676',
		gatewaySlug: 'billie',
		paymentStatus: 'canceled',
		billingCompany: 'Syde',
	},
	{
		testId: 'C354677',
		gatewaySlug: 'billie',
		paymentStatus: 'expired',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420141',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420142',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420143',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3007255',
		gatewaySlug: 'klarna',
		paymentStatus: 'authorized',
	},
	{
		testId: 'C3007256',
		gatewaySlug: 'klarna',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007257',
		gatewaySlug: 'klarna',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007258',
		gatewaySlug: 'klarna',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3007267',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3007268',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007269',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007270',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3241639',
		gatewaySlug: 'alma',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3241640',
		gatewaySlug: 'alma',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3241641',
		gatewaySlug: 'alma',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3241642',
		gatewaySlug: 'alma',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3437842',
		gatewaySlug: 'trustly',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3437843',
		gatewaySlug: 'trustly',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3437844',
		gatewaySlug: 'trustly',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3437845',
		gatewaySlug: 'trustly',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3622413',
		gatewaySlug: 'riverty',
		paymentStatus: 'authorized',
	},
	{
		testId: 'C3622414',
		gatewaySlug: 'riverty',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3622415',
		gatewaySlug: 'riverty',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3622416',
		gatewaySlug: 'riverty',
		paymentStatus: 'expired',
	},
	// Payconiq unset by client on 04/12/2025
	// {
	// 	testId: 'C3622425',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'paid',
	// },
	// {
	// 	testId: 'C3622426',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'failed',
	// },
	// {
	// 	testId: 'C3622427',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'canceled',
	// },
	// {
	// 	testId: 'C3622428',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'expired',
	// },
	{
		testId: 'C3757251',
		gatewaySlug: 'satispay',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3757252',
		gatewaySlug: 'satispay',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3757253',
		gatewaySlug: 'satispay',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3757254',
		gatewaySlug: 'satispay',
		paymentStatus: 'expired',
	},
];
