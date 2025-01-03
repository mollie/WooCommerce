/**
 * Internal dependencies
 */
import { cards, MollieTestData } from '../../../../resources';

export const classicCheckoutEur: MollieTestData.PaymentStatus[] = [
	// {
	// 	testId: '',
	// 	gatewaySlug: 'applepay',
	// 	paymentStatus: 'paid',
	// 	orderStatus: 'processing',
	// },
	{
		testId: 'C3731',
		gatewaySlug: 'in3',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3732',
		gatewaySlug: 'in3',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3733',
		gatewaySlug: 'in3',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3734',
		gatewaySlug: 'in3',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3387',
		gatewaySlug: 'bancontact',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3388',
		gatewaySlug: 'bancontact',
		paymentStatus: 'open',
		orderStatus: 'pending',
	},
	{
		testId: 'C3389',
		gatewaySlug: 'bancontact',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3390',
		gatewaySlug: 'bancontact',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3391',
		gatewaySlug: 'bancontact',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3424',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3425',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3426',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3427',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	// {
	// 	testId: '',
	// 	gatewaySlug: 'voucher',
	// 	paymentStatus: 'expired',
	// 	orderStatus: 'pending',
	// },
	{
		testId: 'C3382',
		gatewaySlug: 'ideal',
		paymentStatus: 'paid',
		bankIssuer: 'ING',
		orderStatus: 'processing',
	},
	{
		testId: 'C3383',
		gatewaySlug: 'ideal',
		paymentStatus: 'open',
		bankIssuer: 'ING',
		orderStatus: 'pending',
	},
	{
		testId: 'C3384',
		gatewaySlug: 'ideal',
		paymentStatus: 'failed',
		bankIssuer: 'ING',
		orderStatus: 'pending',
	},
	{
		testId: 'C3385',
		gatewaySlug: 'ideal',
		paymentStatus: 'expired',
		bankIssuer: 'ING',
		orderStatus: 'pending',
	},
	{
		testId: 'C3386',
		gatewaySlug: 'ideal',
		paymentStatus: 'canceled',
		bankIssuer: 'ING',
		orderStatus: 'pending',
	},
	{
		testId: 'C3392',
		gatewaySlug: 'paypal',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3393',
		gatewaySlug: 'paypal',
		paymentStatus: 'pending',
		orderStatus: 'pending',
	},
	{
		testId: 'C3394',
		gatewaySlug: 'paypal',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3395',
		gatewaySlug: 'paypal',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3396',
		gatewaySlug: 'paypal',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	// {
	// 	testId: '',
	// 	gatewaySlug: 'giftcard',
	// 	paymentStatus: 'expired',
	// 	orderStatus: 'pending',
	// },
	{
		testId: 'C3412',
		gatewaySlug: 'eps',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3413',
		gatewaySlug: 'eps',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3414',
		gatewaySlug: 'eps',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3415',
		gatewaySlug: 'eps',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3419',
		gatewaySlug: 'kbc',
		paymentStatus: 'paid',
		bankIssuer: 'KBC',
		orderStatus: 'processing',
	},
	{
		testId: 'C3416',
		gatewaySlug: 'kbc',
		paymentStatus: 'failed',
		bankIssuer: 'KBC',
		orderStatus: 'pending',
	},
	{
		testId: 'C3417',
		gatewaySlug: 'kbc',
		paymentStatus: 'canceled',
		bankIssuer: 'KBC',
		orderStatus: 'pending',
	},
	{
		testId: 'C3418',
		gatewaySlug: 'kbc',
		paymentStatus: 'expired',
		bankIssuer: 'KBC',
		orderStatus: 'pending',
	},
	{
		testId: 'C3371',
		gatewaySlug: 'creditcard',
		paymentStatus: 'paid',
		card: cards.visa,
		orderStatus: 'processing',
	},
	{
		testId: 'C3372',
		gatewaySlug: 'creditcard',
		paymentStatus: 'open',
		card: cards.visa,
		orderStatus: 'pending',
	},
	{
		testId: 'C3373',
		gatewaySlug: 'creditcard',
		paymentStatus: 'failed',
		card: cards.visa,
		orderStatus: 'pending',
	},	{
		testId: 'C3374',
		gatewaySlug: 'creditcard',
		paymentStatus: 'expired',
		card: cards.visa,
		orderStatus: 'pending',
	},
	{
		testId: 'C3433',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'paid',
		orderStatus: 'processing',
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
		orderStatus: 'processing',
	},
	{
		testId: 'C420295',
		gatewaySlug: 'mybank',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C420296',
		gatewaySlug: 'mybank',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C420297',
		gatewaySlug: 'mybank',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3428',
		gatewaySlug: 'belfius',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3429',
		gatewaySlug: 'belfius',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3430',
		gatewaySlug: 'belfius',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3431',
		gatewaySlug: 'belfius',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C354674',
		gatewaySlug: 'billie',
		paymentStatus: 'authorized',
		orderStatus: 'processing',
		billingCompany: 'Syde',
	},
	{
		testId: 'C354675',
		gatewaySlug: 'billie',
		paymentStatus: 'failed',
		orderStatus: 'pending',
		billingCompany: 'Syde',
	},
	{
		testId: 'C354676',
		gatewaySlug: 'billie',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
		billingCompany: 'Syde',
	},
	{
		testId: 'C354677',
		gatewaySlug: 'billie',
		paymentStatus: 'expired',
		orderStatus: 'pending',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420141',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C420142',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C420143',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007255',
		gatewaySlug: 'klarna',
		paymentStatus: 'authorized',
		orderStatus: 'processing',
	},
	{
		testId: 'C3007256',
		gatewaySlug: 'klarna',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007257',
		gatewaySlug: 'klarna',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007258',
		gatewaySlug: 'klarna',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007267',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3007268',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007269',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007270',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3241639',
		gatewaySlug: 'alma',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3241640',
		gatewaySlug: 'alma',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3241641',
		gatewaySlug: 'alma',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3241642',
		gatewaySlug: 'alma',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3437842',
		gatewaySlug: 'trustly',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3437843',
		gatewaySlug: 'trustly',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3437844',
		gatewaySlug: 'trustly',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3437845',
		gatewaySlug: 'trustly',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622413',
		gatewaySlug: 'riverty',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3622414',
		gatewaySlug: 'riverty',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622415',
		gatewaySlug: 'riverty',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622416',
		gatewaySlug: 'riverty',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622425',
		gatewaySlug: 'payconiq',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3622426',
		gatewaySlug: 'payconiq',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622427',
		gatewaySlug: 'payconiq',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622428',
		gatewaySlug: 'payconiq',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3757251',
		gatewaySlug: 'satispay',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3757252',
		gatewaySlug: 'satispay',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3757253',
		gatewaySlug: 'satispay',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3757254',
		gatewaySlug: 'satispay',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},

	/**
	 * Deprecated gateways
	 */

	// {
	// 	testId: 'C3401',
	// 	gatewaySlug: 'klarnapaylater',
	// 	paymentStatus: 'authorized',
	// 	orderStatus: 'processing',
	// },
	// {
	// 	testId: 'C3402',
	// 	gatewaySlug: 'klarnapaylater',
	// 	paymentStatus: 'failed',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C3403',
	// 	gatewaySlug: 'klarnapaylater',
	// 	paymentStatus: 'canceled',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C3404',
	// 	gatewaySlug: 'klarnapaylater',
	// 	paymentStatus: 'expired',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C3397',
	// 	gatewaySlug: 'klarnapaynow',
	// 	paymentStatus: 'authorized',
	// 	orderStatus: 'processing',
	// },
	// {
	// 	testId: 'C3398',
	// 	gatewaySlug: 'klarnapaynow',
	// 	paymentStatus: 'failed',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C3399',
	// 	gatewaySlug: 'klarnapaynow',
	// 	paymentStatus: 'canceled',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C3400',
	// 	gatewaySlug: 'klarnapaynow',
	// 	paymentStatus: 'expired',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C3408',
	// 	gatewaySlug: 'klarnasliceit',
	// 	paymentStatus: 'authorized',
	// 	orderStatus: 'processing',
	// },
	// {
	// 	testId: 'C3409',
	// 	gatewaySlug: 'klarnasliceit',
	// 	paymentStatus: 'failed',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C3410',
	// 	gatewaySlug: 'klarnasliceit',
	// 	paymentStatus: 'canceled',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C3411',
	// 	gatewaySlug: 'klarnasliceit',
	// 	paymentStatus: 'expired',
	// 	orderStatus: 'pending',
	// },
];
