/**
 * Internal dependencies
 */
import {
	MollieTestData,
	gateways,
	cards,
	MollieGateway,
} from '../../../resources';
import { baseOrder } from './transaction-base-order.data';

const creditCardDisabledComponents: MollieGateway = {
	...gateways.creditcard,
	settings: {
		...gateways.creditcard.settings,
		mollie_components_enabled: 'no',
	},
};

export const creditCardDisabledMollieComponentsClassicCheckout: MollieTestData.ShopOrder[] =
	[
		{
			...baseOrder,
			testId: 'C3371',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'paid',
				card: cards.visa,
			},
		},
		{
			...baseOrder,
			testId: 'C3372',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'open',
				card: cards.visa,
			},
		},
		{
			...baseOrder,
			testId: 'C3373',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'failed',
				card: cards.visa,
			},
		},
		{
			...baseOrder,
			testId: 'C3374',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'expired',
				card: cards.visa,
			},
		},
	];

export const creditCardDisabledMollieComponentsCheckout: MollieTestData.ShopOrder[] =
	[
		{
			...baseOrder,
			testId: 'C420271',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'expired',
				card: cards.visa,
			},
		},
		{
			...baseOrder,
			testId: 'C420268',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'paid',
				card: cards.visa,
			},
		},
		{
			...baseOrder,
			testId: 'C420270',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'failed',
				card: cards.visa,
			},
		},
		{
			...baseOrder,
			testId: 'C420269',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'open',
				card: cards.visa,
			},
		},
	];

export const creditCardDisabledMollieComponentsPayForOrder: MollieTestData.ShopOrder[] =
	[
		{
			...baseOrder,
			testId: 'C420386',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'expired',
				card: cards.visa,
			},
		},
		{
			...baseOrder,
			testId: 'C420383',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'paid',
				card: cards.visa,
			},
		},
		{
			...baseOrder,
			testId: 'C420385',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'failed',
				card: cards.visa,
			},
		},
		{
			...baseOrder,
			testId: 'C420384',
			payment: {
				gateway: creditCardDisabledComponents,
				status: 'open',
				card: cards.visa,
			},
		},
	];
