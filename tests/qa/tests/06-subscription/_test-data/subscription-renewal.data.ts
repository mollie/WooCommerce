/**
 * Internal dependencies
 */
import { cards, MollieTestData } from '../../../resources';

export const classicCheckoutEur: MollieTestData.Transaction[] = [
	// {
	// 	testId: '',
	// 	gatewaySlug: 'applepay',
	// 	paymentStatus: 'paid',
	// 	orderStatus: 'processing',
	// },
	{
		testId: 'C0000',
		gatewaySlug: 'in3',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'in3',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'in3',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'in3',
		paymentStatus: 'expired',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'bancontact',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'bancontact',
		paymentStatus: 'open',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'bancontact',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'bancontact',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'bancontact',
		paymentStatus: 'expired',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'przelewy24',
		paymentStatus: 'expired',
	},
	// {
	// 	testId: '',
	// 	gatewaySlug: 'voucher',
	// 	paymentStatus: 'expired',
	//
	// },
	{
		testId: 'C0000',
		gatewaySlug: 'ideal',
		paymentStatus: 'paid',
		bankIssuer: 'ING',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'ideal',
		paymentStatus: 'open',
		bankIssuer: 'ING',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'ideal',
		paymentStatus: 'failed',
		bankIssuer: 'ING',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'ideal',
		paymentStatus: 'expired',
		bankIssuer: 'ING',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'ideal',
		paymentStatus: 'canceled',
		bankIssuer: 'ING',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'paypal',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'paypal',
		paymentStatus: 'pending',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'paypal',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'paypal',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'paypal',
		paymentStatus: 'expired',
	},
	// {
	// 	testId: '',
	// 	gatewaySlug: 'giftcard',
	// 	paymentStatus: 'expired',
	//
	// },
	{
		testId: 'C0000',
		gatewaySlug: 'eps',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'eps',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'eps',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'eps',
		paymentStatus: 'expired',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'kbc',
		paymentStatus: 'paid',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'kbc',
		paymentStatus: 'failed',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'kbc',
		paymentStatus: 'canceled',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'kbc',
		paymentStatus: 'expired',
		bankIssuer: 'KBC',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'creditcard',
		paymentStatus: 'paid',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'creditcard',
		paymentStatus: 'open',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'creditcard',
		paymentStatus: 'failed',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'creditcard',
		paymentStatus: 'expired',
		card: cards.visa,
		mollieComponentsEnabled: 'yes',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'open',
		orderStatus: 'on-hold',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'banktransfer',
		paymentStatus: 'expired',
		orderStatus: 'on-hold',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'mybank',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'mybank',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'mybank',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'mybank',
		paymentStatus: 'expired',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'belfius',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'belfius',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'belfius',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'belfius',
		paymentStatus: 'expired',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'billie',
		paymentStatus: 'authorized',
		billingCompany: 'Syde',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'billie',
		paymentStatus: 'failed',
		billingCompany: 'Syde',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'billie',
		paymentStatus: 'canceled',
		billingCompany: 'Syde',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'billie',
		paymentStatus: 'expired',
		billingCompany: 'Syde',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'paysafecard',
		paymentStatus: 'expired',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'klarna',
		paymentStatus: 'authorized',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'klarna',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'klarna',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'klarna',
		paymentStatus: 'expired',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'bancomatpay',
		paymentStatus: 'expired',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'alma',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'alma',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'alma',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'alma',
		paymentStatus: 'expired',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'trustly',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'trustly',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'trustly',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'trustly',
		paymentStatus: 'expired',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'riverty',
		paymentStatus: 'authorized',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'riverty',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'riverty',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'riverty',
		paymentStatus: 'expired',
	},
	// Payconiq unset by client on 04/12/2025
	// {
	// 	testId: 'C0000',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'paid',
	// },
	// {
	// 	testId: 'C0000',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'failed',
	// },
	// {
	// 	testId: 'C0000',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'canceled',
	// },
	// {
	// 	testId: 'C0000',
	// 	gatewaySlug: 'payconiq',
	// 	paymentStatus: 'expired',
	// },
	{
		testId: 'C0000',
		gatewaySlug: 'satispay',
		paymentStatus: 'paid',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'satispay',
		paymentStatus: 'failed',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'satispay',
		paymentStatus: 'canceled',
	},
	{
		testId: 'C0000',
		gatewaySlug: 'satispay',
		paymentStatus: 'expired',
	},
];
