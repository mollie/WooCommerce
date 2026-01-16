/**
 * Internal dependencies
 */
import { MollieTestData, gateways, cards } from '../../../../resources';
import { baseOrder } from './transaction-base-order.data';

export const payForOrderEur: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C4237611',
		payment: {
			gateway: gateways.mbway,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237612',
		payment: {
			gateway: gateways.mbway,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237613',
		payment: {
			gateway: gateways.mbway,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237614',
		payment: {
			gateway: gateways.mbway,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C4237607',
		payment: {
			gateway: gateways.multibanco,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237608',
		payment: {
			gateway: gateways.multibanco,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237609',
		payment: {
			gateway: gateways.multibanco,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237610',
		payment: {
			gateway: gateways.multibanco,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420334',
		payment: {
			gateway: gateways.in3,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420335',
		payment: {
			gateway: gateways.in3,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420336',
		payment: {
			gateway: gateways.in3,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420337',
		payment: {
			gateway: gateways.in3,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420345',
		payment: {
			gateway: gateways.bancontact,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420346',
		payment: {
			gateway: gateways.bancontact,
			status: 'open',
		},
	},
	{
		...baseOrder,
		testId: 'C420347',
		payment: {
			gateway: gateways.bancontact,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420348',
		payment: {
			gateway: gateways.bancontact,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420349',
		payment: {
			gateway: gateways.bancontact,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420350',
		payment: {
			gateway: gateways.przelewy24,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420351',
		payment: {
			gateway: gateways.przelewy24,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420352',
		payment: {
			gateway: gateways.przelewy24,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420353',
		payment: {
			gateway: gateways.przelewy24,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420359',
		payment: {
			gateway: gateways.ideal,
			status: 'paid',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C420360',
		payment: {
			gateway: gateways.ideal,
			status: 'open',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C420361',
		payment: {
			gateway: gateways.ideal,
			status: 'failed',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C420363',
		payment: {
			gateway: gateways.ideal,
			status: 'canceled',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C420362',
		payment: {
			gateway: gateways.ideal,
			status: 'expired',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C420368',
		payment: {
			gateway: gateways.paypal,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420369',
		payment: {
			gateway: gateways.paypal,
			status: 'pending',
		},
	},
	{
		...baseOrder,
		testId: 'C420370',
		payment: {
			gateway: gateways.paypal,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420371',
		payment: {
			gateway: gateways.paypal,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420372',
		payment: {
			gateway: gateways.paypal,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420375',
		payment: {
			gateway: gateways.eps,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420376',
		payment: {
			gateway: gateways.eps,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420377',
		payment: {
			gateway: gateways.eps,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420378',
		payment: {
			gateway: gateways.eps,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420379',
		payment: {
			gateway: gateways.kbc,
			status: 'paid',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C420380',
		payment: {
			gateway: gateways.kbc,
			status: 'failed',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C420381',
		payment: {
			gateway: gateways.kbc,
			status: 'canceled',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C420382',
		payment: {
			gateway: gateways.kbc,
			status: 'expired',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C420391',
		payment: {
			gateway: gateways.creditcard,
			status: 'expired',
			card: cards.visa,
		},
	},
	{
		...baseOrder,
		testId: 'C420390',
		payment: {
			gateway: gateways.creditcard,
			status: 'paid',
			card: cards.visa,
		},
	},
	{
		...baseOrder,
		testId: 'C420388',
		payment: {
			gateway: gateways.creditcard,
			status: 'failed',
			card: cards.visa,
		},
	},
	{
		...baseOrder,
		testId: 'C420389',
		payment: {
			gateway: gateways.creditcard,
			status: 'open',
			card: cards.visa,
		},
	},
	{
		...baseOrder,
		testId: 'C420399',
		payment: {
			gateway: gateways.banktransfer,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420398',
		payment: {
			gateway: gateways.banktransfer,
			status: 'open',
		},
		orderStatus: 'on-hold',
	},
	{
		...baseOrder,
		testId: 'C420400',
		payment: {
			gateway: gateways.banktransfer,
			status: 'expired',
		},
		orderStatus: 'on-hold',
	},
	{
		...baseOrder,
		testId: 'C420401',
		payment: {
			gateway: gateways.mybank,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420402',
		payment: {
			gateway: gateways.mybank,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420403',
		payment: {
			gateway: gateways.mybank,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420404',
		payment: {
			gateway: gateways.mybank,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420409',
		payment: {
			gateway: gateways.belfius,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420410',
		payment: {
			gateway: gateways.belfius,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420411',
		payment: {
			gateway: gateways.belfius,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420412',
		payment: {
			gateway: gateways.belfius,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420413',
		payment: {
			gateway: gateways.billie,
			status: 'authorized',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C420414',
		payment: {
			gateway: gateways.billie,
			status: 'failed',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C420415',
		payment: {
			gateway: gateways.billie,
			status: 'canceled',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C420416',
		payment: {
			gateway: gateways.billie,
			status: 'expired',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C420417',
		payment: {
			gateway: gateways.paysafecard,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420418',
		payment: {
			gateway: gateways.paysafecard,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420419',
		payment: {
			gateway: gateways.paysafecard,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3007283',
		payment: {
			gateway: gateways.klarna,
			status: 'authorized',
		},
	},
	{
		...baseOrder,
		testId: 'C3007284',
		payment: {
			gateway: gateways.klarna,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007285',
		payment: {
			gateway: gateways.klarna,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007286',
		payment: {
			gateway: gateways.klarna,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3007291',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3007292',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007293',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007294',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3241647',
		payment: {
			gateway: gateways.alma,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3241648',
		payment: {
			gateway: gateways.alma,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3241649',
		payment: {
			gateway: gateways.alma,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3241650',
		payment: {
			gateway: gateways.alma,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3437850',
		payment: {
			gateway: gateways.trustly,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3437851',
		payment: {
			gateway: gateways.trustly,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3437852',
		payment: {
			gateway: gateways.trustly,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3437853',
		payment: {
			gateway: gateways.trustly,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3622421',
		payment: {
			gateway: gateways.riverty,
			status: 'authorized',
		},
	},
	{
		...baseOrder,
		testId: 'C3622422',
		payment: {
			gateway: gateways.riverty,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3622423',
		payment: {
			gateway: gateways.riverty,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3622424',
		payment: {
			gateway: gateways.riverty,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3757243',
		payment: {
			gateway: gateways.satispay,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3757244',
		payment: {
			gateway: gateways.satispay,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3757245',
		payment: {
			gateway: gateways.satispay,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3757246',
		payment: {
			gateway: gateways.satispay,
			status: 'expired',
		},
	},
];
