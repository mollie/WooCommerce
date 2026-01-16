/**
 * Internal dependencies
 */
import { MollieTestData, gateways } from '../../../resources';
import { baseOrder } from './transaction-base-order.data';

export const classicCheckoutNonEur: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C3007247',
		payment: {
			gateway: gateways.twint,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3007248',
		payment: {
			gateway: gateways.twint,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007249',
		payment: {
			gateway: gateways.twint,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007250',
		payment: {
			gateway: gateways.twint,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3007251',
		payment: {
			gateway: gateways.blik,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3007252',
		payment: {
			gateway: gateways.blik,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007253',
		payment: {
			gateway: gateways.blik,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007254',
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
		testId: 'C4237583',
		payment: {
			gateway: gateways.paybybank,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237584',
		payment: {
			gateway: gateways.paybybank,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237585',
		payment: {
			gateway: gateways.paybybank,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237586',
		payment: {
			gateway: gateways.paybybank,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C4237587',
		payment: {
			gateway: gateways.swish,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237588',
		payment: {
			gateway: gateways.swish,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237589',
		payment: {
			gateway: gateways.swish,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237590',
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
		testId: 'C00001',
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
		testId: 'C00002',
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
