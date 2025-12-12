/**
 * Internal dependencies
 */
import { cards, MollieTestData } from '../../../../resources';

export const payForOrderEur: MollieTestData.Transaction[] = [
	// {
	// 	testId: '',
	// 	gatewaySlug: 'applepay',
	// 	paymentStatus: 'paid',
	// 	orderStatus: 'processing',
	// },
	{
		testId: 'C4237611',
		gatewaySlug: 'mbway',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237612',
		gatewaySlug: 'mbway',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237613',
		gatewaySlug: 'mbway',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237614',
		gatewaySlug: 'mbway',
		paymentStatus: 'expired',
	},
	{
		testId: 'C4237607',
		gatewaySlug: 'multibanco',
		paymentStatus: 'paid',
	},
	{
		testId: 'C4237608',
		gatewaySlug: 'multibanco',
		paymentStatus: 'failed',
	},
	{
		testId: 'C4237609',
		gatewaySlug: 'multibanco',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C4237610',
		gatewaySlug: 'multibanco',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420334',
		gatewaySlug: 'in3',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420335',
		gatewaySlug: 'in3',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420336',
		gatewaySlug: 'in3',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420337',
		gatewaySlug: 'in3',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420345',
		gatewaySlug: 'bancontact',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420346',
		gatewaySlug: 'bancontact',
		paymentStatus: 'open',
	},
	{
		testId: 'C420347',
		gatewaySlug: 'bancontact',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420348',
		gatewaySlug: 'bancontact',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420349',
		gatewaySlug: 'bancontact',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420350',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420351',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420352',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420353',
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
		testId: 'C420359',
		gatewaySlug: 'ideal',
		paymentStatus: 'paid',
		bankIssuer: 'ING',
	},
	{
		testId: 'C420360',
		gatewaySlug: 'ideal',
		paymentStatus: 'open',
		bankIssuer: 'ING',
	},
	{
		testId: 'C420361',
		gatewaySlug: 'ideal',
		paymentStatus: 'failed',
		bankIssuer: 'ING',
	},
	{
		testId: 'C420363',
		gatewaySlug: 'ideal',
		paymentStatus: 'canceled',
		bankIssuer: 'ING',
	},
	{
		testId: 'C420362',
		gatewaySlug: 'ideal',
		paymentStatus: 'expired',
		bankIssuer: 'ING',
	},
	{
		testId: 'C420368',
		gatewaySlug: 'paypal',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420369',
		gatewaySlug: 'paypal',
		paymentStatus: 'pending',
	},
	{
		testId: 'C420370',
		gatewaySlug: 'paypal',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420371',
		gatewaySlug: 'paypal',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420372',
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
		testId: 'C420375',
		gatewaySlug: 'eps',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420376',
		gatewaySlug: 'eps',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420377',
		gatewaySlug: 'eps',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420378',
		gatewaySlug: 'eps',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420379',
		gatewaySlug: 'kbc',
		paymentStatus: 'paid',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C420380',
		gatewaySlug: 'kbc',
		paymentStatus: 'failed',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C420381',
		gatewaySlug: 'kbc',
		paymentStatus: 'canceled',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C420382',
		gatewaySlug: 'kbc',
		paymentStatus: 'expired',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C420391',
		gatewaySlug: 'creditcard',
		paymentStatus: 'expired',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C420390',
		gatewaySlug: 'creditcard',
		paymentStatus: 'paid',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C420388',
		gatewaySlug: 'creditcard',
		paymentStatus: 'failed',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C420389',
		gatewaySlug: 'creditcard',
		paymentStatus: 'open',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C420399',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'paid',
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
	},
	{
		testId: 'C420402',
		gatewaySlug: 'mybank',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420403',
		gatewaySlug: 'mybank',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420404',
		gatewaySlug: 'mybank',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420409',
		gatewaySlug: 'belfius',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420410',
		gatewaySlug: 'belfius',
		paymentStatus: 'failed',
	},
	{
		testId: 'C420411',
		gatewaySlug: 'belfius',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420412',
		gatewaySlug: 'belfius',
		paymentStatus: 'expired',
	},
	{
		testId: 'C420413',
		gatewaySlug: 'billie',
		paymentStatus: 'authorized',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420414',
		gatewaySlug: 'billie',
		paymentStatus: 'failed',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420415',
		gatewaySlug: 'billie',
		paymentStatus: 'canceled',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420416',
		gatewaySlug: 'billie',
		paymentStatus: 'expired',
		billingCompany: 'Syde',
	},
	{
		testId: 'C420417',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'paid',
	},
	{
		testId: 'C420418',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C420419',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3007283',
		gatewaySlug: 'klarna',
		paymentStatus: 'authorized',
	},
	{
		testId: 'C3007284',
		gatewaySlug: 'klarna',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007285',
		gatewaySlug: 'klarna',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007286',
		gatewaySlug: 'klarna',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3007291',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3007292',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3007293',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3007294',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3241647',
		gatewaySlug: 'alma',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3241648',
		gatewaySlug: 'alma',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3241649',
		gatewaySlug: 'alma',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3241650',
		gatewaySlug: 'alma',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3437850',
		gatewaySlug: 'trustly',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3437851',
		gatewaySlug: 'trustly',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3437852',
		gatewaySlug: 'trustly',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3437853',
		gatewaySlug: 'trustly',
		paymentStatus: 'expired',
	},
	{
		testId: 'C3622421',
		gatewaySlug: 'riverty',
		paymentStatus: 'authorized',
	},
	{
		testId: 'C3622422',
		gatewaySlug: 'riverty',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3622423',
		gatewaySlug: 'riverty',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3622424',
		gatewaySlug: 'riverty',
		paymentStatus: 'expired',
	},
	// Payconiq unset by client on 04/12/2025
	// {
	// 	testId: 'C3622433',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'paid',
	// },
	// {
	// 	testId: 'C3622434',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'failed',
	// },
	// {
	// 	testId: 'C3622435',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'canceled',
	// },
	// {
	// 	testId: 'C3622436',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'expired',
	// },
	{
		testId: 'C3757243',
		gatewaySlug: 'satispay',
		paymentStatus: 'paid',
	},
	{
		testId: 'C3757244',
		gatewaySlug: 'satispay',
		paymentStatus: 'failed',
	},
	{
		testId: 'C3757245',
		gatewaySlug: 'satispay',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C3757246',
		gatewaySlug: 'satispay',
		paymentStatus: 'expired',
	},
];
