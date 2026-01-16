/**
 * Internal dependencies
 */
import { MollieTestData, gateways } from '../../../../resources';
import { baseOrder } from './transaction-base-order.data';

export const payForOrderNonEur: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C3007279',
		payment: {
			gateway: gateways.twint,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3007280',
		payment: {
			gateway: gateways.twint,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007281',
		payment: {
			gateway: gateways.twint,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007282',
		payment: {
			gateway: gateways.twint,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3007287',
		payment: {
			gateway: gateways.blik,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3007288',
		payment: {
			gateway: gateways.blik,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007289',
		payment: {
			gateway: gateways.blik,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007290',
		payment: {
			gateway: gateways.blik,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.paybybank,
			status: 'open',
		},
	},
	{
		...baseOrder,
		testId: 'C4237615',
		payment: {
			gateway: gateways.paybybank,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237616',
		payment: {
			gateway: gateways.paybybank,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237617',
		payment: {
			gateway: gateways.paybybank,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237618',
		payment: {
			gateway: gateways.paybybank,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C4237619',
		payment: {
			gateway: gateways.swish,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237620',
		payment: {
			gateway: gateways.swish,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237621',
		payment: {
			gateway: gateways.swish,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237622',
		payment: {
			gateway: gateways.swish,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.vipps,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.vipps,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.vipps,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.vipps,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.mobilepay,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.mobilepay,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.mobilepay,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.mobilepay,
			status: 'expired',
		},
	},
];
