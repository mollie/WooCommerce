/**
 * External dependencies
 */
import { Client as MollieClientApi } from 'mollie-api-typescript';
import { expect, WooCommerceApi } from '@inpsyde/playwright-utils/build';

type MollieResource = {
	id: string;
	status?: string;
	metadata?: { order_id?: string | number };
	amount?: { value: string; currency: string };
};

/**
 * Loads a Mollie payment (tr_) or order (ord_). The SDK has no Orders API.
 */
export const getMollieResource = async (
	mollieClientApi: MollieClientApi,
	resourceId: string
): Promise< MollieResource > => {
	if ( resourceId.startsWith( 'ord_' ) ) {
		const response = await fetch(
			`https://api.mollie.com/v2/orders/${ resourceId }`,
			{
				headers: {
					Authorization: `Bearer ${ process.env.MOLLIE_TEST_API_KEY }`,
				},
			}
		);
		return response.json();
	}
	return ( await mollieClientApi.payments.get( {
		paymentId: resourceId,
	} ) ) as MollieResource;
};

/**
 * Asserts the order's Mollie payment was created for it (metadata.order_id, amount, currency).
 */
export const assertPaymentBelongsToOrder = async (
	{
		mollieClientApi,
		wooCommerceApi,
	}: { mollieClientApi: MollieClientApi; wooCommerceApi: WooCommerceApi },
	orderId: number
) => {
	const order = await wooCommerceApi.getOrder( orderId );
	await expect(
		order.transaction_id,
		`Assert order ${ orderId } has a Mollie transaction ID`
	).toBeTruthy();

	const payment = await getMollieResource(
		mollieClientApi,
		order.transaction_id
	);
	await expect(
		String( payment.metadata?.order_id ),
		`Assert Mollie ${ payment.id } metadata.order_id is order ${ orderId }`
	).toEqual( String( orderId ) );
	await expect(
		Number( payment.amount?.value ),
		`Assert Mollie ${ payment.id } amount equals order ${ orderId } total`
	).toEqual( Number( order.total ) );
	await expect(
		payment.amount?.currency,
		`Assert Mollie ${ payment.id } currency equals order ${ orderId } currency`
	).toEqual( order.currency );
};
