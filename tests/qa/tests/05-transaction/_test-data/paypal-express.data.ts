/**
 * Internal dependencies
 */
import {
	MollieTestData,
	gateways,
	products,
	freeShipping,
} from '../../../resources';
import { baseOrder } from './transaction-base-order.data';

// PayPal Express only renders when every product involved is virtual (see
// PayPalExpressButton::isVirtualProduct() / registerCartPageHook() in
// src/Buttons/PayPalButton/PayPalExpressButton.php), so all three page
// variants below use the virtual product instead of baseOrder's default.
//
// The Express flow never calls selectShippingMethod() (it bypasses normal
// checkout entirely) and a virtual product needs no shipping, so the real
// order never gets a shipping line - confirmed via the order-edit page
// ("No shipping address set", Order Total = Items Subtotal + tax only).
// Override baseOrder's flatRate shipping with freeShipping so countTotals()
// expects the same zero-shipping total the real order actually has.
const virtualOrder: MollieTestData.ShopOrder = {
	...baseOrder,
	products: [ products.mollieVirtual100 ],
	shipping: freeShipping,
};

// orderStatus is set explicitly here instead of relying on getOrderStatusFromMollieStatus()

export const payPalExpressProduct: MollieTestData.ShopOrder[] = [
	{
		...virtualOrder,
		testId: 'C4567643',
		testLabel: '@Critical',
		orderStatus: 'completed',
		payment: {
			gateway: gateways.paypal,
			status: 'paid',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567644',
		orderStatus: 'pending',
		payment: {
			gateway: gateways.paypal,
			status: 'pending',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567645',
		orderStatus: 'failed',
		payment: {
			gateway: gateways.paypal,
			status: 'failed',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567646',
		orderStatus: 'pending',
		payment: {
			gateway: gateways.paypal,
			status: 'canceled',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567647',
		orderStatus: 'cancelled',
		payment: {
			gateway: gateways.paypal,
			status: 'expired',
		},
	},
];

export const payPalExpressCart: MollieTestData.ShopOrder[] = [
	{
		...virtualOrder,
		testId: 'C4567648',
		testLabel: '@Critical',
		orderStatus: 'completed',
		payment: {
			gateway: gateways.paypal,
			status: 'paid',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567649',
		orderStatus: 'pending',
		payment: {
			gateway: gateways.paypal,
			status: 'pending',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567650',
		orderStatus: 'failed',
		payment: {
			gateway: gateways.paypal,
			status: 'failed',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567651',
		orderStatus: 'pending',
		payment: {
			gateway: gateways.paypal,
			status: 'canceled',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567652',
		orderStatus: 'cancelled',
		payment: {
			gateway: gateways.paypal,
			status: 'expired',
		},
	},
];

export const payPalExpressCheckout: MollieTestData.ShopOrder[] = [
	{
		...virtualOrder,
		testId: 'C4567653',
		testLabel: '@Critical',
		orderStatus: 'completed',
		payment: {
			gateway: gateways.paypal,
			status: 'paid',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567654',
		orderStatus: 'pending',
		payment: {
			gateway: gateways.paypal,
			status: 'pending',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567655',
		orderStatus: 'failed',
		payment: {
			gateway: gateways.paypal,
			status: 'failed',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567656',
		orderStatus: 'pending',
		payment: {
			gateway: gateways.paypal,
			status: 'canceled',
		},
	},
	{
		...virtualOrder,
		testId: 'C4567657',
		orderStatus: 'cancelled',
		payment: {
			gateway: gateways.paypal,
			status: 'expired',
		},
	},
];
