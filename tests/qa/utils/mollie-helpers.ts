/**
 * External dependencies
 */
import {
	OrderReceived,
	PayForOrder,
	WooCommerceApi,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { expect, MollieHostedCheckout } from '.';
import {
	MolliePaymentStatus,
	MollieSettings,
	MollieGateway,
} from '../resources';

const testedApiMethod =
	( process.env.MOLLIE_API_METHOD as MollieSettings.ApiMethod ) || 'payment';

export const getOrderStatusFromMollieStatus = (
	paymentStatus: MolliePaymentStatus
): WooCommerce.OrderStatus => {
	const isPaymentApiMethod = testedApiMethod === 'payment';

	const statusConversion = {
		paid: 'processing',
		authorized: 'processing',
		canceled: 'pending',
		expired: isPaymentApiMethod ? 'cancelled' : 'pending',
		failed: isPaymentApiMethod ? 'failed' : 'pending',
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
 * @param param0 
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
