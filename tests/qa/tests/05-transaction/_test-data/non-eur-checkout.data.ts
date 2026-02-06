/**
 * Internal dependencies
 */
import { MollieTestData, gateways } from '../../../resources';
import { baseOrder } from './transaction-base-order.data';

export const checkoutNonEur: MollieTestData.ShopOrder[] = [
	{
		...baseOrder,
		testId: 'C3007271',
		payment: {
			gateway: gateways.twint,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3007272',
		payment: {
			gateway: gateways.twint,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007273',
		payment: {
			gateway: gateways.twint,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007274',
		payment: {
			gateway: gateways.twint,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C3007275',
		payment: {
			gateway: gateways.blik,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C3007276',
		payment: {
			gateway: gateways.blik,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C3007277',
		payment: {
			gateway: gateways.blik,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C3007278',
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
		testId: 'C4237599',
		payment: {
			gateway: gateways.paybybank,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237500',
		payment: {
			gateway: gateways.paybybank,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237501',
		payment: {
			gateway: gateways.paybybank,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237502',
		payment: {
			gateway: gateways.paybybank,
			status: 'expired',
		},
	},
	{
		...baseOrder,
		testId: 'C4237603',
		payment: {
			gateway: gateways.swish,
			status: 'paid',
		},
	},
	{
		...baseOrder,
		testId: 'C4237604',
		payment: {
			gateway: gateways.swish,
			status: 'failed',
		},
	},
	{
		...baseOrder,
		testId: 'C4237605',
		payment: {
			gateway: gateways.swish,
			status: 'canceled',
		},
	},
	{
		...baseOrder,
		testId: 'C4237606',
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
		testId: 'C00003',
		payment: {
			gateway: gateways.mobilepay,
			status: 'expired',
		},
	},
];
