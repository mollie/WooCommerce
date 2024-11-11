/**
 * Internal dependencies
 */
import { cards, MollieTestData } from '../../../resources';

export const paymentStatusPayForOrderEur: MollieTestData.PaymentStatus[] = [
	// {
	// 	testId: '',
	// 	gatewaySlug: 'applepay',
	// 	paymentStatus: 'paid',
	// 	orderStatus: 'processing',
	// },
	{
		testId: 'C420334',
		gatewaySlug: 'in3',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C420335',
		gatewaySlug: 'in3',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C420336',
		gatewaySlug: 'in3',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C420337',
		gatewaySlug: 'in3',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C420345',
		gatewaySlug: 'bancontact',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C420346',
		gatewaySlug: 'bancontact',
		paymentStatus: 'open',
		orderStatus: 'pending',
	},
	{
		testId: 'C420347',
		gatewaySlug: 'bancontact',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C420348',
		gatewaySlug: 'bancontact',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C420349',
		gatewaySlug: 'bancontact',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C420350',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C420351',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C420352',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C420353',
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
		testId: 'C420359',
		gatewaySlug: 'ideal',
		paymentStatus: 'paid',
		bankIssuer: 'ING',
		orderStatus: 'processing',
	},
	{
		testId: 'C420360',
		gatewaySlug: 'ideal',
		paymentStatus: 'open',
		bankIssuer: 'ING',
		orderStatus: 'pending',
	},
	{
		testId: 'C420361',
		gatewaySlug: 'ideal',
		paymentStatus: 'failed',
		bankIssuer: 'ING',
		orderStatus: 'pending',
	},
	{
		testId: 'C420363',
		gatewaySlug: 'ideal',
		paymentStatus: 'canceled',
		bankIssuer: 'ING',
		orderStatus: 'pending',
	},
	{
		testId: 'C420362',
		gatewaySlug: 'ideal',
		paymentStatus: 'expired',
		bankIssuer: 'ING',
		orderStatus: 'pending',
	},
	{
		testId: 'C420368',
		gatewaySlug: 'paypal',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C420369',
		gatewaySlug: 'paypal',
		paymentStatus: 'pending',
		orderStatus: 'pending',
	},
	{
		testId: 'C420370',
		gatewaySlug: 'paypal',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C420371',
		gatewaySlug: 'paypal',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C420372',
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
		testId: 'C420375',
		gatewaySlug: 'eps',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C420376',
		gatewaySlug: 'eps',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C420377',
		gatewaySlug: 'eps',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C420378',
		gatewaySlug: 'eps',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C420379',
		gatewaySlug: 'kbc',
		paymentStatus: 'paid',
		bankIssuer: 'KBC',
		orderStatus: 'processing',
	},
	{
		testId: 'C420380',
		gatewaySlug: 'kbc',
		paymentStatus: 'failed',
		bankIssuer: 'KBC',
		orderStatus: 'pending',
	},
	{
		testId: 'C420381',
		gatewaySlug: 'kbc',
		paymentStatus: 'canceled',
		bankIssuer: 'KBC',
		orderStatus: 'pending',
	},
	{
		testId: 'C420382',
		gatewaySlug: 'kbc',
		paymentStatus: 'expired',
		bankIssuer: 'KBC',
		orderStatus: 'pending',
	},
	{
		testId: 'C420383',
		gatewaySlug: 'creditcard',
		paymentStatus: 'paid',
		card: cards.visa,
		orderStatus: 'processing',
	},
	{
		testId: 'C420384',
		gatewaySlug: 'creditcard',
		paymentStatus: 'open',
		card: cards.visa,
		orderStatus: 'pending',
	},
	{
		testId: 'C420385',
		gatewaySlug: 'creditcard',
		paymentStatus: 'failed',
		card: cards.visa,
		orderStatus: 'pending',
	},
	// {
	// 	testId: 'C420387',
	// 	gatewaySlug: 'creditcard',
	// 	paymentStatus: 'canceled',
	// 	card: cards.visa,
	// 	orderStatus: 'pending',
	// },
	{
		testId: 'C420386',
		gatewaySlug: 'creditcard',
		paymentStatus: 'expired',
		card: cards.visa,
		orderStatus: 'pending',
	},
	{
		testId: 'C420399',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C420398',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'open',
		orderStatus: 'on-hold',
	},
	{
		testId: 'C420400',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'expired',
		orderStatus: 'on-hold',
	},
	{
		testId: 'C420401',
		gatewaySlug: 'mybank',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C420402',
		gatewaySlug: 'mybank',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C420403',
		gatewaySlug: 'mybank',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C420404',
		gatewaySlug: 'mybank',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C420409',
		gatewaySlug: 'belfius',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C420410',
		gatewaySlug: 'belfius',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C420411',
		gatewaySlug: 'belfius',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C420412',
		gatewaySlug: 'belfius',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C420413',
		gatewaySlug: 'billie',
		paymentStatus: 'authorized',
		orderStatus: 'processing',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420414',
		gatewaySlug: 'billie',
		paymentStatus: 'failed',
		orderStatus: 'pending',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420415',
		gatewaySlug: 'billie',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420416',
		gatewaySlug: 'billie',
		paymentStatus: 'expired',
		orderStatus: 'pending',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420417',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C420418',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C420419',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007283',
		gatewaySlug: 'klarna',
		paymentStatus: 'authorized',
		orderStatus: 'processing',
	},
	{
		testId: 'C3007284',
		gatewaySlug: 'klarna',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007285',
		gatewaySlug: 'klarna',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007286',
		gatewaySlug: 'klarna',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007291',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3007292',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007293',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3007294',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3241647',
		gatewaySlug: 'alma',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3241648',
		gatewaySlug: 'alma',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3241649',
		gatewaySlug: 'alma',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3241650',
		gatewaySlug: 'alma',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3437850',
		gatewaySlug: 'trustly',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3437851',
		gatewaySlug: 'trustly',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3437852',
		gatewaySlug: 'trustly',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3437853',
		gatewaySlug: 'trustly',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622421',
		gatewaySlug: 'riverty',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3622422',
		gatewaySlug: 'riverty',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622423',
		gatewaySlug: 'riverty',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622424',
		gatewaySlug: 'riverty',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622433',
		gatewaySlug: 'payconiq',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3622434',
		gatewaySlug: 'payconiq',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622435',
		gatewaySlug: 'payconiq',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3622436',
		gatewaySlug: 'payconiq',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},
	{
		testId: 'C3757243',
		gatewaySlug: 'satispay',
		paymentStatus: 'paid',
		orderStatus: 'processing',
	},
	{
		testId: 'C3757244',
		gatewaySlug: 'satispay',
		paymentStatus: 'failed',
		orderStatus: 'pending',
	},
	{
		testId: 'C3757245',
		gatewaySlug: 'satispay',
		paymentStatus: 'canceled',
		orderStatus: 'pending',
	},
	{
		testId: 'C3757246',
		gatewaySlug: 'satispay',
		paymentStatus: 'expired',
		orderStatus: 'pending',
	},

	/**
	 * Deprecated gateways
	 */

	// {
	// 	testId: 'C420364',
	// 	gatewaySlug: 'klarnapaylater',
	// 	paymentStatus: 'authorized',
	// 	orderStatus: 'processing',
	// },
	// {
	// 	testId: 'C420365',
	// 	gatewaySlug: 'klarnapaylater',
	// 	paymentStatus: 'failed',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C420366',
	// 	gatewaySlug: 'klarnapaylater',
	// 	paymentStatus: 'canceled',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C420367',
	// 	gatewaySlug: 'klarnapaylater',
	// 	paymentStatus: 'expired',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C420394',
	// 	gatewaySlug: 'klarnapaynow',
	// 	paymentStatus: 'authorized',
	// 	orderStatus: 'processing',
	// },
	// {
	// 	testId: 'C420395',
	// 	gatewaySlug: 'klarnapaynow',
	// 	paymentStatus: 'failed',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C420396',
	// 	gatewaySlug: 'klarnapaynow',
	// 	paymentStatus: 'canceled',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C420397',
	// 	gatewaySlug: 'klarnapaynow',
	// 	paymentStatus: 'expired',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C420338',
	// 	gatewaySlug: 'klarnasliceit',
	// 	paymentStatus: 'authorized',
	// 	orderStatus: 'processing',
	// },
	// {
	// 	testId: 'C420339',
	// 	gatewaySlug: 'klarnasliceit',
	// 	paymentStatus: 'failed',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C420340',
	// 	gatewaySlug: 'klarnasliceit',
	// 	paymentStatus: 'canceled',
	// 	orderStatus: 'pending',
	// },
	// {
	// 	testId: 'C420341',
	// 	gatewaySlug: 'klarnasliceit',
	// 	paymentStatus: 'expired',
	// 	orderStatus: 'pending',
	// },
];