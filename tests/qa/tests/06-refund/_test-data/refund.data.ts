/**
 * Internal dependencies
 */
import { MollieTestData, gateways, cards, customers } from '../../../resources';
import { baseOrder } from './refund-base-order.data';

export const refundViaWooCommerce: MollieTestData.ShopRefund[] = [
	{
		...baseOrder,
		testId: 'C3458',
		payment: {
			gateway: gateways.ideal,
			status: 'paid',
			bankIssuer: 'ING',
		},
		refundPercentage: 100,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'pending',
		isMollieClientApiRefund: false,
	},
	{
		...baseOrder,
		testId: 'C3493',
		payment: {
			gateway: gateways.ideal,
			status: 'paid',
			bankIssuer: 'ING',
		},
		refundPercentage: 50,
		refundOrderStatus: 'processing',
		refundPaymentStatus: 'pending',
		isMollieClientApiRefund: false,
	},
	{
		...baseOrder,
		testId: 'C3459',
		payment: {
			gateway: gateways.creditcard,
			status: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'yes',
		},
		refundPercentage: 100,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'pending',
		isMollieClientApiRefund: false,
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.creditcard,
			status: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'yes',
		},
		refundPercentage: 50,
		refundOrderStatus: 'processing',
		refundPaymentStatus: 'pending',
		isMollieClientApiRefund: false,
	},
];

export const refundViaMollieDashboard: MollieTestData.ShopRefund[] = [
	{
		...baseOrder,
		testId: 'C3476',
		payment: {
			gateway: gateways.ideal,
			status: 'paid',
			bankIssuer: 'ING',
		},
		refundPercentage: 100,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'pending',
		isMollieClientApiRefund: true,
	},
	{
		...baseOrder,
		testId: 'C3497',
		payment: {
			gateway: gateways.ideal,
			status: 'paid',
			bankIssuer: 'ING',
		},
		refundPercentage: 50,
		refundOrderStatus: 'processing',
		refundPaymentStatus: 'pending',
		isMollieClientApiRefund: true,
	},
	{
		...baseOrder,
		testId: 'C3477',
		payment: {
			gateway: gateways.creditcard,
			status: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'yes',
		},
		refundPercentage: 100,
		refundOrderStatus: 'refunded',
		refundPaymentStatus: 'pending',
		isMollieClientApiRefund: true,
	},
	{
		...baseOrder,
		testId: 'C0000',
		payment: {
			gateway: gateways.creditcard,
			status: 'paid',
			card: cards.visa,
			mollieComponentsEnabled: 'yes',
		},
		refundPercentage: 50,
		refundOrderStatus: 'processing',
		refundPaymentStatus: 'pending',
		isMollieClientApiRefund: true,
	},
];
