/**
 * External dependencies
 */
import { OrderReceived, PayForOrder } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { expect, MollieHostedCheckout } from '../../../../utils';

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
			await expect( orderId ).toEqual( orderNumber );
			break;

		case 'failed':
		case 'canceled':
			await expect( payForOrder.heading() ).toBeVisible();
			orderNumber = await payForOrder.getOrderNumberFromUrl();
			await expect( orderId ).toEqual( orderNumber );
			break;

		case 'expired':
			await expect( mollieHostedCheckout.page ).toHaveURL(
				mollieHostedCheckout.expiredUrlRegex
			);
			break;
	}
};
