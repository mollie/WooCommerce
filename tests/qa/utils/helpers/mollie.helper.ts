/**
 * External dependencies
 */
import { OrderReceived, PayForOrder } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { expect, MollieHostedCheckout } from '..';
import {
	MolliePaymentStatus,
	MollieSettings,
	MollieGateway,
} from '../../resources';

export const getOrderStatusFromMollieStatus = (
	paymentStatus: MolliePaymentStatus,
	mollieApiMethod: MollieSettings.ApiMethod = 'payment',
): WooCommerce.OrderStatus => {
	const statusConversion = {
		paid: 'processing',
		authorized: 'processing',
		canceled: 'pending',
		expired: mollieApiMethod === 'payment' ? 'cancelled' : 'pending',
		failed: mollieApiMethod === 'payment' ? 'failed' : 'pending',
		open: 'pending',
		pending: 'pending',
	} as Record< MolliePaymentStatus, WooCommerce.OrderStatus >;

	return statusConversion[ paymentStatus ];
};

export function buildMollieGatewayLabel( gateway: MollieGateway ): string {
	let label = gateway.name;

	if ( gateway.slug === 'creditcard' ) {
		const componentsEnabled =
			gateway.settings.mollie_components_enabled === 'yes';
		label += componentsEnabled
			? ' - Mollie components enabled'
			: ' - Mollie components disabled';
	}

	return label;
}

/**
 * Depending on payment status different WC pages are shown after payment
 *
 * @param param0
 * @param param0.mollieHostedCheckout
 * @param param0.orderReceived
 * @param param0.payForOrder
 * @param orderId
 * @param order
 */
export const processMolliePaymentStatus = async (
	{
		mollieHostedCheckout,
		orderReceived,
		payForOrder,
	}: {
		mollieHostedCheckout: MollieHostedCheckout;
		orderReceived: OrderReceived;
		payForOrder: PayForOrder;
	},
	orderId: number,
	order: WooCommerce.ShopOrder
): Promise< void > => {
	const { payment } = order;
	let orderNumber: number;
	switch ( payment.status ) {
		case 'paid':
		case 'pending':
		case 'authorized':
			await orderReceived.assertOrderDetails( order );
			orderNumber = await orderReceived.getOrderNumber();
			await expect(
				orderId,
				'Assert order ID matches order number'
			).toEqual( orderNumber );
			break;

		case 'failed':
		case 'canceled':
			await expect(
				payForOrder.heading(),
				'Assert pay for order heading is visible'
			).toBeVisible();
			orderNumber = await payForOrder.getOrderNumberFromUrl();
			await expect(
				orderId,
				'Assert order ID matches order number'
			).toEqual( orderNumber );
			break;

		case 'expired':
			await expect(
				mollieHostedCheckout.page,
				'Assert mollie hosted checkout page has URL for expired order'
			).toHaveURL( mollieHostedCheckout.expiredUrlRegex );
			break;
	}
};
