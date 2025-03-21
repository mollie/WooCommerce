/**
 * External dependencies
 */
import {
	OrderReceived,
	WooCommerceUtils,
	expect,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import {
	ClassicCheckout,
	PayForOrder,
	Checkout,
	MollieHostedCheckout,
} from './frontend';
import { Utils } from './utils';

export class Transaction {
	wooCommerceUtils: WooCommerceUtils;
	orderReceived: OrderReceived;
	checkout: Checkout;
	classicCheckout: ClassicCheckout;
	payForOrder: PayForOrder;
	mollieHostedCheckout: MollieHostedCheckout;
	utils: Utils;

	constructor( {
		wooCommerceUtils,
		orderReceived,
		checkout,
		classicCheckout,
		payForOrder,
		mollieHostedCheckout,
		utils,
	} ) {
		this.wooCommerceUtils = wooCommerceUtils;
		this.orderReceived = orderReceived;
		this.checkout = checkout;
		this.classicCheckout = classicCheckout;
		this.payForOrder = payForOrder;
		this.mollieHostedCheckout = mollieHostedCheckout;
		this.utils = utils;
	}

	onCheckout = async ( order: WooCommerce.ShopOrder ) => {
		await this.utils.fillVisitorsCart( order.products );
		await this.checkout.makeOrder( order );
		return this.processMolliePaymentByStatus( order );
	};

	onClassicCheckout = async ( order: WooCommerce.ShopOrder ) => {
		await this.utils.fillVisitorsCart( order.products );
		await this.classicCheckout.makeOrder( order );
		return this.processMolliePaymentByStatus( order );
	};

	onPayForOrder = async ( order: WooCommerce.ShopOrder ) => {
		const apiOrder = await this.wooCommerceUtils.createApiOrder( order );
		await this.payForOrder.makeOrder(
			apiOrder.id,
			apiOrder.order_key,
			order
		);
		return this.processMolliePaymentByStatus( order );
	};

	processMolliePaymentByStatus = async ( order: WooCommerce.ShopOrder ) => {
		let orderNumber;
		const { payment } = order;
		const mollieOrderNumber = await this.mollieHostedCheckout.pay(
			payment
		);

		switch ( payment.status ) {
			case 'paid':
			case 'pending':
			case 'authorized':
				await this.orderReceived.assertOrderDetails( order );
				orderNumber = await this.orderReceived.getOrderNumber();
				await expect( mollieOrderNumber ).toEqual( orderNumber );
				break;

			case 'failed':
			case 'canceled':
				await expect( this.payForOrder.heading() ).toBeVisible();
				orderNumber = await this.payForOrder.getOrderNumberFromUrl();
				await expect( mollieOrderNumber ).toEqual( orderNumber );
				break;

			case 'expired':
				await expect( this.mollieHostedCheckout.page ).toHaveURL(
					this.mollieHostedCheckout.expiredUrlRegex
				);
				break;
		}

		return mollieOrderNumber;
	};
}
