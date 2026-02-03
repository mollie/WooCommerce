/**
 * Internal dependencies
 */
import { MollieTestData, gateways, cards } from '../../../resources';
import { baseOrder } from './transaction-base-order.data';

export const checkoutEur: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C4237595',
		payment: {
			gateway: gateways.mbway,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237596',
		payment: {
			gateway: gateways.mbway,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237597',
		payment: {
			gateway: gateways.mbway,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237598',
		payment: {
			gateway: gateways.mbway,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C4237591',
		payment: {
			gateway: gateways.multibanco,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237592',
		payment: {
			gateway: gateways.multibanco,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237593',
		payment: {
			gateway: gateways.multibanco,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237594',
		payment: {
			gateway: gateways.multibanco,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420219',
		payment: {
			gateway: gateways.in3,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420220',
		payment: {
			gateway: gateways.in3,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420221',
		payment: {
			gateway: gateways.in3,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420222',
		payment: {
			gateway: gateways.in3,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420230',
		payment: {
			gateway: gateways.bancontact,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420231',
		payment: {
			gateway: gateways.bancontact,
			status: 'open',
		},
	},
	{
		...baseOrder,
		testId: 'C420232',
		payment: {
			gateway: gateways.bancontact,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420233',
		payment: {
			gateway: gateways.bancontact,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420234',
		payment: {
			gateway: gateways.bancontact,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420235',
		payment: {
			gateway: gateways.przelewy24,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420236',
		payment: {
			gateway: gateways.przelewy24,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420237',
		payment: {
			gateway: gateways.przelewy24,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420238',
		payment: {
			gateway: gateways.przelewy24,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420244',
		payment: {
			gateway: gateways.ideal,
			status: 'paid',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C420245',
		payment: {
			gateway: gateways.ideal,
			status: 'open',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C420246',
		payment: {
			gateway: gateways.ideal,
			status: 'failed',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C420248',
		payment: {
			gateway: gateways.ideal,
			status: 'canceled',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C420247',
		payment: {
			gateway: gateways.ideal,
			status: 'expired',
			bankIssuer: 'ING',
		},
	},
	{
		...baseOrder,
		testId: 'C420253',
		payment: {
			gateway: gateways.paypal,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420254',
		payment: {
			gateway: gateways.paypal,
			status: 'pending',
		},
	},
	{
		...baseOrder,
		testId: 'C420255',
		payment: {
			gateway: gateways.paypal,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420256',
		payment: {
			gateway: gateways.paypal,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420257',
		payment: {
			gateway: gateways.paypal,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420260',
		payment: {
			gateway: gateways.eps,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420261',
		payment: {
			gateway: gateways.eps,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420262',
		payment: {
			gateway: gateways.eps,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420263',
		payment: {
			gateway: gateways.eps,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420267',
		payment: {
			gateway: gateways.kbc,
			status: 'paid',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C420264',
		payment: {
			gateway: gateways.kbc,
			status: 'failed',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C420265',
		payment: {
			gateway: gateways.kbc,
			status: 'canceled',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C420266',
		payment: {
			gateway: gateways.kbc,
			status: 'expired',
			bankIssuer: 'KBC',
		},
	},
	{
		...baseOrder,
		testId: 'C420274',
		payment: {
			gateway: gateways.creditcard,
			status: 'open',
			card: cards.visa,
		},
	},
	{
		...baseOrder,
		testId: 'C420273',
		payment: {
			gateway: gateways.creditcard,
			status: 'paid',
			card: cards.visa,
		},
	},
	{
		...baseOrder,
		testId: 'C420275',
		payment: {
			gateway: gateways.creditcard,
			status: 'failed',
			card: cards.visa,
		},
	},
	{
		...baseOrder,
		testId: 'C420276',
		payment: {
			gateway: gateways.creditcard,
			status: 'expired',
			card: cards.visa,
		},
	},
	{
		...baseOrder,
		testId: 'C420284',
		payment: {
			gateway: gateways.banktransfer,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420283',
		payment: {
			gateway: gateways.banktransfer,
			status: 'open',
		},
		orderStatus: 'on-hold',
	},
	{
		...baseOrder,
		testId: 'C420285',
		payment: {
			gateway: gateways.banktransfer,
			status: 'expired',
		},
		orderStatus: 'on-hold',
	},
	{
		...baseOrder,
		testId: 'C420286',
		payment: {
			gateway: gateways.mybank,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420287',
		payment: {
			gateway: gateways.mybank,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420288',
		payment: {
			gateway: gateways.mybank,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420289',
		payment: {
			gateway: gateways.mybank,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420298',
		payment: {
			gateway: gateways.belfius,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420299',
		payment: {
			gateway: gateways.belfius,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C420300',
		payment: {
			gateway: gateways.belfius,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420301',
		payment: {
			gateway: gateways.belfius,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C420302',
		payment: {
			gateway: gateways.billie,
			status: 'authorized',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C420303',
		payment: {
			gateway: gateways.billie,
			status: 'failed',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C420304',
		payment: {
			gateway: gateways.billie,
			status: 'canceled',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C420305',
		payment: {
			gateway: gateways.billie,
			status: 'expired',
			billingCompany: 'Syde',
		},
	},
	{
		...baseOrder,
		testId: 'C420306',
		payment: {
			gateway: gateways.paysafecard,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C420307',
		payment: {
			gateway: gateways.paysafecard,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C420308',
		payment: {
			gateway: gateways.paysafecard,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3007259',
		payment: {
			gateway: gateways.klarna,
			status: 'authorized',
		},
	},
	{
		...baseOrder,
		testId: 'C3007260',
		payment: {
			gateway: gateways.klarna,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007261',
		payment: {
			gateway: gateways.klarna,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007262',
		payment: {
			gateway: gateways.klarna,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3007263',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3007264',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007265',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007266',
		payment: {
			gateway: gateways.bancomatpay,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3241643',
		payment: {
			gateway: gateways.alma,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3241644',
		payment: {
			gateway: gateways.alma,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3241645',
		payment: {
			gateway: gateways.alma,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3241646',
		payment: {
			gateway: gateways.alma,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3437846',
		payment: {
			gateway: gateways.trustly,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3437847',
		payment: {
			gateway: gateways.trustly,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3437848',
		payment: {
			gateway: gateways.trustly,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3437849',
		payment: {
			gateway: gateways.trustly,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3622417',
		payment: {
			gateway: gateways.riverty,
			status: 'authorized',
		},
	},
	{
		...baseOrder,
		testId: 'C3622418',
		payment: {
			gateway: gateways.riverty,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3622419',
		payment: {
			gateway: gateways.riverty,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3622420',
		payment: {
			gateway: gateways.riverty,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3757247',
		payment: {
			gateway: gateways.satispay,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3757248',
		payment: {
			gateway: gateways.satispay,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3757249',
		payment: {
			gateway: gateways.satispay,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3757250',
		payment: {
			gateway: gateways.satispay,
			status: 'expired',
		},
	},
];
