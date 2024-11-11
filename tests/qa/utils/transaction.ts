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

	onCheckout = async ( data: WooCommerce.ShopOrder ) => {
		await this.utils.fillVisitorsCart( data.products );
		await this.checkout.makeOrder( data );
		return this.processMolliePaymentByStatus( data );
	};

	onClassicCheckout = async ( data: WooCommerce.ShopOrder ) => {
		await this.utils.fillVisitorsCart( data.products );
		await this.classicCheckout.makeOrder( data );
		return this.processMolliePaymentByStatus( data );
	};

	onPayForOrder = async ( data: WooCommerce.ShopOrder ) => {
		const order = await this.wooCommerceUtils.createApiOrder( data );
		await this.payForOrder.makeOrder( order.id, order.order_key, data );
		return this.processMolliePaymentByStatus( data );
	};

	processMolliePaymentByStatus = async ( data: WooCommerce.ShopOrder ) => {
		let orderNumber;
		const mollieOrderNumber = await this.mollieHostedCheckout.pay(
			data.payment
		);

		switch ( data.payment.status ) {
			case 'paid':
			case 'pending':
			case 'authorized':
				await this.orderReceived.assertOrderDetails( data );
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
