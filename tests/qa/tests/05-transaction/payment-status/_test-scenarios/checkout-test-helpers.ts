/**
 * External dependencies
 */
import { OrderReceived, PayForOrder, WooCommerceApi } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { expect, MollieHostedCheckout } from '../../../../utils';
import { MollieGateway } from '../../../../resources';

export function buildGatewayLabel( gateway: MollieGateway ): string {
	let label = gateway.name;
	
	if (gateway.slug === 'creditcard') {
		const componentsEnabled = gateway.settings.mollie_components_enabled === 'yes';
		label += componentsEnabled 
			? ' - Mollie components enabled' 
			: ' - Mollie components disabled';
	}
	
	return label;
}

export async function updateCurrencyIfNeeded(
	wooCommerceApi: WooCommerceApi,
	currency: string | undefined
) {
	if ( currency && currency !== 'EUR' ) {
		await wooCommerceApi.updateGeneralSettings( {
			woocommerce_currency: currency,
		} );
	}
}

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
