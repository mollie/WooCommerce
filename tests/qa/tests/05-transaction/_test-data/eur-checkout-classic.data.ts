/**
 * Internal dependencies
 */
import { MollieTestData, gateways, cards } from '../../../resources';
import { baseOrder } from './transaction-base-order.data';

export const classicCheckoutEur: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C4237579',
		payment: {
			gateway: gateways.mbway,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237580',
		payment: {
			gateway: gateways.mbway,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237581',
		payment: {
			gateway: gateways.mbway,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237582',
		payment: {
			gateway: gateways.mbway,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C4237575',
		payment: {
			gateway: gateways.multibanco,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237576',
		payment: {
			gateway: gateways.multibanco,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237577',
		payment: {
			gateway: gateways.multibanco,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237578',
		payment: {
			gateway: gateways.multibanco,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3731',
		payment: {
			gateway: gateways.in3,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3732',
		payment: {
			gateway: gateways.in3,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3733',
		payment: {
			gateway: gateways.in3,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3734',
		payment: {
			gateway: gateways.in3,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3387',
		payment: {
			gateway: gateways.bancontact,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3388',
		payment: {
			gateway: gateways.bancontact,
			status: 'open',
		},
	},
	{
		...baseOrder,
		testId: 'C3389',
		payment: {
			gateway: gateways.bancontact,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3390',
		payment: {
			gateway: gateways.bancontact,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3391',
		payment: {
			gateway: gateways.bancontact,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3424',
		payment: {
			gateway: gateways.przelewy24,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3425',
		payment: {
			gateway: gateways.przelewy24,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3426',
		payment: {
			gateway: gateways.przelewy24,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3427',
		payment: {
			gateway: gateways.przelewy24,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3382',
		payment: {
			gateway: gateways.ideal,
			status: 'paid',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C3383',
		payment: {
			gateway: gateways.ideal,
			status: 'open',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C3384',
		payment: {
			gateway: gateways.ideal,
			status: 'failed',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C3385',
		payment: {
			gateway: gateways.ideal,
			status: 'expired',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C3386',
		payment: {
			gateway: gateways.ideal,
			status: 'canceled',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C3392',
		payment: {
			gateway: gateways.paypal,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3393',
		payment: {
			gateway: gateways.paypal,
			status: 'pending',
		},
	},
	{
		...baseOrder,
		testId: 'C3394',
		payment: {
			gateway: gateways.paypal,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3395',
		payment: {
			gateway: gateways.paypal,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3396',
		payment: {
			gateway: gateways.paypal,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3412',
		payment: {
			gateway: gateways.eps,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3413',
		payment: {
			gateway: gateways.eps,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3414',
		payment: {
			gateway: gateways.eps,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3415',
		payment: {
			gateway: gateways.eps,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3419',
		payment: {
			gateway: gateways.kbc,
			status: 'paid',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C3416',
		payment: {
			gateway: gateways.kbc,
			status: 'failed',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C3417',
		payment: {
			gateway: gateways.kbc,
			status: 'canceled',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C3418',
		payment: {
			gateway: gateways.kbc,
			status: 'expired',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C3376',
		payment: {
			gateway: gateways.creditcard,
			status: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'yes',
		},
	},
	{
		...baseOrder,
		testId: 'C3377',
		payment: {
			gateway: gateways.creditcard,
			status: 'open',
			card: cards.visa,
			mollieComponentsEnabled: 'yes',
		},
	},
	{
		...baseOrder,
		testId: 'C3379',
		payment: {
			gateway: gateways.creditcard,
			status: 'expired',
			card: cards.visa,
		},
	},
	{
		...baseOrder,
		testId: 'C3378',
		payment: {
			gateway: gateways.creditcard,
			status: 'failed',
			card: cards.visa,
		},
	},
	{
		...baseOrder,
		testId: 'C3433',
		payment: {
			gateway: gateways.banktransfer,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3432',
		payment: {
			gateway: gateways.banktransfer,
			status: 'open',
		},
		orderStatus: 'on-hold',
	},
	{
		...baseOrder,
		testId: 'C3434',
		payment: {
			gateway: gateways.banktransfer,
			status: 'expired',
		},
		orderStatus: 'on-hold',
	},
	{
		...baseOrder,
		testId: 'C420294',
		payment: {
			gateway: gateways.mybank,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420295',
		payment: {
			gateway: gateways.mybank,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420296',
		payment: {
			gateway: gateways.mybank,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420297',
		payment: {
			gateway: gateways.mybank,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3428',
		payment: {
			gateway: gateways.belfius,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3429',
		payment: {
			gateway: gateways.belfius,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3430',
		payment: {
			gateway: gateways.belfius,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3431',
		payment: {
			gateway: gateways.belfius,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C354674',
		payment: {
			gateway: gateways.billie,
			status: 'authorized',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C354675',
		payment: {
			gateway: gateways.billie,
			status: 'failed',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C354676',
		payment: {
			gateway: gateways.billie,
			status: 'canceled',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C354677',
		payment: {
			gateway: gateways.billie,
			status: 'expired',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C420141',
		payment: {
			gateway: gateways.paysafecard,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420142',
		payment: {
			gateway: gateways.paysafecard,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420143',
		payment: {
			gateway: gateways.paysafecard,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3007255',
		payment: {
			gateway: gateways.klarna,
			status: 'authorized',
		},
	},
	{
		...baseOrder,
		testId: 'C3007256',
		payment: {
			gateway: gateways.klarna,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007257',
		payment: {
			gateway: gateways.klarna,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007258',
		payment: {
			gateway: gateways.klarna,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3007267',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3007268',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007269',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007270',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3241639',
		payment: {
			gateway: gateways.alma,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3241640',
		payment: {
			gateway: gateways.alma,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3241641',
		payment: {
			gateway: gateways.alma,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3241642',
		payment: {
			gateway: gateways.alma,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3437842',
		payment: {
			gateway: gateways.trustly,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3437843',
		payment: {
			gateway: gateways.trustly,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3437844',
		payment: {
			gateway: gateways.trustly,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3437845',
		payment: {
			gateway: gateways.trustly,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3622413',
		payment: {
			gateway: gateways.riverty,
			status: 'authorized',
		},
	},
	{
		...baseOrder,
		testId: 'C3622414',
		payment: {
			gateway: gateways.riverty,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3622415',
		payment: {
			gateway: gateways.riverty,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3622416',
		payment: {
			gateway: gateways.riverty,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3757251',
		payment: {
			gateway: gateways.satispay,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3757252',
		payment: {
			gateway: gateways.satispay,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3757253',
		payment: {
			gateway: gateways.satispay,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3757254',
		payment: {
			gateway: gateways.satispay,
			status: 'expired',
		},
	},
];
